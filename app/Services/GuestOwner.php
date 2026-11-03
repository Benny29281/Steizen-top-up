<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GuestOwner
{
    public function hash(Request $request): string
    {
        if (!$request->session()->has('order_owner')) {
            $request->session()->put('order_owner', Str::random(64));
        }
        return hash('sha256', $request->session()->get('order_owner'));
    }
}
