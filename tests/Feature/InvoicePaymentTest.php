<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Order;
use App\Models\Payment;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.mode' => 'demo', 'store.catalog_source' => 'demo']);
        $this->seed(StoreSeeder::class);
    }

    private function makeOrder(): Order
    {
        $game = Game::where('slug', 'free-fire')->firstOrFail();
        $this->get('/games/free-fire')->assertOk();
        $this->post('/games/free-fire/orders', ['request_id' => (string) Str::uuid(), 'product_id' => $game->products()->first()->id,
            'account' => ['user_id' => '12345678'], 'whatsapp' => '081234567890'])->assertStatus(303);
        return Order::latest('id')->firstOrFail();
    }

    private function endpoint(Order $order, string $action): string
    {
        return '/orders/'.$order->public_id.'/payment/'.$action;
    }

    public function test_checkout_creates_one_invoice_and_can_simulate_success_and_print_receipt(): void
    {
        $order = $this->makeOrder();
        $payment = $order->payment;
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('UNPAID', $payment->status);
        $this->get('/orders/'.$order->public_id)->assertOk()->assertSee($payment->invoice_number)->assertSee('Pilih metode pembayaran')->assertSee('Cetak / Simpan PDF');
        $this->post($this->endpoint($order, 'method'), ['method' => 'DEMO_QRIS', 'fee' => 9999, 'total' => 1, 'status' => 'PAID'])->assertStatus(303);
        $this->assertSame(15000, $payment->fresh()->total);
        $this->assertSame(0, $payment->fresh()->fee);
        $this->assertSame('READY', $payment->fresh()->gateway_state);
        $this->get('/orders/'.$order->public_id)->assertSee('Simulasikan pembayaran berhasil');
        $this->post($this->endpoint($order, 'simulate'), ['outcome' => 'PAID'])->assertStatus(303);
        $this->assertSame('PAID', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
        $this->get('/orders/'.$order->public_id)->assertSee('Pembayaran berhasil')->assertSee('DM belum dikirim')->assertSee('BUKAN TAGIHAN RIIL')->assertSee('PAID');
        $this->getJson($this->endpoint($order, 'status'))->assertOk()->assertJsonPath('status', 'PAID')->assertJsonMissingPath('account');
        $this->post($this->endpoint($order, 'simulate'), ['outcome' => 'PAID'])->assertStatus(303);
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_return_query_cannot_mark_paid_and_method_cannot_be_forged(): void
    {
        $order = $this->makeOrder();
        $this->get('/orders/'.$order->public_id.'?status=PAID&success=1')->assertOk();
        $this->assertSame('UNPAID', $order->payment->status);
        $this->postJson($this->endpoint($order, 'method'), ['method' => 'FAKE_BANK'])->assertUnprocessable();
        $this->post($this->endpoint($order, 'simulate'), ['outcome' => 'PAID'])->assertConflict();
    }

    public function test_other_session_cannot_read_status_or_mutate_payment(): void
    {
        $order = $this->makeOrder();
        $this->withSession(['order_owner' => Str::random(64)]);
        foreach (['method', 'start', 'refresh', 'simulate'] as $action) $this->post($this->endpoint($order, $action), [])->assertNotFound();
        $this->getJson($this->endpoint($order, 'status'))->assertNotFound();
        $this->assertSame('UNPAID', $order->payment->status);
    }

    public function test_expiry_prevents_simulator_success_and_method_change(): void
    {
        $order = $this->makeOrder();
        $this->post($this->endpoint($order, 'method'), ['method' => 'DEMO_VA'])->assertStatus(303);
        $this->travel(61)->minutes();
        $this->getJson($this->endpoint($order, 'status'))->assertJsonPath('status', 'EXPIRED');
        $this->post($this->endpoint($order, 'simulate'), ['outcome' => 'PAID'])->assertConflict();
        $this->post($this->endpoint($order, 'method'), ['method' => 'DEMO_QRIS'])->assertConflict();
        $this->assertNull($order->payment->paid_at);
    }

    public function test_demo_simulator_is_disabled_in_production(): void
    {
        $order = $this->makeOrder();
        $this->post($this->endpoint($order, 'method'), ['method' => 'DEMO_VA'])->assertStatus(303);
        config(['app.url' => 'https://localhost', 'app.debug' => false, 'session.secure' => true]);
        $this->app->instance('env', 'production');
        $this->withSession(['_token' => 'csrf'])->post('https://localhost'.$this->endpoint($order, 'simulate'), ['_token' => 'csrf', 'outcome' => 'PAID'])->assertForbidden();
        $this->assertSame('UNPAID', $order->payment->status);
        \Illuminate\Http\Request::setTrustedHosts([]);
    }

    public function test_payment_mutations_require_csrf_and_failure_is_not_success(): void
    {
        $order = $this->makeOrder();
        $this->post($this->endpoint($order, 'method'), ['method' => 'DEMO_EWALLET'])->assertStatus(303);
        $this->post($this->endpoint($order, 'simulate'), ['outcome' => 'FAILED'])->assertStatus(303);
        $this->get('/orders/'.$order->public_id)->assertSee('Pembayaran gagal')->assertDontSee('Pembayaran berhasil');
        $this->assertNull($order->payment->paid_at);
        $this->app->instance('env', 'local');
        $this->post($this->endpoint($order, 'simulate'), ['outcome' => 'PAID'])->assertStatus(419);
        $this->post($this->endpoint($order, 'refresh'))->assertStatus(419);
    }

    public function test_invoice_for_legacy_order_stays_demo_after_mode_change(): void
    {
        $order = $this->makeOrder();
        config(['store.mode' => 'sandbox']);
        $this->get('/orders/'.$order->public_id)->assertOk()->assertSee('INVOICE SIMULASI');
        $this->assertSame('demo', $order->payment->provider);
        $this->assertDatabaseCount('payments', 1);
    }
}
