<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\DigiflazzCatalog;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SupplierCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
        Http::preventStrayRequests();
        config(['store.digiflazz.username' => 'test-user', 'store.digiflazz.key' => 'test-key', 'store.digiflazz.margin_percent' => 10]);
    }

    private function row(array $overrides = []): array
    {
        return array_replace(['brand' => 'FREE FIRE', 'buyer_sku_code' => 'FF60', 'product_name' => '60 Diamonds', 'price' => 10000, 'buyer_product_status' => true, 'seller_product_status' => true, 'unlimited_stock' => true, 'stock' => 0], $overrides);
    }

    public function test_sync_signs_price_request_and_upserts_with_margin(): void
    {
        Http::fake(['api.digiflazz.com/*' => Http::response(['data' => [$this->row()]])]);
        $this->assertSame(1, app(DigiflazzCatalog::class)->sync());
        $this->assertDatabaseHas('products', ['sku' => 'FF60', 'source' => 'digiflazz', 'price' => 11000, 'active' => true]);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.digiflazz.com/v1/price-list' && $request['sign'] === md5('test-usertest-keypricelist'));
        app(DigiflazzCatalog::class)->sync();
        $this->assertSame(1, Product::where('source', 'digiflazz')->count());
    }

    public function test_missing_products_and_out_of_stock_are_disabled(): void
    {
        Http::fakeSequence()->push(['data' => [$this->row()]])->push(['data' => [$this->row(['buyer_sku_code' => 'FF100', 'unlimited_stock' => false, 'stock' => 0])]]);
        app(DigiflazzCatalog::class)->sync();
        app(DigiflazzCatalog::class)->sync();
        $this->assertDatabaseHas('products', ['sku' => 'FF60', 'active' => false]);
        $this->assertDatabaseHas('products', ['sku' => 'FF100', 'active' => false]);
    }

    public function test_supplier_failure_preserves_existing_catalog(): void
    {
        Http::fakeSequence()->push(['data' => [$this->row()]])->push(['message' => 'bad credentials'], 403);
        app(DigiflazzCatalog::class)->sync();
        try { app(DigiflazzCatalog::class)->sync(); $this->fail('Harus menolak respons supplier.'); } catch (RuntimeException $e) { $this->assertStringNotContainsString('test-key', $e->getMessage()); }
        $this->assertDatabaseHas('products', ['sku' => 'FF60', 'price' => 11000, 'active' => true]);
    }

    public function test_duplicate_sku_and_malformed_prices_do_not_commit(): void
    {
        Http::fake(['*' => Http::response(['data' => [$this->row(), $this->row()]])]);
        try { app(DigiflazzCatalog::class)->sync(); $this->fail('Harus menolak SKU ganda.'); } catch (RuntimeException) {}
        $this->assertSame(0, Product::where('source', 'digiflazz')->count());
        Http::fake(['*' => Http::response(['data' => [$this->row(['price' => -1])]])]);
        $this->expectException(RuntimeException::class);
        app(DigiflazzCatalog::class)->sync();
    }

    public function test_missing_credentials_fail_without_requesting_supplier(): void
    {
        Http::fake();
        config(['store.digiflazz.key' => '']);
        $this->artisan('catalog:sync')->expectsOutputToContain('Kredensial Digiflazz belum diatur')->assertFailed();
        Http::assertNothingSent();
    }
}
