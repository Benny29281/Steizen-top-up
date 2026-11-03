@extends('layouts.store')
@section('title', $payment->invoice_number.' — Steizen Store')
@section('content')
<section class="invoice-page" @if($payment->status === 'UNPAID') data-payment-poll="{{ route('payments.status', $order) }}" data-payment-status="{{ $payment->status }}" data-gateway-state="{{ $payment->gateway_state }}" @endif>
    <div class="invoice-heading"><div><span class="eyebrow">INVOICE PESANAN</span><h1>{{ $payment->label() }}{{ $payment->status === 'PAID' ? ' ✓' : '' }}</h1><p>{{ $payment->provider === 'demo' ? 'SIMULASI — tidak ada uang yang ditagihkan.' : 'SANDBOX TRIPAY — transaksi uji, bukan pembayaran produksi.' }}</p></div><button class="button button-secondary" type="button" data-print-invoice>Cetak / Simpan PDF</button></div>
    <ol class="invoice-progress" aria-label="Tahapan checkout"><li class="is-done"><span>01</span>Invoice dibuat</li><li class="{{ $payment->status === 'PAID' ? 'is-done' : 'is-current' }}"><span>02</span>Pembayaran</li><li class="{{ $payment->status === 'PAID' ? 'is-done' : '' }}"><span>03</span>Bukti pembayaran</li></ol>
    @if($payment->status === 'PAID')<div class="payment-success" role="status">@include('partials.icon', ['name' => 'check'])<div><strong>Pembayaran berhasil{{ $payment->provider === 'demo' ? ' disimulasikan' : ' di sandbox' }}.</strong><p>Invoice sudah tercatat sebagai lunas dalam mode uji. DM belum dikirim; pengiriman item game belum diaktifkan.</p></div></div>@endif
    @if(in_array($payment->status, ['FAILED', 'EXPIRED', 'REFUND'], true))<div class="notice">{{ $payment->label() }}. {{ $payment->status === 'REFUND' ? 'Pengembalian dicatat sesuai status gateway sandbox.' : 'Jangan membayar invoice ini. Buat pesanan baru bila ingin mencoba kembali.' }}</div>@endif
    <div class="invoice-layout">
        <article class="invoice-sheet" aria-label="Rincian invoice">
            <div class="invoice-brand"><strong>STEIZEN STORE</strong><span class="payment-badge status-{{ strtolower($payment->status) }}">{{ $payment->status === 'UNPAID' ? 'BELUM DIBAYAR' : $payment->status }}</span></div>
            <p class="invoice-watermark">{{ $payment->provider === 'demo' ? 'INVOICE SIMULASI / BUKAN TAGIHAN RIIL' : 'INVOICE SANDBOX / BUKAN TAGIHAN RIIL' }}</p>
            <dl class="invoice-meta"><dt>No. invoice</dt><dd>{{ $payment->invoice_number }}</dd><dt>Referensi pesanan</dt><dd>{{ $order->reference }}</dd><dt>Dibuat</dt><dd>{{ $payment->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</dd><dt>Batas pembayaran</dt><dd>{{ $payment->expires_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</dd>@if($payment->paid_at)<dt>Dibayar</dt><dd>{{ $payment->paid_at->timezone('Asia/Jakarta')->format('d M Y, H:i:s') }} WIB</dd>@endif</dl>
            <h2>Data penerima</h2><dl class="invoice-meta"><dt>Game</dt><dd>{{ $order->game_name }}</dd>@foreach($order->account as $key => $value)<dt>{{ $game->account_fields[$key] ?? $key }}</dt><dd>{{ $value }}</dd>@endforeach<dt>WhatsApp</dt><dd>{{ $order->whatsapp }}</dd><dt>Metode</dt><dd>{{ $payment->method_name ?? 'Belum dipilih' }}</dd></dl>
            <div class="invoice-item"><div><strong>{{ $order->product_name }}</strong><small>{{ $order->game_name }} · 1 produk</small></div><strong>Rp {{ number_format($order->price, 0, ',', '.') }}</strong></div>
            <dl class="invoice-totals"><dt>Harga produk</dt><dd>Rp {{ number_format($order->price, 0, ',', '.') }}</dd><dt>Biaya toko</dt><dd>Rp {{ number_format($order->fee, 0, ',', '.') }}</dd><dt>Biaya pembayaran</dt><dd>{{ $payment->method ? 'Rp '.number_format($payment->fee, 0, ',', '.') : 'Pilih metode dahulu' }}</dd><dt class="invoice-total">{{ !$payment->method ? 'Subtotal' : 'Total '.($payment->status === 'PAID' ? 'dibayar' : 'invoice') }}</dt><dd class="invoice-total">Rp {{ number_format($payment->total, 0, ',', '.') }}</dd></dl>
            <p class="invoice-disclaimer">Pembayaran dan pengiriman adalah dua status berbeda. Invoice uji ini tidak menyatakan DM telah masuk dan bukan faktur pajak.</p>
        </article>
        <aside class="payment-workspace">
            @if($payment->status === 'UNPAID')
                <div class="payment-countdown"><span>Batas waktu pembayaran</span><strong data-payment-countdown="{{ $payment->expires_at->toIso8601String() }}">{{ $payment->expires_at->timezone('Asia/Jakarta')->format('H:i') }} WIB</strong></div>
                @if($gatewayError)<div class="error" role="alert">{{ $gatewayError }}</div>@endif
                @if($payment->gateway_state === 'NOT_CREATED')
                    <h2>1. Pilih metode pembayaran</h2><p class="muted">Biaya dan total akan diperbarui sebelum konfirmasi.</p>
                    @if(count($methods))<form action="{{ route('payments.method', $order) }}" method="post" class="payment-method-form">@csrf<div class="payment-methods">@foreach($methods as $method)<label class="payment-option"><input type="radio" name="method" value="{{ $method['code'] }}" @checked($payment->method === $method['code']) required><span><strong>{{ $method['name'] }}</strong><small>{{ $payment->provider === 'demo' ? 'Contoh metode · biaya Rp 0' : 'Metode merchant sandbox' }}</small></span></label>@endforeach</div>
                    @if($payment->provider === 'tripay_sandbox')<label for="payment-email">Email untuk invoice sandbox<input id="payment-email" type="email" name="email" maxlength="254" autocomplete="email" value="{{ is_string(old('email')) ? old('email') : $payment->customer_email }}" required></label>@endif<button class="button" type="submit">Pilih metode & lihat total</button></form>@endif
                    @if($payment->provider === 'tripay_sandbox' && $payment->method && $payment->quote_id)<div class="payment-confirm"><h2>2. Konfirmasi invoice</h2><p>{{ $payment->method_name }} · Total <strong>Rp {{ number_format($payment->total, 0, ',', '.') }}</strong></p><form action="{{ route('payments.start', $order) }}" method="post">@csrf<input type="hidden" name="quote_id" value="{{ $payment->quote_id }}"><button class="button" type="submit">Buat pembayaran sandbox</button></form></div>@endif
                @elseif(in_array($payment->gateway_state, ['UNKNOWN', 'CREATING'], true))
                    <h2>Menunggu kepastian gateway</h2><p>Permintaan pembayaran mungkin sudah diterima. Klik Cek status gateway untuk memeriksa invoice yang sama. Jangan mengirim pembayaran kedua.</p>
                @elseif($payment->provider === 'demo')
                    <h2>2. Coba pembayaran</h2><p>Metode: <strong>{{ $payment->method_name }}</strong>. Tidak ada QR/rekening riil yang harus dibayar.</p>
                    @if(app()->environment('local', 'testing') && config('store.mode') === 'demo')<div class="demo-payment-controls"><form action="{{ route('payments.simulate', $order) }}" method="post">@csrf<input type="hidden" name="outcome" value="PAID"><button class="button" type="submit">Simulasikan pembayaran berhasil</button></form><form action="{{ route('payments.simulate', $order) }}" method="post">@csrf<input type="hidden" name="outcome" value="FAILED"><button class="button button-secondary" type="submit">Simulasikan gagal</button></form></div>@else<p>Simulator hanya tersedia pada lingkungan lokal/testing dengan mode demo.</p>@endif
                @else
                    <h2>2. Bayar di sandbox Tripay</h2><p>Periksa jumlah dan metode pada halaman sandbox. Jangan menggunakan uang sungguhan.</p>
                    @if($payment->pay_code)<label>Kode bayar sandbox<input readonly value="{{ $payment->pay_code }}" aria-label="Kode bayar sandbox"></label>@endif
                    @if($payment->checkout_url && App\Services\Payments\TripayGateway::safeCheckoutUrl($payment->checkout_url))<a class="button" href="{{ $payment->checkout_url }}" target="_blank" rel="noopener noreferrer">Buka pembayaran sandbox ↗</a>@endif
                    @foreach($payment->instructions ?? [] as $instruction)<details class="payment-instruction"><summary>{{ $instruction['title'] }}</summary><ol>@foreach($instruction['steps'] as $step)<li>{{ $step }}</li>@endforeach</ol></details>@endforeach
                @endif
            @elseif($payment->status === 'PAID')<div class="payment-receipt"><span class="receipt-check">@include('partials.icon', ['name' => 'check'])</span><h2>Terima kasih!</h2><p>Pembayaran {{ $payment->provider === 'demo' ? 'simulasi' : 'sandbox' }} sudah berhasil. Kamu bisa mencetak invoice sebagai bukti uji.</p><p class="muted">Pengiriman DM: <strong>belum diaktifkan.</strong></p></div>
            @else<h2>{{ $payment->label() }}</h2><p>Kamu dapat kembali ke katalog untuk mencoba pesanan baru.</p>@endif
            @if($payment->provider === 'tripay_sandbox' && $payment->gateway_state !== 'NOT_CREATED')<form class="payment-refresh" action="{{ route('payments.refresh', $order) }}" method="post">@csrf<button class="button button-secondary" type="submit">Cek status gateway</button></form>@endif
            <p class="payment-safety">@include('partials.icon', ['name' => 'shield'])Status lunas ditentukan server. Tombol kembali dari gateway tidak mengubah status pembayaran.</p>
        </aside>
    </div>
    <div class="invoice-actions"><a class="subtle-link" href="{{ route('orders.index') }}">← Semua pesanan</a><a class="subtle-link" href="{{ route('store.index') }}#games">Kembali ke katalog →</a></div>
</section>
@endsection
