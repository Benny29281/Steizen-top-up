<?php

namespace App\Http\Requests;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Game $game */
        $game = $this->route('game');
        $accountRules = [];
        foreach ($game->account_fields as $key => $label) {
            $rules = ['bail', 'required', 'string', 'max:80', 'not_regex:/[<>\x00-\x1F\x7F]/u'];
            if ($key === 'server_id' && $game->servers) $rules[] = Rule::in($game->servers);
            if ($key === 'user_id' && $game->slug !== 'valorant') $rules[] = 'regex:/^[0-9]{3,30}$/D';
            if ($key === 'user_id' && $game->slug === 'valorant') $rules[] = 'regex:/^[\pL\pN _.-]{3,32}#[\pL\pN]{2,10}$/u';
            if ($key === 'server_id' && !$game->servers) $rules[] = 'regex:/^[0-9]{1,10}$/D';
            $accountRules[$key] = $rules;
        }
        return [
            'request_id' => ['required', 'uuid'],
            'product_id' => ['required', 'integer', 'min:1'],
            'whatsapp' => ['required', 'string', 'regex:/^(?:08|628)[0-9]{7,11}$/D'],
            'account' => ['required', 'array:'.implode(',', array_keys($game->account_fields))],
            ...collect($accountRules)->mapWithKeys(fn ($rules, $key) => ['account.'.$key => $rules])->all(),
        ];
    }

    public function attributes(): array
    {
        $game = $this->route('game');
        return ['product_id' => 'nominal', 'whatsapp' => 'nomor WhatsApp', ...collect($game->account_fields)->mapWithKeys(fn ($label, $key) => ['account.'.$key => $label])->all()];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.', 'string' => ':attribute harus berupa teks.',
            'regex' => 'Format :attribute tidak valid.', 'not_regex' => ':attribute mengandung karakter yang tidak diizinkan.',
            'in' => 'Pilihan :attribute tidak tersedia.', 'product_id.integer' => 'Pilih nominal yang tersedia.',
            'account.array' => 'Kolom akun tidak sesuai dengan game yang dipilih.',
        ];
    }
}
