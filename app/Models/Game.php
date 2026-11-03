<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = ['slug', 'name', 'short', 'category', 'supplier_brand', 'unit', 'account_fields', 'servers', 'active'];

    protected function casts(): array
    {
        return ['account_fields' => 'array', 'servers' => 'array', 'active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
