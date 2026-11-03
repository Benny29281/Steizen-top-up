<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\StoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        parent::tearDown();
    }

    public function test_production_requires_https_and_rejects_untrusted_hosts(): void
    {
        $this->seed(StoreSeeder::class);
        config(['app.url' => 'https://localhost', 'app.debug' => false, 'session.secure' => true, 'session.http_only' => true, 'session.encrypt' => true]);
        $this->app->instance('env', 'production');
        $this->get('http://localhost/')->assertBadRequest();
        $this->get('https://localhost/')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->get('https://localhost.attacker.test/')->assertBadRequest();
    }

    public function test_production_debug_configuration_fails_closed(): void
    {
        $this->app->instance('env', 'production');
        config(['app.debug' => true]);
        $this->expectException(\LogicException::class);
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_admin_command_creates_a_hashed_admin_without_default_credentials(): void
    {
        $password = 'This-Is-Test-Only42!';
        $this->artisan('admin:create')
            ->expectsQuestion('Nama admin', 'Pengelola')
            ->expectsQuestion('Email admin', 'admin@example.test')
            ->expectsQuestion('Kata sandi (minimal 14 karakter, huruf besar/kecil, angka, simbol)', $password)
            ->expectsQuestion('Ulangi kata sandi', $password)
            ->expectsOutputToContain('Admin berhasil dibuat')
            ->assertSuccessful();
        $admin = User::firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertTrue(Hash::check($password, $admin->password));
    }

    public function test_admin_command_rejects_a_weak_password(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Nama admin', 'Pengelola')
            ->expectsQuestion('Email admin', 'admin@example.test')
            ->expectsQuestion('Kata sandi (minimal 14 karakter, huruf besar/kecil, angka, simbol)', 'weak')
            ->expectsQuestion('Ulangi kata sandi', 'weak')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
