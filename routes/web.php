<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:catalog')->group(function () {
    Route::get('/', [StoreController::class, 'index'])->name('store.index');
    Route::get('/games/{game:slug}', [StoreController::class, 'show'])->name('games.show');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order:public_id}', [OrderController::class, 'show'])->name('orders.show');
});
Route::post('/games/{game:slug}/orders', [OrderController::class, 'store'])->middleware('throttle:orders')->block(10, 10)->name('orders.store');

Route::prefix('/orders/{order:public_id}/payment')->name('payments.')->group(function () {
    Route::get('/status', [PaymentController::class, 'status'])->middleware('throttle:payment-status')->name('status');
    Route::middleware('throttle:payments')->group(function () {
        Route::post('/method', [PaymentController::class, 'choose'])->block(10, 10)->name('method');
        Route::post('/start', [PaymentController::class, 'start'])->block(10, 10)->name('start');
        Route::post('/refresh', [PaymentController::class, 'refresh'])->block(10, 10)->name('refresh');
        Route::post('/simulate', [PaymentController::class, 'simulate'])->block(10, 10)->name('simulate');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::post('/products', [AdminController::class, 'storeProduct'])->middleware('throttle:admin-write')->name('products.store');
    Route::patch('/products/{product}', [AdminController::class, 'updateProduct'])->middleware('throttle:admin-write')->name('products.update');
    Route::patch('/games/{game:slug}', [AdminController::class, 'updateGame'])->middleware('throttle:admin-write')->name('games.update');
});
