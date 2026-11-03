<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Game;
use App\Models\Order;
use App\Services\CreateDemoOrder;
use App\Services\GuestOwner;
use App\Services\Payments\InvoiceService;
use App\Services\Payments\PaymentStatus;
use App\Services\Payments\GatewayException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, Game $game, CreateDemoOrder $service, GuestOwner $owner): RedirectResponse
    {
        $order = $service->create($game, $request->validated(), $owner->hash($request));
        return redirect()->route('orders.show', $order, 303);
    }

    public function index(Request $request, GuestOwner $owner): View
    {
        $orders = Order::with('payment')->where('owner_hash', $owner->hash($request))->latest('id')->paginate(15);
        return view('orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order, GuestOwner $owner, InvoiceService $invoices, PaymentStatus $statuses): View
    {
        abort_unless(hash_equals($order->owner_hash, $owner->hash($request)), 404);
        $game = Game::findOrFail($order->game_id);
        $payment = $statuses->expire($invoices->ensure($order));
        $methods = [];
        $gatewayError = null;
        if ($payment->status === 'UNPAID' && $payment->gateway_state === 'NOT_CREATED') {
            try { $methods = $invoices->methods($payment); }
            catch (GatewayException $error) { $gatewayError = $error->getMessage(); }
        }
        return view('orders.show', compact('order', 'game', 'payment', 'methods', 'gatewayError'));
    }
}
