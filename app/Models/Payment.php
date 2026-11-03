<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['customer_email', 'checkout_url', 'pay_code'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'fee' => 'integer', 'total' => 'integer', 'customer_email' => 'encrypted',
            'checkout_url' => 'encrypted', 'pay_code' => 'encrypted', 'instructions' => 'array',
            'expires_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime', 'checked_at' => 'immutable_datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function label(): string
    {
        return match ($this->status) {
            'PAID' => 'Pembayaran berhasil', 'EXPIRED' => 'Invoice kedaluwarsa',
            'FAILED' => 'Pembayaran gagal', 'REFUND' => 'Pembayaran dikembalikan',
            default => 'Menunggu pembayaran',
        };
    }
}
