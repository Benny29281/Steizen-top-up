@extends('layouts.store')
@section('content')
@if($query === '' && $selectedCategory === '')
<section class="hero-grid" aria-label="Pilihan Steizen">
    <div class="hero-banner">
        <img class="hero-backdrop" src="{{ asset('foto/mntap1.webp') }}" alt="" width="1311" height="720" fetchpriority="high" decoding="async">
        <div class="hero-copy"><span class="eyebrow"><span class="status-dot"></span>STEIZEN / PLAY YOUR WAY</span><h1>Main lebih jauh.<br><span>Top up lebih mudah.</span></h1><p>Satu tempat untuk game favoritmu.<br>Pilih nominalmu, siapkan langkah berikutnya.</p><a class="button" href="#games">Temukan gamemu @include('partials.icon', ['name' => 'arrow'])</a><span class="hero-footnote">Katalog multi game · Formulir sesuai akun</span></div>
        <span class="banner-label">MOBILE LEGENDS COLLECTION</span>
    </div>
    <div class="hero-side">
        <a class="diamond-panel" href="#games"><div><span class="eyebrow">PILIH SESUKAMU</span><h2>Nominal kecil.<br>Semangat besar.</h2><span class="panel-link">Jelajahi katalog @include('partials.icon', ['name' => 'arrow'])</span></div><img class="hero-diamond" src="{{ asset('foto/diamond1.jpg') }}" alt="" width="160" height="160" decoding="async"></a>
        <a class="order-panel" href="{{ route('orders.index') }}"><span class="panel-icon">@include('partials.icon', ['name' => 'receipt'])</span><div><span class="eyebrow">SELALU DALAM JANGKAUAN</span><h2>Pesananmu, di sini.</h2><p>Lihat pesanan dan invoice uji kamu.</p></div>@include('partials.icon', ['name' => 'arrow'])</a>
    </div>
</section>
<section class="quick-guide" id="cara-topup" aria-label="Cara top up">
    <div><span class="guide-icon">@include('partials.icon', ['name' => 'gamepad'])</span><div><h2>01. Pilih gamemu</h2><p>Temukan game yang ingin kamu isi.</p></div></div>
    <div><span class="guide-icon">@include('partials.icon', ['name' => 'user'])</span><div><h2>02. Isi akun & nominal</h2><p>Periksa ID dan server dengan teliti.</p></div></div>
    <div><span class="guide-icon">@include('partials.icon', ['name' => 'check'])</span><div><h2>03. Periksa pesanan</h2><p>Buat invoice dan coba pembayaran.</p></div></div>
