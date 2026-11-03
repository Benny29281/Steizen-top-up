@extends('layouts.store')
@section('title', $game->name.' — Steizen Store')
@section('content')
<a class="back" href="{{ route('store.index') }}#games">← Semua game</a>
<div class="checkout-layout">
    <aside class="game-intro"><div class="game-cover cover-{{ $game->slug }}">@include('partials.game-art', ['game' => $game])<span class="game-category">{{ $game->category }}</span></div><span class="intro-label">@include('partials.icon', ['name' => 'gamepad'])TOP UP / {{ $game->category }}</span><h1>{{ $game->name }}</h1><p>Isi identitas akun sesuai game. Periksa kembali ID dan server sebelum membuat pesanan.</p><p class="security-note">Jangan berikan kata sandi atau kode OTP akun Anda.</p></aside>
    <form class="checkout" action="{{ route('orders.store', $game) }}" method="post">
        @csrf
        <input type="hidden" name="request_id" value="{{ (string) Illuminate\Support\Str::uuid() }}">
        <fieldset><legend><span class="step">01</span> Data akun</legend><div class="fields">
            @foreach($game->account_fields as $key => $label)
            <label for="account-{{ $key }}">{{ $label }}
                @if($key === 'server_id' && $game->servers)
                    <select id="account-{{ $key }}" name="account[{{ $key }}]" required aria-invalid="{{ $errors->has('account.'.$key) ? 'true' : 'false' }}"><option value="">Pilih server</option>@foreach($game->servers as $server)<option value="{{ $server }}" @selected(old('account.'.$key) === $server)>{{ $server }}</option>@endforeach</select>
                @else
                    <input id="account-{{ $key }}" name="account[{{ $key }}]" value="{{ is_string(old('account.'.$key)) ? old('account.'.$key) : '' }}" maxlength="80" placeholder="Masukkan {{ $label }}" required aria-invalid="{{ $errors->has('account.'.$key) ? 'true' : 'false' }}" @if($game->slug !== 'valorant') inputmode="numeric" @endif>
                @endif
            </label>
            @endforeach
        </div></fieldset>
        <fieldset><legend><span class="step">02</span> Pilih nominal</legend><div class="products">
            @foreach($products as $product)
            <label class="product"><input type="radio" name="product_id" value="{{ $product->id }}" data-price="{{ $product->price }}" @checked(is_scalar(old('product_id')) && (string) old('product_id') === (string) $product->id) required><span>@if($game->unit === 'Diamonds')<img class="product-art" src="{{ asset('foto/diamond1.jpg') }}" alt="" width="46" height="46" loading="lazy" decoding="async">@endif<strong>{{ $product->name }}</strong><small>Rp {{ number_format($product->price, 0, ',', '.') }}</small></span></label>
            @endforeach
        </div>@if($products->isEmpty())<p class="empty">Nominal belum tersedia. Silakan kembali setelah katalog diperbarui.</p>@endif</fieldset>
        <fieldset><legend><span class="step">03</span> Kontak dan ringkasan</legend><label for="whatsapp">Nomor WhatsApp<input id="whatsapp" type="tel" name="whatsapp" value="{{ is_string(old('whatsapp')) ? old('whatsapp') : '' }}" placeholder="081234567890" pattern="(08|628)[0-9]{7,11}" maxlength="14" autocomplete="tel" required></label><p class="muted">Nomor untuk catatan pesanan; notifikasi otomatis belum aktif.</p><div class="summary"><span>Harga produk · biaya metode dipilih pada invoice</span><strong id="total" aria-live="polite">Pilih nominal</strong></div><p>Cek kembali ID dan nominalmu. Rincian pesanan uji dapat dilihat melalui menu Pesanan saya pada sesi browser yang sama.</p><button class="button" type="submit" @disabled($products->isEmpty())>Buat invoice uji @include('partials.icon', ['name' => 'arrow'])</button></fieldset>
    </form>
</div>
@endsection
