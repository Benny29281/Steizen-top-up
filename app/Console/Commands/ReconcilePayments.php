<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\InvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--limit=20 : Maksimal invoice per proses (1-100)}';
    protected $description = 'Memeriksa invoice Tripay sandbox tertunda tanpa membuat pembayaran baru';

    public function handle(InvoiceService $invoices): int
    {
        if (config('store.mode') !== 'sandbox') {
            $this->warn('Rekonsiliasi gateway hanya berjalan dalam STORE_MODE=sandbox.');
            return self::SUCCESS;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($limit === false) { $this->error('Limit harus 1 sampai 100.'); return self::FAILURE; }
        $lock = Cache::lock('tripay-payment-reconcile', 3600);
        if (!$lock->get()) { $this->warn('Rekonsiliasi lain masih berjalan.'); return self::SUCCESS; }
        try {
            $payments = Payment::where('provider', 'tripay_sandbox')->whereIn('status', ['UNPAID', 'EXPIRED', 'FAILED'])
                ->whereIn('gateway_state', ['CREATING', 'UNKNOWN', 'READY'])->where('created_at', '>=', now()->subDays(2))
                ->orderBy('checked_at')->orderBy('id')->limit($limit)->get();
            $checked = 0;
            foreach ($payments as $payment) {
                // Rotasi antrean tetap berjalan jika invoice belum ditemukan/gateway bermasalah.
                $payment->update(['checked_at' => now()]);
                try { $invoices->refresh($payment); $checked++; }
                catch (\Throwable) { $this->warn('Invoice '.$payment->invoice_number.' masih perlu diperiksa.'); }
            }
            $this->info($checked.' dari '.$payments->count().' invoice diperiksa. Tidak ada pembayaran baru dibuat.');
            return self::SUCCESS;
        } finally { $lock->release(); }
    }
}