</section>
@if($featuredGames->isNotEmpty())
<section class="featured-section"><div class="section-heading"><div><span class="eyebrow">MULAI DARI SINI</span><h2>@include('partials.icon', ['name' => 'spark'])Pilihan Steizen</h2></div><a class="subtle-link" href="#games">Semua game @include('partials.icon', ['name' => 'arrow'])</a></div>
<div class="featured-grid">@foreach($featuredGames as $game)<a class="featured-game" href="{{ route('games.show', $game) }}"><span class="featured-art cover-{{ $game->slug }}">@include('partials.game-art', ['game' => $game])</span><span class="featured-info"><small>{{ $game->category }}</small><strong>{{ $game->name }}</strong><span>{{ $game->unit }}</span></span><span class="featured-arrow">@include('partials.icon', ['name' => 'chevron'])</span></a>@endforeach</div></section>
@endif
@endif
<section id="games" class="catalog-section">
    <div class="section-heading"><div><span class="eyebrow">PILIH DUNIAMU</span><h2>Temukan gamemu.</h2><p>{{ $games->count() }} game {{ $query !== '' || $selectedCategory !== '' ? 'cocok dengan pencarianmu' : 'siap kamu jelajahi' }}.</p></div><span class="catalog-label">GAME COLLECTION / {{ str_pad((string) $games->count(), 2, '0', STR_PAD_LEFT) }}</span></div>
    <div class="catalog-toolbar"><nav class="category-filters" aria-label="Kategori game"><a href="{{ route('store.index', array_filter(['q' => $query])) }}#games" class="{{ $selectedCategory === '' ? 'is-active' : '' }}" @if($selectedCategory === '') aria-current="true" @endif>@include('partials.icon', ['name' => 'grid'])Semua game</a>@foreach($categories as $category)<a href="{{ route('store.index', array_filter(['q' => $query, 'category' => $category])) }}#games" class="{{ $selectedCategory === $category ? 'is-active' : '' }}" @if($selectedCategory === $category) aria-current="true" @endif>{{ $category }}</a>@endforeach</nav>
    <form class="search" action="{{ route('store.index') }}#games" method="get" role="search"><label class="sr-only" for="catalog-search">Cari nama game</label>@include('partials.icon', ['name' => 'search'])<input id="catalog-search" name="q" type="search" maxlength="80" placeholder="Cari nama game..." value="{{ $query }}">@if($selectedCategory !== '')<input type="hidden" name="category" value="{{ $selectedCategory }}">@endif<button type="submit">Cari</button></form></div>
    <div class="game-grid">@foreach($games as $game)<a class="game-card" href="{{ route('games.show', $game) }}"><div class="game-cover cover-{{ $game->slug }}">@include('partials.game-art', ['game' => $game])<span class="game-category">{{ $game->category }}</span><span class="cover-action">Pilih game @include('partials.icon', ['name' => 'arrow'])</span></div><div class="game-info"><h3>{{ $game->name }}</h3><span>{{ $game->unit }}<span class="game-card-arrow">@include('partials.icon', ['name' => 'arrow'])</span></span></div></a>@endforeach</div>
    @if($games->isEmpty())<div class="empty"><span class="empty-icon">@include('partials.icon', ['name' => 'search'])</span><h3>Belum ketemu gamenya?</h3><p>Tidak ada game yang cocok. Coba kata kunci atau kategori lain.</p><a class="button button-secondary" href="{{ route('store.index') }}#games">Lihat semua game</a></div>@endif
</section>
<section class="help-section" id="bantuan"><div><span class="eyebrow">SEBELUM MULAI MAIN</span><h2>Hal kecil,<br>yang perlu kamu tahu.</h2><p>Beberapa jawaban untuk langkah pertamamu di Steizen.</p><span class="help-emblem" aria-hidden="true">@include('partials.icon', ['name' => 'shield'])</span></div><div class="faq-list">
    <details><summary>Bagaimana cara membuat pesanan? @include('partials.icon', ['name' => 'chevron'])</summary><p>Pilih game dari katalog, isi data akun yang diminta, pilih nominal, lalu masukkan nomor WhatsApp. Periksa datanya, buat invoice, lalu pilih metode pembayaran. Pada mode demo lokal, gunakan tombol Simulasikan pembayaran berhasil untuk mencoba sampai lunas tanpa uang riil.</p></details>
    <details><summary>Apakah sudah bisa top up sungguhan? @include('partials.icon', ['name' => 'chevron'])</summary><p>Versi ini masih simulasi. Belum ada pembayaran yang ditagihkan atau item game yang dikirim. Nominal dan harga demo hanya untuk mencoba alur website.</p></details>
    <details><summary>Di mana saya melihat pesanan? @include('partials.icon', ['name' => 'chevron'])</summary><p>Buka menu Pesanan saya dari browser dan sesi yang sama saat membuat pesanan. Setelah sesi berakhir, akses pelanggan belum dapat dipulihkan secara mandiri.</p></details>
    <details><summary>Apakah perlu memberikan password game? @include('partials.icon', ['name' => 'chevron'])</summary><p>Tidak. Formulir hanya meminta identitas akun yang sesuai game. Jangan membagikan password, kode OTP, atau kode pemulihan akun.</p></details>
</div></section>
@endsection
