<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PaymentStatus
{
    public function apply(Payment $payment, string $status, string $source, string $eventKey, ?int $paidAt = null, ?string $payloadHash = null): Payment
    {
        return DB::transaction(function () use ($payment, $status, $source, $eventKey, $paidAt, $payloadHash) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if (PaymentEvent::where('event_key', $eventKey)->exists()) return $locked;
            $from = $locked->status;
            $allowed = match ($from) {
                'UNPAID' => ['UNPAID', 'PAID', 'FAILED', 'EXPIRED', 'REFUND'],
                // Gateway tetap menjadi sumber kebenaran untuk pembayaran sah yang terlambat diberitahukan.
                'EXPIRED', 'FAILED' => ['PAID', 'REFUND'],
                'PAID' => ['REFUND'], default => [],
            };
            if (in_array($status, $allowed, true)) {
                $locked->status = $status;
                if ($status === 'PAID' && !$locked->paid_at) $locked->paid_at = CarbonImmutable::createFromTimestamp($paidAt ?? time());
                $locked->save();
            }
            PaymentEvent::create(['payment_id' => $locked->id, 'event_key' => $eventKey, 'source' => $source,
                'from_status' => $from, 'to_status' => $locked->status, 'payload_hash' => $payloadHash]);
            return $locked;
        }, 3);
    }

    public function expire(Payment $payment): Payment
    {
        if ($payment->status === 'UNPAID' && $payment->expires_at->isPast()) {
            return $this->apply($payment, 'EXPIRED', 'clock', hash('sha256', 'expire:'.$payment->id));
        }
        return $payment;
    }
}
