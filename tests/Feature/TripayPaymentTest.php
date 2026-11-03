<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\InvoiceService;
use App\Services\Payments\TripayGateway;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TripayPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.mode' => 'sandbox', 'store.catalog_source' => 'demo', 'payments.tripay.api_key' => 'test-api-key',
            'payments.tripay.private_key' => 'test-private-key', 'payments.tripay.merchant_code' => 'TTEST',
            'payments.tripay.callback_url' => 'https://example.test/api/payments/tripay/callback']);
        Http::preventStrayRequests();
        $this->seed(StoreSeeder::class);
    }

    private function makeOrder(): Order
    {
        $game = Game::where('slug', 'free-fire')->firstOrFail();
        $this->post('/games/free-fire/orders', ['request_id' => (string) Str::uuid(), 'product_id' => $game->products()->first()->id,
            'account' => ['user_id' => '12345678'], 'whatsapp' => '081234567890'])->assertStatus(303);
        return Order::latest('id')->firstOrFail();
    }

    private function endpoint(Order $order, string $action): string
    {
        return '/orders/'.$order->public_id.'/payment/'.$action;
    }

    private function gatewayData(Payment $payment, array $changes = []): array
    {
        return array_replace(['reference' => 'TTESTREF123456', 'merchant_ref' => $payment->invoice_number,
            'payment_method' => 'QRIS', 'amount' => 15500, 'fee_customer' => 500,
            'checkout_url' => 'https://tripay.co.id/checkout/TTESTREF123456', 'expired_time' => $payment->expires_at->timestamp,
            'status' => 'UNPAID', 'pay_code' => 'SANDBOX-CODE',
            'instructions' => [['title' => 'Petunjuk sandbox', 'steps' => ['<b>Uji</b> tanpa uang nyata <script>alert(1)</script>']]]], $changes);
    }

    private function fakeGateway(Payment $payment, array $changes = []): void
    {
        Http::fake([
            '*merchant/payment-channel' => Http::response(['success' => true, 'data' => [['code' => 'QRIS', 'name' => 'QRIS Sandbox', 'active' => true, 'minimum_amount' => 1000, 'maximum_amount' => 1000000]]]),
            '*merchant/fee-calculator*' => Http::response(['success' => true, 'data' => [['code' => 'QRIS', 'total_fee' => ['customer' => 500, 'merchant' => 0]]]]),
            '*transaction/create' => Http::response(['success' => true, 'data' => $this->gatewayData($payment, $changes)]),
            '*transaction/detail*' => Http::response(['success' => true, 'data' => $this->gatewayData($payment)]),
        ]);
    }

    private function readyPayment(): array
    {
        $order = $this->makeOrder();
        $this->fakeGateway($order->payment);
        $this->post($this->endpoint($order, 'method'), ['method' => 'QRIS', 'email' => 'customer@example.test'])->assertStatus(303);
        $payment = $order->payment->fresh();
        $this->post($this->endpoint($order, 'start'), ['quote_id' => $payment->quote_id])->assertStatus(303);
        return [$order, $payment->fresh()];
    }

    private function sendCallback(Payment $payment, array $changes = [], bool $signed = true)
    {
        $payload = array_replace(['reference' => $payment->gateway_reference, 'merchant_ref' => $payment->invoice_number,
            'payment_method_code' => $payment->method, 'total_amount' => $payment->total, 'fee_customer' => $payment->fee,
            'is_closed_payment' => 1, 'status' => 'PAID', 'paid_at' => $payment->created_at->timestamp], $changes);
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        return $this->call('POST', '/api/payments/tripay/callback', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CALLBACK_EVENT' => 'payment_status', 'HTTP_X_CALLBACK_SIGNATURE' => $signed ? hash_hmac('sha256', $raw, 'test-private-key') : str_repeat('0', 64),
        ], $raw);
    }

    public function test_sandbox_quote_create_uses_server_totals_signature_and_encrypted_email(): void
    {
        [$order, $payment] = $this->readyPayment();
        $this->assertSame('tripay_sandbox', $payment->provider);
        $this->assertSame(15500, $payment->total);
        $this->assertSame('READY', $payment->gateway_state);
        $this->assertSame('UNPAID', $payment->status);
        $this->assertNotSame('customer@example.test', DB::table('payments')->value('customer_email'));
        $this->assertNotSame($payment->checkout_url, DB::table('payments')->value('checkout_url'));
        Http::assertSent(fn (ClientRequest $request) => str_ends_with($request->url(), '/api-sandbox/transaction/create')
            && $request['amount'] === 15000 && $request['merchant_ref'] === $payment->invoice_number
            && $request['signature'] === hash_hmac('sha256', 'TTEST'.$payment->invoice_number.'15000', 'test-private-key'));
        $this->get('/orders/'.$order->public_id)->assertOk()->assertSee('Buka pembayaran sandbox')->assertDontSee('<script>alert(1)</script>', false);
        $this->post($this->endpoint($order, 'start'), ['quote_id' => $payment->quote_id])->assertConflict();
        Http::assertSentCount(3);
    }

    public function test_signed_callback_marks_paid_is_idempotent_and_cannot_downgrade(): void
    {
        [$order, $payment] = $this->readyPayment();
        $this->sendCallback($payment)->assertOk()->assertJson(['success' => true]);
        $this->sendCallback($payment)->assertOk();
        $this->assertSame('PAID', $payment->fresh()->status);
        $this->assertSame(1, DB::table('payment_events')->where('source', 'webhook')->count());
        $this->sendCallback($payment, ['status' => 'EXPIRED', 'paid_at' => null])->assertOk();
        $this->assertSame('PAID', $payment->fresh()->status);
        $this->get('/orders/'.$order->public_id)->assertOk()->assertSee('Pembayaran berhasil')->assertSee('di sandbox')->assertSee('DM belum dikirim');
    }

    public function test_invalid_signature_amount_reference_fee_and_method_are_rejected(): void
    {
        [$order, $payment] = $this->readyPayment();
        $this->sendCallback($payment, [], false)->assertForbidden();
        foreach ([['total_amount' => 1], ['reference' => 'DIFFERENT'], ['fee_customer' => 0], ['payment_method_code' => 'BCAVA']] as $change) {
            $this->sendCallback($payment, $change)->assertConflict();
        }
        $this->sendCallback($payment, ['is_closed_payment' => 0])->assertUnprocessable();
        $this->sendCallback($payment, ['status' => 'INVALID'])->assertUnprocessable();
        $this->assertSame('UNPAID', $payment->fresh()->status);
    }

    public function test_refund_cannot_be_reversed_by_later_paid_callback(): void
    {
        [$order, $payment] = $this->readyPayment();
        $this->sendCallback($payment)->assertOk();
        $this->sendCallback($payment, ['status' => 'REFUND'])->assertOk();
        $this->assertSame('REFUND', $payment->fresh()->status);
        $this->sendCallback($payment)->assertOk();
        $this->assertSame('REFUND', $payment->fresh()->status);
    }

    public function test_sandbox_has_no_client_success_simulator_and_return_url_is_not_proof(): void
    {
        [$order, $payment] = $this->readyPayment();
        $this->post($this->endpoint($order, 'simulate'), ['outcome' => 'PAID'])->assertForbidden();
        $this->get('/orders/'.$order->public_id.'?status=PAID')->assertOk();
        $this->assertSame('UNPAID', $payment->fresh()->status);
    }

    public function test_untrusted_checkout_url_is_not_exposed_and_create_is_not_retried(): void
    {
        $order = $this->makeOrder();
        $this->fakeGateway($order->payment, ['checkout_url' => 'https://attacker.test/steal']);
        $this->post($this->endpoint($order, 'method'), ['method' => 'QRIS', 'email' => 'customer@example.test'])->assertStatus(303);
        $payment = $order->payment->fresh();
        $this->post($this->endpoint($order, 'start'), ['quote_id' => $payment->quote_id])->assertSessionHasErrors('payment');
        $this->assertSame('UNKNOWN', $payment->fresh()->gateway_state);
        $this->assertNull($payment->fresh()->checkout_url);
        $this->post($this->endpoint($order, 'start'), ['quote_id' => $payment->quote_id])->assertConflict();
        $this->get('/orders/'.$order->public_id)->assertDontSee('https://attacker.test');
        Http::assertSentCount(3);
    }

    public function test_gateway_response_with_changed_fee_is_rejected(): void
    {
        $order = $this->makeOrder();
        $this->fakeGateway($order->payment, ['amount' => 16000, 'fee_customer' => 1000]);
        $this->post($this->endpoint($order, 'method'), ['method' => 'QRIS', 'email' => 'customer@example.test'])->assertStatus(303);
        $payment = $order->payment->fresh();
        $this->post($this->endpoint($order, 'start'), ['quote_id' => $payment->quote_id])->assertSessionHasErrors('payment');
        $this->assertSame('UNKNOWN', $payment->fresh()->gateway_state);
        $this->assertSame('UNPAID', $payment->fresh()->status);
        $this->assertSame(15500, $payment->fresh()->total);
    }

    public function test_missing_credentials_show_safe_error_without_creating_gateway_request(): void
    {
        $order = $this->makeOrder();
        config(['payments.tripay.api_key' => '']);
        Http::fake();
        $this->get('/orders/'.$order->public_id)->assertOk()->assertSee('Kredensial Tripay sandbox belum diatur');
        Http::assertNothingSent();
    }

    public function test_status_poll_does_not_query_gateway(): void
    {
        [$order, $payment] = $this->readyPayment();
        Http::fake();
        $this->getJson($this->endpoint($order, 'status'))->assertOk()->assertJsonPath('status', 'UNPAID');
        Http::assertNothingSent();
    }

    public function test_timeout_is_reconciled_by_invoice_reference_without_creating_again(): void
    {
        $order = $this->makeOrder();
        $this->fakeGateway($order->payment);
        $this->post($this->endpoint($order, 'method'), ['method' => 'QRIS', 'email' => 'customer@example.test'])->assertStatus(303);
        $payment = $order->payment->fresh();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake([
            '*transaction/create' => Http::failedConnection(),
            '*merchant/transactions*' => Http::response(['success' => true, 'data' => [['reference' => 'TTESTREF123456', 'merchant_ref' => $payment->invoice_number]]]),
            '*transaction/detail*' => Http::response(['success' => true, 'data' => $this->gatewayData($payment, ['status' => 'PAID', 'paid_at' => $payment->created_at->timestamp])]),
        ]);
        $this->post($this->endpoint($order, 'start'), ['quote_id' => $payment->quote_id])->assertSessionHasErrors('payment');
        $this->assertSame('UNKNOWN', $payment->fresh()->gateway_state);
        $this->post($this->endpoint($order, 'start'), ['quote_id' => $payment->quote_id])->assertConflict();
        $this->post($this->endpoint($order, 'refresh'))->assertStatus(303);
        $this->assertSame('READY', $payment->fresh()->gateway_state);
        $this->assertSame('PAID', $payment->fresh()->status);
        $this->assertSame(1, Http::recorded(fn ($request) => str_ends_with($request->url(), '/transaction/create'))->count());
    }

    public function test_stale_quote_cannot_start_a_different_method_or_amount(): void
    {
        $order = $this->makeOrder();
        $this->fakeGateway($order->payment);
        $this->post($this->endpoint($order, 'method'), ['method' => 'QRIS', 'email' => 'customer@example.test'])->assertStatus(303);
        $oldQuote = $order->payment->fresh()->quote_id;
        $this->post($this->endpoint($order, 'method'), ['method' => 'QRIS', 'email' => 'other@example.test'])->assertStatus(303);
        $this->post($this->endpoint($order, 'start'), ['quote_id' => $oldQuote])->assertConflict();
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/transaction/create'));
    }

    public function test_authenticated_api_callback_works_without_browser_csrf_but_early_callback_waits(): void
    {
        [$order, $payment] = $this->readyPayment();
        $this->app->instance('env', 'local');
        $payment->update(['gateway_reference' => null]);
        $this->sendCallback($payment, ['reference' => 'TTESTREF123456'])->assertConflict();
        $payment->update(['gateway_reference' => 'TTESTREF123456']);
        $this->sendCallback($payment)->assertOk();
        $this->assertSame('PAID', $payment->fresh()->status);
    }

    public function test_https_checkout_host_allowlist_rejects_lookalikes_and_credentials(): void
    {
        foreach (['javascript:alert(1)', 'https://tripay.co.id.attacker.test/checkout/x', 'http://tripay.co.id/checkout/x', 'https://user@tripay.co.id/checkout/x', 'https://tripay.co.id:8080/checkout/x'] as $url) {
            $this->assertFalse(TripayGateway::safeCheckoutUrl($url));
        }
        $this->assertTrue(TripayGateway::safeCheckoutUrl('https://payment.tripay.co.id/checkout/T123456'));
    }

    public function test_reconciliation_command_recovers_missed_paid_callback_without_new_payment(): void
    {
        [$order, $payment] = $this->readyPayment();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(['*transaction/detail*' => Http::response(['success' => true, 'data' => $this->gatewayData($payment,
            ['status' => 'PAID', 'paid_at' => $payment->created_at->timestamp])])]);
        $this->artisan('payments:reconcile')->expectsOutputToContain('Tidak ada pembayaran baru dibuat')->assertSuccessful();
        $this->assertSame('PAID', $payment->fresh()->status);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/transaction/create'));
    }

    public function test_gateway_callback_cannot_pay_a_demo_invoice_and_empty_secret_is_rejected(): void
    {
        config(['store.mode' => 'demo']);
        $order = $this->makeOrder();
        config(['store.mode' => 'sandbox']);
        $this->sendCallback($order->payment, ['reference' => 'TTESTREF123456', 'payment_method_code' => 'QRIS'])->assertNotFound();
        $this->assertSame('UNPAID', $order->payment->fresh()->status);
        config(['payments.tripay.private_key' => '']);
        $this->sendCallback($order->payment, ['reference' => 'TTESTREF123456', 'payment_method_code' => 'QRIS'])->assertServiceUnavailable();
    }
}
