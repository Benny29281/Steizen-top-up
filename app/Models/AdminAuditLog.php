<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'entity_type', 'entity_id', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }
}
