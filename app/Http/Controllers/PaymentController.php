<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\GuestOwner;
use App\Services\Payments\GatewayException;
use App\Services\Payments\InvoiceService;
use App\Services\Payments\PaymentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    private function owned(Request $request, Order $order, GuestOwner $owner, InvoiceService $invoices): Payment
    {
        abort_unless(hash_equals($order->owner_hash, $owner->hash($request)), 404);
        return $invoices->ensure($order);
    }

    public function choose(Request $request, Order $order, GuestOwner $owner, InvoiceService $invoices): RedirectResponse
    {
        $payment = $this->owned($request, $order, $owner, $invoices);
        $input = $request->validate(['method' => ['required', 'string', 'max:50'],
            'email' => [$payment->provider === 'tripay_sandbox' ? 'required' : 'nullable', 'string', 'email', 'max:254']]);
        try {
            $invoices->choose($payment, $input['method'], $input['email'] ?? null);
        } catch (GatewayException $error) {
            return back()->withErrors(['payment' => $error->getMessage()]);
        }
        return redirect()->route('orders.show', $order, 303)->with('status', 'Metode dipilih. Periksa biaya dan total pada invoice sebelum melanjutkan.');
    }

    public function start(Request $request, Order $order, GuestOwner $owner, InvoiceService $invoices): RedirectResponse
    {
        $payment = $this->owned($request, $order, $owner, $invoices);
        $input = $request->validate(['quote_id' => ['required', 'uuid']]);
        try { $invoices->start($payment, $input['quote_id']); }
        catch (GatewayException $error) { return back()->withErrors(['payment' => $error->getMessage()]); }
        return redirect()->route('orders.show', $order, 303);
    }

    public function refresh(Request $request, Order $order, GuestOwner $owner, InvoiceService $invoices): RedirectResponse
    {
        $payment = $this->owned($request, $order, $owner, $invoices);
        try { $invoices->refresh($payment); }
        catch (GatewayException $error) { return back()->withErrors(['payment' => $error->getMessage()]); }
        return redirect()->route('orders.show', $order, 303)->with('status', 'Status pembayaran sudah diperiksa.');
    }

    public function simulate(Request $request, Order $order, GuestOwner $owner, InvoiceService $invoices): RedirectResponse
    {
        $payment = $this->owned($request, $order, $owner, $invoices);
        $input = $request->validate(['outcome' => ['required', Rule::in(['PAID', 'FAILED'])]]);
        $invoices->simulate($payment, $input['outcome']);
        return redirect()->route('orders.show', $order, 303);
    }

    public function status(Request $request, Order $order, GuestOwner $owner, InvoiceService $invoices, PaymentStatus $statuses): JsonResponse
    {
        $payment = $statuses->expire($this->owned($request, $order, $owner, $invoices));
        // Polling membaca database, tidak memanggil gateway setiap beberapa detik.
        return response()->json(['status' => $payment->status, 'label' => $payment->label(), 'gateway_state' => $payment->gateway_state]);
    }
}
