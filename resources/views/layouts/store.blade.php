<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="theme-color" content="#101725">
    <meta name="description" content="Jelajahi katalog game Steizen Store. Pilih game, isi akun, dan tentukan nominal top up dalam satu tempat.">
    <title>@yield('title', 'Steizen Store — Top Up Game')</title>
    <link rel="icon" href="{{ asset('foto/12.png') }}">
    <link rel="stylesheet" href="{{ asset('store.css') }}?v={{ filemtime(public_path('store.css')) }}">
    <script src="{{ asset('store.js') }}?v={{ filemtime(public_path('store.js')) }}" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">Lewati ke konten</a>
<header class="site-header">
    <div class="topbar">
        <a class="brand" href="{{ route('store.index') }}"><img class="brand-logo" src="{{ asset('foto/liby.png') }}" alt="Steizen Store — Beranda" width="220" height="72" decoding="async"></a>
        <form class="header-search" action="{{ route('store.index') }}" method="get" role="search">
            @include('partials.icon', ['name' => 'search'])
            <label class="sr-only" for="site-search">Cari game favoritmu</label>
            <input id="site-search" name="q" type="search" maxlength="80" placeholder="Cari game favoritmu..." value="{{ is_string(request('q')) ? request('q') : '' }}">
            <button type="submit" aria-label="Cari game">Cari</button>
        </form>
        <div class="header-actions"><span class="locale-label"><span class="flag-id" aria-hidden="true"></span>ID / IDR</span>
        @auth
            @if(auth()->user()->is_admin)<a class="account-link" href="{{ route('admin.index') }}">@include('partials.icon', ['name' => 'user'])<span>Dashboard</span></a>@endif
            <form action="{{ route('logout') }}" method="post">@csrf<button class="text-button" type="submit">Keluar</button></form>
        @else
            <a class="account-link" href="{{ route('login') }}">@include('partials.icon', ['name' => 'user'])<span>Admin</span></a>
        @endauth
        </div>
    </div>
    <div class="navigation-row"><nav class="main-nav" aria-label="Navigasi utama">
        <a class="{{ request()->routeIs('store.index', 'games.show') ? 'is-active' : '' }}" href="{{ route('store.index') }}#games" @if(request()->routeIs('store.index', 'games.show')) aria-current="page" @endif>@include('partials.icon', ['name' => 'gamepad'])Top up game</a>
        <a class="{{ request()->routeIs('orders.*') ? 'is-active' : '' }}" href="{{ route('orders.index') }}" @if(request()->routeIs('orders.*')) aria-current="page" @endif>@include('partials.icon', ['name' => 'receipt'])Pesanan saya</a>
        <a href="{{ route('store.index') }}#cara-topup">@include('partials.icon', ['name' => 'grid'])Cara top up</a>
        <a href="{{ route('store.index') }}#bantuan">@include('partials.icon', ['name' => 'shield'])Panduan</a>
    </nav><span class="nav-caption">YOUR GAME. YOUR NEXT LEVEL.</span></div>
</header>
<main id="main-content">
    <div class="notice demo-notice">@include('partials.icon', ['name' => 'shield'])<span><strong>Mode uji coba.</strong> Tidak ada pembayaran riil atau pengiriman item. {{ config('store.catalog_source') === 'demo' ? 'Nominal dan harga adalah contoh.' : 'Katalog supplier aktif; checkout tetap simulasi.' }}</span></div>
    @if(session('status'))<p class="notice flash" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="error" role="alert"><strong>Periksa data berikut:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
<footer class="site-footer">
    <div class="footer-grid"><div class="footer-about"><a class="brand" href="{{ route('store.index') }}"><img class="brand-logo brand-logo-footer" src="{{ asset('foto/liby.png') }}" alt="Steizen Store — Beranda" width="200" height="64" loading="lazy" decoding="async"></a><p>Tempat singgah sebelum naik level.<br>Berbagai dunia game, satu tempat untuk memulai.</p><span class="footer-tag">Dibuat untuk para pemain.</span></div>
    <div><h2>Jelajahi</h2><a href="{{ route('store.index') }}#games">Katalog game</a><a href="{{ route('orders.index') }}">Pesanan saya</a><a href="{{ route('store.index') }}#cara-topup">Cara top up</a></div>
    <div><h2>Informasi</h2><a href="{{ route('store.index') }}#bantuan">Pertanyaan umum</a><a href="{{ route('login') }}">Area admin</a><p>Indonesia · IDR</p></div>
    <div class="footer-status">@include('partials.icon', ['name' => 'shield'])<h2>Periksa sebelum lanjut.</h2><p>Pastikan ID akun, server, dan nominal yang kamu pilih sudah sesuai.</p></div></div>
    <div class="footer-bottom"><span>© {{ now()->year }} Steizen Store. Versi pengembangan.</span><span>Mode simulasi · Tanpa pembayaran riil</span></div>
</footer>
</body>
</html>
