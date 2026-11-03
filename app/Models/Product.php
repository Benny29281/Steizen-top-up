<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = ['game_id', 'source', 'sku', 'name', 'price', 'active', 'synced_at'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'active' => 'boolean', 'synced_at' => 'immutable_datetime'];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function scopeAvailable(Builder $query): void
    {
        $source = config('store.catalog_source');
        $query->where('active', true)->where('price', '>', 0)->where('source', $source);
        if ($source !== 'demo') {
            $query->where('synced_at', '>=', now()->subHours(config('store.catalog_max_age_hours')));
        }
    }
}
