<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'create_game_id' => ['required', 'integer', Rule::exists('games', 'id')],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'create_price' => ['required', 'integer', 'min:1', 'max:100000000'],
            'create_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['create_game_id' => 'game', 'quantity' => 'jumlah nominal', 'create_price' => 'harga jual', 'create_active' => 'status nominal'];
    }
}
