<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InvoiceService
{
    public function __construct(private TripayGateway $gateway, private PaymentStatus $statuses) {}

    public function ensure(Order $order): Payment
    {
        // Pesanan lama selalu default demo dan tidak pernah diubah otomatis menjadi sandbox.
        return Payment::firstOrCreate(['order_id' => $order->id], [
            'invoice_number' => 'INV-'.now('Asia/Jakarta')->format('Ymd').'-'.strtoupper(Str::random(18)),
            'provider' => $order->checkout_mode === 'sandbox' ? 'tripay_sandbox' : 'demo',
            'amount' => $order->price + $order->fee, 'fee' => 0, 'total' => $order->price + $order->fee,
            'expires_at' => now()->addMinutes(config('payments.invoice_minutes')),
        ]);
    }

    public function methods(Payment $payment): array
    {
        if ($payment->provider === 'demo') return [
            ['code' => 'DEMO_QRIS', 'name' => 'QRIS — simulasi'],
            ['code' => 'DEMO_VA', 'name' => 'Virtual Account — simulasi'],
            ['code' => 'DEMO_EWALLET', 'name' => 'E-Wallet — simulasi'],
        ];
        if (config('store.mode') !== 'sandbox') throw new GatewayException('Invoice ini memerlukan konfigurasi mode sandbox.');
        return $this->gateway->channels($payment->amount);
    }

    public function choose(Payment $payment, string $method, ?string $email): Payment
    {
        $payment = $this->statuses->expire($payment->fresh());
        abort_unless($payment->status === 'UNPAID' && $payment->gateway_state === 'NOT_CREATED', 409, 'Metode invoice ini sudah dikunci atau invoice kedaluwarsa.');
        $channel = collect($this->methods($payment))->firstWhere('code', $method);
        if (!$channel) throw \Illuminate\Validation\ValidationException::withMessages(['method' => 'Pilih metode pembayaran yang tersedia.']);
        $fee = $payment->provider === 'demo' ? 0 : $this->gateway->fee($method, $payment->amount);
        return DB::transaction(function () use ($payment, $method, $email, $channel, $fee) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'UNPAID' && $locked->gateway_state === 'NOT_CREATED' && $locked->expires_at->isFuture(), 409);
            $locked->update(['method' => $method, 'method_name' => $channel['name'], 'fee' => $fee, 'total' => $locked->amount + $fee,
                'quote_id' => Str::uuid()->toString(), 'customer_email' => $locked->provider === 'demo' ? null : $email,
                'gateway_state' => $locked->provider === 'demo' ? 'READY' : 'NOT_CREATED']);
            return $locked;
        }, 3);
    }

    public function start(Payment $payment, string $quoteId): Payment
    {
        abort_unless($payment->provider === 'tripay_sandbox' && config('store.mode') === 'sandbox', 409);
        if (!$this->gateway->configured()) throw new GatewayException('Kredensial Tripay sandbox belum diatur.');
        $payment = DB::transaction(function () use ($payment, $quoteId) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            // Sekali sebuah permintaan dikirim, tidak ada pembuatan ulang otomatis meski timeout.
            abort_unless($locked->gateway_state === 'NOT_CREATED' && $locked->status === 'UNPAID' && $locked->method
                && $locked->customer_email && $locked->quote_id === $quoteId && $locked->expires_at->isFuture(), 409, 'Invoice telah berubah atau sedang diproses. Muat ulang halaman.');
            $locked->update(['gateway_state' => 'CREATING']);
            return $locked;
        }, 3);
        try {
            $data = $this->gateway->create($payment);
            return $this->attach($payment, $data);
        } catch (\Throwable) {
            Payment::whereKey($payment->id)->where('gateway_state', 'CREATING')->update(['gateway_state' => 'UNKNOWN']);
            throw new GatewayException('Hasil pembuatan pembayaran belum diketahui. Gunakan Cek status gateway; jangan membuat pembayaran ganda.');
        }
    }

    public function refresh(Payment $payment): Payment
    {
        if ($payment->provider === 'demo') return $this->statuses->expire($payment->fresh());
        abort_unless(config('store.mode') === 'sandbox', 409);
        if ($payment->gateway_state === 'NOT_CREATED') return $this->statuses->expire($payment->fresh());
        if (!Cache::add('tripay-check:'.$payment->id, true, 20)) return $payment->fresh();
        $data = $this->gateway->details($payment->fresh());
        if (!$data) throw new GatewayException('Transaksi belum ditemukan oleh gateway. Tunggu, lalu cek kembali; invoice tidak dibuat ulang otomatis.');
        return $this->attach($payment, $data);
    }

    public function attach(Payment $payment, array $data): Payment
    {
        $valid = Validator::make($data, [
            'reference' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{5,100}$/D'],
            'merchant_ref' => ['required', Rule::in([$payment->invoice_number])],
            'payment_method' => ['required', Rule::in([$payment->method])],
            'amount' => ['required', 'integer', Rule::in([$payment->total])],
            'fee_customer' => ['required', 'integer', Rule::in([$payment->fee])],
            'status' => ['required', Rule::in(['UNPAID', 'PAID', 'FAILED', 'EXPIRED', 'REFUND'])],
            'checkout_url' => ['required', 'string', 'max:2000'],
            'expired_time' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required_if:status,PAID', 'nullable', 'integer', 'min:1', 'max:'.(time() + 300)],
        ]);
        if ($valid->fails() || !TripayGateway::safeCheckoutUrl($data['checkout_url'] ?? null)
            || ($payment->gateway_reference && $payment->gateway_reference !== ($data['reference'] ?? null))) {
            throw new GatewayException('Referensi, metode, atau jumlah pembayaran gateway tidak cocok. Hubungi pengelola.');
        }
        return DB::transaction(function () use ($payment, $data) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->gateway_reference && $locked->gateway_reference !== $data['reference']) throw new GatewayException('Referensi pembayaran tidak sesuai.');
            $instructions = [];
            foreach (array_slice(is_array($data['instructions'] ?? null) ? $data['instructions'] : [], 0, 15) as $section) {
                if (!is_array($section) || !is_string($section['title'] ?? null) || !is_array($section['steps'] ?? null)) continue;
                $instructions[] = ['title' => Str::limit(strip_tags($section['title']), 150),
                    'steps' => collect(array_slice($section['steps'], 0, 20))->filter(fn ($step) => is_string($step))->map(fn ($step) => Str::limit(html_entity_decode(strip_tags($step), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 1000))->values()->all()];
            }
            $locked->update(['gateway_state' => 'READY', 'gateway_reference' => $data['reference'], 'checkout_url' => $data['checkout_url'],
                'pay_code' => is_string($data['pay_code'] ?? null) ? Str::limit($data['pay_code'], 150) : null,
                'expires_at' => \Carbon\CarbonImmutable::createFromTimestamp(min($locked->expires_at->timestamp, (int) $data['expired_time'])),
                'instructions' => $instructions, 'checked_at' => now()]);
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            return $this->statuses->apply($locked, $data['status'], 'gateway_api', hash('sha256', 'api:'.$locked->id.':'.$hash),
                isset($data['paid_at']) ? (int) $data['paid_at'] : null, $hash);
        }, 3);
    }

    public function simulate(Payment $payment, string $outcome): Payment
    {
        abort_unless($payment->provider === 'demo' && config('store.mode') === 'demo' && app()->environment('local', 'testing'), 403);
        return DB::transaction(function () use ($payment, $outcome) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === $outcome) return $locked;
            abort_unless(in_array($outcome, ['PAID', 'FAILED'], true) && $locked->status === 'UNPAID'
                && $locked->gateway_state === 'READY' && $locked->expires_at->isFuture(), 409, 'Invoice ini tidak dapat disimulasikan.');
            return $this->statuses->apply($locked, $outcome, 'demo_simulator', hash('sha256', 'demo:'.$locked->id.':'.$outcome), time());
        }, 3);
    }
}
