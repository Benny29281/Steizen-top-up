<?php

use App\Http\Controllers\TripayCallbackController;
use Illuminate\Support\Facades\Route;

// Route stateless khusus gateway; HMAC raw body wajib, bukan endpoint browser.
Route::post('/payments/tripay/callback', TripayCallbackController::class)->middleware('throttle:payment-webhook')->name('payments.tripay.callback');
