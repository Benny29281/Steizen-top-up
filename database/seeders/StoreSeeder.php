<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Product;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('games') as $slug => $game) {
            // Seeder berulang tidak menimpa perubahan admin pada record yang sudah ada.
            $model = Game::firstOrCreate(['slug' => $slug], [
                'name' => $game['name'], 'short' => $game['short'], 'category' => $game['category'],
                'supplier_brand' => $game['brand'], 'unit' => $game['unit'], 'account_fields' => $game['fields'],
                'servers' => $game['servers'] ?? null, 'active' => true,
            ]);
            foreach ([['small', 60, 15000], ['medium', 300, 70000], ['large', 600, 135000]] as [$size, $amount, $price]) {
                Product::firstOrCreate(['source' => 'demo', 'sku' => "demo-{$slug}-{$size}"], [
                    'game_id' => $model->id, 'name' => $amount.' '.$game['unit'], 'price' => $price, 'active' => true,
                ]);
            }
        }
    }
}
