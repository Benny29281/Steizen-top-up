<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Order;
use App\Models\Product;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.mode' => 'demo', 'store.catalog_source' => 'demo']);
        $this->seed(StoreSeeder::class);
    }

    private function payload(string $slug = 'free-fire'): array
    {
        $game = Game::where('slug', $slug)->firstOrFail();
        $account = ['user_id' => $slug === 'valorant' ? 'Player#123' : '12345678'];
        if (isset($game->account_fields['server_id'])) $account['server_id'] = $game->servers[0] ?? '1234';
        return ['request_id' => (string) Str::uuid(), 'product_id' => $game->products()->where('source', 'demo')->first()->id, 'account' => $account, 'whatsapp' => '081234567890'];
    }

    public function test_catalog_and_all_game_forms_render_without_inline_scripts(): void
    {
        $this->get('/')->assertOk()->assertSee('Main lebih jauh.')->assertHeader('X-Frame-Options', 'DENY');
        foreach (Game::all() as $game) $this->get('/games/'.$game->slug)->assertOk()->assertSee($game->name)->assertSee('name="_token"', false);
        $this->get('/games/free-fire')->assertDontSee('name="account[server_id]"', false);
        $this->get('/games/mobile-legends')->assertSee('name="account[server_id]"', false);
        $this->get('/games/genshin-impact')->assertSee('<select', false);
    }

    public function test_search_and_malformed_query(): void
    {
        $this->get('/?q=Free')->assertOk()->assertSee('Free Fire')->assertDontSee('Mobile Legends');
        $this->get('/?q=unknown')->assertOk()->assertSee('Tidak ada game yang cocok');
        $this->getJson('/?q[]=invalid')->assertUnprocessable();
    }

    public function test_category_filters_combine_with_search_and_validate_input(): void
    {
        $this->get('/?category=RPG')->assertOk()->assertSee('Genshin Impact')->assertSee('Honkai: Star Rail')->assertDontSee('Mobile Legends');
        $this->get('/?category=RPG&q=Impact')->assertOk()->assertSee('Genshin Impact')->assertDontSee('Honkai: Star Rail');
        $this->get('/?category=FPS&q=Free')->assertOk()->assertSee('Tidak ada game yang cocok');
        $this->getJson('/?category=unknown')->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->getJson('/?category[]=RPG')->assertUnprocessable()->assertJsonValidationErrors('category');
    }

    public function test_order_price_status_and_ownership_come_from_server(): void
    {
        $input = $this->payload() + ['price' => 1, 'status' => 'PAID', 'owner_hash' => 'attacker', 'fee' => -100];
        $response = $this->post('/games/free-fire/orders', $input)->assertStatus(303);
        $order = Order::firstOrFail();
        $this->assertSame(15000, $order->price);
        $this->assertSame(0, $order->fee);
        $this->assertSame('SIMULASI', $order->status);
        $this->assertNotSame('attacker', $order->owner_hash);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee($order->reference)->assertSee('081234567890');
    }

    public function test_sensitive_order_data_is_encrypted_at_rest_and_hidden_in_json(): void
    {
        $this->post('/games/free-fire/orders', $this->payload())->assertStatus(303);
        $raw = DB::table('orders')->first();
        $this->assertStringNotContainsString('081234567890', $raw->whatsapp);
        $this->assertStringNotContainsString('12345678', $raw->account);
        $serialized = Order::first()->toArray();
        foreach (['account', 'whatsapp', 'owner_hash', 'request_key'] as $field) $this->assertArrayNotHasKey($field, $serialized);
    }

    public function test_duplicate_request_returns_one_persisted_order(): void
    {
        $input = $this->payload();
        $first = $this->post('/games/free-fire/orders', $input)->assertStatus(303);
        $second = $this->post('/games/free-fire/orders', $input)->assertStatus(303);
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_a_different_session_cannot_read_orders_or_reuse_the_ownership(): void
    {
        $this->post('/games/free-fire/orders', $this->payload())->assertStatus(303);
        $order = Order::firstOrFail();
        $this->withSession(['order_owner' => Str::random(64)])->get('/orders/'.$order->public_id)->assertNotFound();
        $this->get('/orders')->assertOk()->assertDontSee($order->reference);
    }

    public function test_cross_game_product_is_rejected(): void
    {
        $payload = $this->payload();
        $payload['product_id'] = $this->payload('mobile-legends')['product_id'];
        $this->postJson('/games/free-fire/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_inactive_product_and_disabled_game_are_rejected(): void
    {
        $payload = $this->payload();
        Product::find($payload['product_id'])->update(['active' => false]);
        $this->postJson('/games/free-fire/orders', $payload)->assertUnprocessable();
        Game::where('slug', 'free-fire')->update(['active' => false]);
        $this->get('/games/free-fire')->assertNotFound();
        $this->postJson('/games/free-fire/orders', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_required_zone_and_server_allowlist_are_enforced(): void
    {
        $payload = $this->payload('mobile-legends');
        unset($payload['account']['server_id']);
        $this->postJson('/games/mobile-legends/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('account.server_id');
        $payload = $this->payload('genshin-impact');
        $payload['account']['server_id'] = 'invalid-server';
        $this->postJson('/games/genshin-impact/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('account.server_id');
    }

    public function test_unexpected_account_fields_arrays_and_html_are_rejected(): void
    {
        $payload = $this->payload();
        $payload['account']['password'] = 'must-not-store';
        $this->postJson('/games/free-fire/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('account');
        $payload = $this->payload(); $payload['account']['user_id'] = ['123'];
        $this->postJson('/games/free-fire/orders', $payload)->assertUnprocessable();
        $payload = $this->payload(); $payload['account']['user_id'] = '<script>alert(1)</script>';
        $this->postJson('/games/free-fire/orders', $payload)->assertUnprocessable();
        $payload = $this->payload(); $payload['whatsapp'] = 'invalid';
        $this->postJson('/games/free-fire/orders', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_valid_riot_id_and_server_games_can_create_orders(): void
    {
        foreach (['valorant', 'genshin-impact', 'mobile-legends'] as $slug) $this->post('/games/'.$slug.'/orders', $this->payload($slug))->assertStatus(303);
        $this->assertDatabaseCount('orders', 3);
    }

    public function test_invalid_html_form_arrays_can_be_rendered_again_without_errors(): void
    {
        $payload = $this->payload();
        $payload['account']['user_id'] = ['invalid'];
        $payload['whatsapp'] = ['invalid'];
        $payload['product_id'] = ['invalid'];
        $payload['request_id'] = ['invalid'];
        $this->from('/games/free-fire')->post('/games/free-fire/orders', $payload)->assertRedirect('/games/free-fire');
        $this->get('/games/free-fire')->assertOk()->assertSee('Periksa data berikut');
        $this->from('/login')->post('/login', ['email' => ['invalid'], 'password' => 'wrong'])->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Masuk ke admin.');
    }

    public function test_supplier_catalog_never_falls_back_to_demo_or_stale_prices(): void
    {
        config(['store.catalog_source' => 'digiflazz']);
        $payload = $this->payload();
        $this->get('/games/free-fire')->assertOk()->assertSee('Nominal belum tersedia');
        $this->postJson('/games/free-fire/orders', $payload)->assertUnprocessable();
        $supplier = Product::create(['game_id' => Game::where('slug', 'free-fire')->first()->id, 'source' => 'digiflazz', 'sku' => 'FF-STALE', 'name' => 'Stale', 'price' => 5000, 'active' => true, 'synced_at' => now()->subHours(25)]);
        $payload['product_id'] = $supplier->id;
        $this->postJson('/games/free-fire/orders', $payload)->assertUnprocessable();
        $supplier->update(['synced_at' => now()]);
        $this->postJson('/games/free-fire/orders', $payload)->assertStatus(303);
    }

    public function test_non_demo_checkout_is_closed(): void
    {
        config(['store.mode' => 'live']);
        $this->postJson('/games/free-fire/orders', $this->payload())->assertServiceUnavailable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_csrf_is_enforced_outside_test_bypass(): void
    {
        $this->app->instance('env', 'local');
        $this->withSession(['_token' => 'valid-token'])->post('/games/free-fire/orders', $this->payload())->assertStatus(419);
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'fake'])->assertStatus(419);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_creation_is_rate_limited(): void
    {
        $payload = $this->payload();
        for ($i = 0; $i < 10; $i++) $this->post('/games/free-fire/orders', $payload)->assertStatus(303);
        $this->post('/games/free-fire/orders', $payload)->assertTooManyRequests();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_headers_block_inline_resources_and_public_source_paths_are_missing(): void
    {
        $response = $this->get('/')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        foreach (['/.env', '/submit.php', '/callback.php', '/tambah.php', '/login.php', '/storage/app/private/legacy-20261102/db.php'] as $path) {
            $this->assertContains($this->get($path)->status(), [403, 404]);
        }
    }
}
