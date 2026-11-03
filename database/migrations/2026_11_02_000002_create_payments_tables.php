<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('checkout_mode', 20)->default('demo');
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('invoice_number', 40)->unique();
            $table->string('provider', 30);
            $table->string('status', 20)->default('UNPAID');
            $table->string('gateway_state', 20)->default('NOT_CREATED');
            $table->string('method', 50)->nullable();
            $table->string('method_name')->nullable();
            $table->uuid('quote_id')->nullable();
            $table->string('gateway_reference', 100)->nullable()->unique();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee')->default(0);
            $table->unsignedBigInteger('total');
            $table->text('customer_email')->nullable();
            $table->text('checkout_url')->nullable();
            $table->text('pay_code')->nullable();
            $table->json('instructions')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('event_key', 64)->unique();
            $table->string('source', 30);
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->string('payload_hash', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('checkout_mode'));
    }
};
