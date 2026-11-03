<?php

namespace App\Console\Commands;

use App\Services\DigiflazzCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncCatalog extends Command
{
    protected $signature = 'catalog:sync';
    protected $description = 'Sinkronisasi daftar harga game Digiflazz tanpa membeli item';

    public function handle(DigiflazzCatalog $catalog): int
    {
        try {
            $count = Cache::lock('digiflazz-catalog-sync', 180)->get(fn () => $catalog->sync());
            if ($count === false) {
                $this->warn('Sinkronisasi lain masih berjalan.');
                return self::FAILURE;
            }
            $this->info($count.' produk disinkronkan. Checkout tetap simulasi.');
            return self::SUCCESS;
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        } catch (\Throwable) {
            $this->error('Sinkronisasi gagal. Periksa konfigurasi dan akses database; katalog lama dipertahankan.');
            return self::FAILURE;
        }
    }
}
