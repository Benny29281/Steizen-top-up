<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Game;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StoreSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'password' => 'A-Long-Test-Password42!']);
    }

    public function test_seed_does_not_create_default_admin_or_registration_route(): void
    {
        $this->assertDatabaseCount('users', 0);
        $this->get('/register')->assertNotFound();
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_password_is_hashed_and_admin_can_login(): void
    {
        $admin = $this->admin();
        $this->assertNotSame('A-Long-Test-Password42!', $admin->getRawOriginal('password'));
        $this->assertTrue(Hash::check('A-Long-Test-Password42!', $admin->password));
        $this->post('/login', ['email' => $admin->email, 'password' => 'A-Long-Test-Password42!'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk()->assertSee('Dashboard');
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/logout')->assertMethodNotAllowed();
    }

    public function test_non_admin_cannot_login_to_admin_or_modify_catalog(): void
    {
        $user = User::factory()->create(['password' => 'A-Long-Test-Password42!']);
        $this->post('/login', ['email' => $user->email, 'password' => 'A-Long-Test-Password42!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $product = Product::first();
        $this->patch('/admin/products/'.$product->id, ['price' => 1, 'active' => true])->assertForbidden();
        $this->assertSame(15000, $product->fresh()->price);
    }

    public function test_admin_updates_only_allowed_fields_and_records_audit(): void
    {
        $product = Product::first();
        $this->actingAs($this->admin())->from('/admin')->patch('/admin/products/'.$product->id, ['price' => 20000, 'active' => false, 'sku' => 'HACKED', 'game_id' => 999])->assertRedirect('/admin');
        $this->assertSame(20000, $product->fresh()->price);
        $this->assertFalse($product->fresh()->active);
        $this->assertNotSame('HACKED', $product->fresh()->sku);
        $this->assertDatabaseCount('admin_audit_logs', 1);
        $this->assertSame('product.updated', AdminAuditLog::first()->action);
        $game = Game::first();
        $this->patch('/admin/games/'.$game->slug, ['active' => false])->assertRedirect();
        $this->assertFalse($game->fresh()->active);
        $this->assertDatabaseCount('admin_audit_logs', 2);
    }

    public function test_invalid_price_is_rejected_and_supplier_price_cannot_be_overridden(): void
    {
        $this->actingAs($this->admin());
        $product = Product::first();
        $this->patchJson('/admin/products/'.$product->id, ['price' => -1, 'active' => true])->assertUnprocessable();
        $product->update(['source' => 'digiflazz']);
        $this->patch('/admin/products/'.$product->id, ['price' => 1, 'active' => true])->assertRedirect();
        $this->assertSame(15000, $product->fresh()->price);
    }

    public function test_repeated_login_is_throttled_with_generic_errors(): void
    {
        for ($i = 0; $i < 5; $i++) $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_admin_role_is_not_mass_assignable(): void
    {
        $user = new User(['name' => 'User', 'email' => 'u@example.com', 'password' => 'Example-Password42!', 'is_admin' => true]);
        $user->save();
        $this->assertFalse($user->fresh()->is_admin);
    }

    private function newNominal(): array
    {
        return ['create_game_id' => Game::where('slug', 'mobile-legends')->first()->id, 'quantity' => 86, 'create_price' => 20000, 'create_active' => true];
    }

    public function test_only_admin_can_add_nominals(): void
    {
        $this->post('/admin/products', $this->newNominal())->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->post('/admin/products', $this->newNominal())->assertForbidden();
        $this->assertDatabaseCount('products', 24);
    }

    public function test_admin_adds_a_nominal_with_server_defined_source_and_audit(): void
    {
        $this->actingAs($this->admin())->post('/admin/products', $this->newNominal() + ['source' => 'digiflazz', 'sku' => 'fake-live-sku', 'name' => 'Fake'])->assertRedirect('/admin');
        $product = Product::latest('id')->first();
        $this->assertSame('86 Diamonds', $product->name);
        $this->assertSame('demo', $product->source);
        $this->assertSame(20000, $product->price);
        $this->assertTrue($product->active);
        $this->assertStringStartsWith('demo-custom-', $product->sku);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'product.created', 'entity_id' => $product->id]);
        $this->get('/games/mobile-legends')->assertOk()->assertSee('86 Diamonds');
        $this->get('/admin')->assertOk()->assertSee('Tambah nominal');
    }

    public function test_duplicate_and_invalid_nominals_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $payload = $this->newNominal();
        $this->post('/admin/products', $payload)->assertRedirect();
        $this->postJson('/admin/products', $payload)->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->postJson('/admin/products', array_replace($payload, ['quantity' => 60]))->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->postJson('/admin/products', array_replace($payload, ['create_game_id' => 999, 'quantity' => -1, 'create_price' => 0]))->assertUnprocessable()->assertJsonValidationErrors(['create_game_id', 'quantity', 'create_price']);
        $this->assertDatabaseCount('products', 25);
        $this->assertDatabaseCount('admin_audit_logs', 1);
    }

    public function test_inactive_new_nominal_stays_hidden_and_supplier_mode_does_not_show_manual_products(): void
    {
        $this->actingAs($this->admin())->post('/admin/products', array_replace($this->newNominal(), ['create_active' => false]))->assertRedirect();
        $this->get('/games/mobile-legends')->assertOk()->assertDontSee('86 Diamonds');
        Product::latest('id')->first()->update(['active' => true]);
        config(['store.catalog_source' => 'digiflazz']);
        $this->get('/games/mobile-legends')->assertOk()->assertDontSee('86 Diamonds');
    }
}
