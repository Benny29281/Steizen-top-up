<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });

        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('short', 12);
            $table->string('category', 50);
            $table->string('supplier_brand');
            $table->string('unit', 60);
            $table->json('account_fields');
            $table->json('servers')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->string('source', 30)->index();
            $table->string('sku');
            $table->string('name');
            $table->unsignedBigInteger('price');
            $table->boolean('active')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['source', 'sku']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('reference', 32)->unique();
            $table->string('owner_hash', 64)->index();
            $table->string('request_key', 64)->unique();
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('game_name');
            $table->string('product_name');
            // Encrypted casts menghasilkan ciphertext yang lebih panjang dari input asli.
            $table->text('account');
            $table->text('whatsapp');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('fee')->default(0);
            $table->string('status', 30)->default('SIMULASI');
            $table->timestamps();
        });

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->json('changes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('games');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
