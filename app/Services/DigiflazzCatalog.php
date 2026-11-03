<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DigiflazzCatalog
{
    public function sync(): int
    {
        $username = config('store.digiflazz.username');
        $key = config('store.digiflazz.key');
        $margin = config('store.digiflazz.margin_percent');
        if (!is_string($username) || $username === '' || !is_string($key) || $key === '') throw new RuntimeException('Kredensial Digiflazz belum diatur pada environment server.');
        if (!is_numeric($margin) || (float) $margin < 0 || (float) $margin > 100) throw new RuntimeException('Margin supplier harus berupa angka 0 sampai 100.');
        try {
            $response = Http::acceptJson()->asJson()->connectTimeout(10)->timeout(30)->withoutRedirecting()->post('https://api.digiflazz.com/v1/price-list', [
                'cmd' => 'prepaid', 'username' => $username, 'sign' => md5($username.$key.'pricelist'),
            ]);
        } catch (\Throwable) {
            throw new RuntimeException('Koneksi supplier gagal. Katalog lama dipertahankan.');
        }
        $rows = $response->json('data');
        if (!$response->successful() || !is_array($rows) || !array_is_list($rows)) throw new RuntimeException('Respons supplier tidak valid. Katalog lama dipertahankan.');
        $brands = Game::all()->keyBy(fn (Game $game) => mb_strtoupper($game->supplier_brand));
        $products = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['brand'] ?? null)) continue;
            $game = $brands->get(mb_strtoupper($row['brand']));
            if (!$game) continue;
            if (!is_string($row['buyer_sku_code'] ?? null) || $row['buyer_sku_code'] === '' || mb_strlen($row['buyer_sku_code']) > 255
                || !is_string($row['product_name'] ?? null) || trim($row['product_name']) === '' || mb_strlen($row['product_name']) > 255
                || !is_numeric($row['price'] ?? null) || (float) $row['price'] <= 0 || (float) $row['price'] > 100000000) {
                throw new RuntimeException('Data produk supplier tidak valid. Katalog lama dipertahankan.');
            }
            $price = (int) ceil((float) $row['price'] * (1 + (float) $margin / 100));
            if ($price > 100000000 || isset($products[$row['buyer_sku_code']])) throw new RuntimeException('SKU ganda atau harga supplier melewati batas. Katalog lama dipertahankan.');
            $active = ($row['buyer_product_status'] ?? false) === true && ($row['seller_product_status'] ?? false) === true
                && (($row['unlimited_stock'] ?? false) === true || (int) ($row['stock'] ?? 0) > 0);
            $products[$row['buyer_sku_code']] = ['game_id' => $game->id, 'name' => $row['product_name'], 'price' => $price, 'active' => $active, 'synced_at' => now()];
        }
        if (!$products) throw new RuntimeException('Tidak ada brand game yang cocok. Katalog lama dipertahankan.');
        DB::transaction(function () use ($products) {
            Product::where('source', 'digiflazz')->update(['active' => false]);
            foreach ($products as $sku => $attributes) Product::updateOrCreate(['source' => 'digiflazz', 'sku' => $sku], $attributes);
        });
        return count($products);
    }
}
