<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TripayGateway
{
    public function configured(): bool
    {
        foreach (['api_key', 'private_key', 'merchant_code'] as $key) {
            if (!is_string(config('payments.tripay.'.$key)) || config('payments.tripay.'.$key) === '') return false;
        }
        return true;
    }

    public function channels(int $amount): array
    {
        $cacheKey = 'tripay-sandbox-channels:'.hash('sha256', (string) config('payments.tripay.api_key'));
        $rows = Cache::remember($cacheKey, 300, fn () => $this->request('GET', 'merchant/payment-channel'));
        if (!array_is_list($rows)) throw new GatewayException('Daftar metode pembayaran tidak valid.');
        return collect($rows)->filter(fn ($row) => is_array($row) && ($row['active'] ?? false) === true
            && is_string($row['code'] ?? null) && preg_match('/^[A-Z0-9_]{2,50}$/D', $row['code'])
            && is_string($row['name'] ?? null) && strlen($row['name']) <= 255
            && $amount >= (int) ($row['minimum_amount'] ?? 0) && $amount <= (int) ($row['maximum_amount'] ?? PHP_INT_MAX))
            ->map(fn ($row) => ['code' => $row['code'], 'name' => $row['name']])->values()->all();
    }

    public function fee(string $method, int $amount): int
    {
        $rows = $this->request('GET', 'merchant/fee-calculator', ['code' => $method, 'amount' => $amount]);
        foreach ($rows as $row) {
            if (is_array($row) && ($row['code'] ?? null) === $method && is_int($row['total_fee']['customer'] ?? null)
                && $row['total_fee']['customer'] >= 0 && $row['total_fee']['customer'] <= 10000000) return $row['total_fee']['customer'];
        }
        throw new GatewayException('Biaya metode pembayaran tidak dapat diverifikasi.');
    }

    public function create(Payment $payment): array
    {
        $callback = config('payments.tripay.callback_url');
        if (!is_string($callback) || !filter_var($callback, FILTER_VALIDATE_URL) || parse_url($callback, PHP_URL_SCHEME) !== 'https') {
            throw new GatewayException('URL callback HTTPS publik belum dikonfigurasi.');
        }
        $order = $payment->order;
        return $this->request('POST', 'transaction/create', [
            'method' => $payment->method, 'merchant_ref' => $payment->invoice_number, 'amount' => $payment->amount,
            'customer_name' => 'Pelanggan Steizen', 'customer_email' => $payment->customer_email, 'customer_phone' => $order->whatsapp,
            'order_items' => [['sku' => 'ORDER-'.$order->id, 'name' => $order->game_name.' - '.$order->product_name, 'price' => $payment->amount, 'quantity' => 1]],
            'callback_url' => $callback, 'return_url' => route('orders.show', $order), 'expired_time' => $payment->expires_at->timestamp,
            'signature' => hash_hmac('sha256', config('payments.tripay.merchant_code').$payment->invoice_number.$payment->amount, config('payments.tripay.private_key')),
        ]);
    }

    public function details(Payment $payment): ?array
    {
        $reference = $payment->gateway_reference;
        if (!$reference) {
            $data = $this->request('GET', 'merchant/transactions', ['merchant_ref' => $payment->invoice_number, 'per_page' => 5]);
            $matches = collect($data)->filter(fn ($row) => is_array($row) && ($row['merchant_ref'] ?? null) === $payment->invoice_number);
            if ($matches->isEmpty()) return null;
            if ($matches->count() !== 1) throw new GatewayException('Referensi gateway perlu diperiksa pengelola.');
            $reference = $matches->first()['reference'] ?? null;
        }
        if (!is_string($reference) || !preg_match('/^[A-Za-z0-9_-]{5,100}$/D', $reference)) throw new GatewayException('Referensi gateway tidak valid.');
        return $this->request('GET', 'transaction/detail', ['reference' => $reference]);
    }

    public static function safeCheckoutUrl($url): bool
    {
        return is_string($url) && strlen($url) <= 2000 && filter_var($url, FILTER_VALIDATE_URL)
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), ['tripay.co.id', 'payment.tripay.co.id'], true)
            && !parse_url($url, PHP_URL_USER) && !parse_url($url, PHP_URL_PASS)
            && in_array(parse_url($url, PHP_URL_PORT), [null, 443], true);
    }

    private function request(string $verb, string $path, array $data = []): array
    {
        if (!$this->configured()) throw new GatewayException('Kredensial Tripay sandbox belum diatur.');
        try {
            $client = Http::withToken(config('payments.tripay.api_key'))->acceptJson()->connectTimeout(10)->timeout(25)->withoutRedirecting();
            // POST pembuatan invoice tidak di-retry otomatis: timeout harus direkonsiliasi.
            $response = $verb === 'POST' ? $client->asForm()->post('https://tripay.co.id/api-sandbox/'.$path, $data)
                : $client->get('https://tripay.co.id/api-sandbox/'.$path, $data);
            $body = $response->json();
            if (!$response->successful() || !is_array($body) || ($body['success'] ?? null) !== true || !is_array($body['data'] ?? null)) {
                throw new GatewayException('Respons Tripay sandbox belum dapat diverifikasi.');
            }
            return $body['data'];
        } catch (\Throwable) {
            // Tidak menyertakan exception transport/body yang bisa berisi secret atau data pelanggan.
            throw new GatewayException('Gateway sandbox belum dapat dihubungi atau responsnya tidak valid.');
        }
    }
}
