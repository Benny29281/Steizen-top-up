<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    // Input HTTP tidak pernah dimasukkan ke model melalui request->all().
    protected $guarded = ['id'];

    protected $hidden = ['owner_hash', 'request_key', 'account', 'whatsapp'];

    protected function casts(): array
    {
        return ['account' => 'encrypted:array', 'whatsapp' => 'encrypted', 'price' => 'integer', 'fee' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
