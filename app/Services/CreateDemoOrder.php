<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateDemoOrder
{
    public function create(Game $game, array $input, string $ownerHash): Order
    {
        abort_unless(in_array(config('store.mode'), ['demo', 'sandbox'], true), 503, 'Checkout produksi belum diaktifkan.');
        $requestKey = hash('sha256', $ownerHash.'|'.$input['request_id']);
        if ($existing = Order::where('request_key', $requestKey)->first()) return $existing;

        try {
            return DB::transaction(function () use ($game, $input, $ownerHash, $requestKey) {
                $freshGame = Game::whereKey($game->id)->where('active', true)->lockForUpdate()->first();
                $product = Product::available()->where('game_id', $game->id)->whereKey($input['product_id'])->lockForUpdate()->first();
                if (!$freshGame || !$product) {
                    throw ValidationException::withMessages(['product_id' => 'Game atau nominal tidak tersedia. Pilih ulang dari katalog.']);
                }
                $order = Order::create([
                    'public_id' => Str::uuid()->toString(), 'reference' => 'STZ-'.strtoupper(Str::random(20)),
                    'owner_hash' => $ownerHash, 'request_key' => $requestKey, 'game_id' => $game->id,
                    'product_id' => $product->id, 'game_name' => $game->name, 'product_name' => $product->name,
                    'account' => $input['account'], 'whatsapp' => $input['whatsapp'],
                    'price' => $product->price, 'fee' => 0, 'status' => 'SIMULASI', 'checkout_mode' => config('store.mode'),
                ]);
                app(\App\Services\Payments\InvoiceService::class)->ensure($order);
                return $order;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // Kunci unik database menangani dua request yang lolos pengecekan awal bersamaan.
            return Order::where('request_key', $requestKey)->first() ?? throw $exception;
        }
    }
}
