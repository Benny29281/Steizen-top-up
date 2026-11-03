<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payments\PaymentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TripayCallbackController extends Controller
{
    public function __invoke(Request $request, PaymentStatus $statuses): JsonResponse
    {
        $key = config('payments.tripay.private_key');
        abort_unless(config('store.mode') === 'sandbox' && is_string($key) && $key !== '', 503);
        $raw = $request->getContent();
        abort_if(strlen($raw) > 65536, 413);
        $signature = $request->header('X-Callback-Signature', '');
        abort_unless(is_string($signature) && preg_match('/^[a-f0-9]{64}$/D', $signature)
            && hash_equals(hash_hmac('sha256', $raw, $key), $signature), 403);
        abort_unless($request->header('X-Callback-Event') === 'payment_status', 400);
        $data = json_decode($raw, true);
        $validator = Validator::make(is_array($data) ? $data : [], [
            'merchant_ref' => ['required', 'string', 'max:40'], 'reference' => ['required', 'string', 'max:100'],
            'payment_method_code' => ['required', 'string', 'max:50'], 'is_closed_payment' => ['required', 'integer', Rule::in([1])],
            'total_amount' => ['required', 'integer', 'min:1'], 'fee_customer' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['PAID', 'EXPIRED', 'FAILED', 'REFUND'])],
            'paid_at' => ['required_if:status,PAID', 'nullable', 'integer', 'min:1', 'max:'.(time() + 300)],
        ]);
        abort_if($validator->fails(), 422);
        $payment = Payment::where('invoice_number', $data['merchant_ref'])->where('provider', 'tripay_sandbox')->first();
        abort_unless($payment, 404);
        // Callback yang datang sebelum respons create disimpan diminta ulang oleh gateway.
        abort_unless($payment->gateway_reference, 409);
        abort_unless(hash_equals($payment->gateway_reference, $data['reference']) && $payment->method === $data['payment_method_code']
            && $payment->total === (int) $data['total_amount'] && $payment->fee === (int) $data['fee_customer'], 409);
        $hash = hash('sha256', $raw);
        $statuses->apply($payment, $data['status'], 'webhook', hash('sha256', 'webhook:'.$payment->id.':'.$hash),
            isset($data['paid_at']) ? (int) $data['paid_at'] : null, $hash);
        return response()->json(['success' => true]);
    }
}
