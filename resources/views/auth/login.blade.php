@extends('layouts.store')
@section('title', 'Login admin — Steizen Store')
@section('content')
<section class="login-panel"><span class="eyebrow">AREA PENGELOLA</span><h1>Masuk ke admin.</h1><p class="muted">Gunakan akun admin yang dibuat melalui perintah server.</p><form action="{{ route('login.store') }}" method="post">@csrf<label for="email">Email<input id="email" type="email" name="email" value="{{ is_string(old('email')) ? old('email') : '' }}" maxlength="254" autocomplete="username" required autofocus></label><label for="password">Kata sandi<input id="password" type="password" name="password" maxlength="128" autocomplete="current-password" required></label><button class="button" type="submit">Masuk →</button></form><p class="muted">Tidak ada registrasi admin publik. Hubungi pengelola server jika akses perlu dipulihkan.</p></section>
@endsection
