@extends('layouts.store')
@section('title', 'Pesanan saya — Steizen Store')
@section('content')
<section class="order-page"><span class="eyebrow">RIWAYAT PESANAN</span><h1>Pesanan saya</h1><p class="muted">Hanya pesanan dari sesi browser ini yang ditampilkan. Data pesanan tetap tersimpan di database.</p>
@forelse($orders as $order)<a class="order-link" href="{{ route('orders.show', $order) }}">{{ $order->game_name }} · {{ $order->product_name }}<small>{{ $order->reference }} · {{ $order->payment?->label() ?? 'Invoice belum dibuka' }} · {{ $order->checkout_mode === 'sandbox' ? 'SANDBOX' : 'SIMULASI' }}</small></a>@empty<p class="empty">Belum ada pesanan. Pilih game untuk memulai.</p>@endforelse
{{ $orders->links('partials.pagination') }}<a class="button" href="{{ route('store.index') }}">Kembali ke katalog →</a></section>
@endsection
