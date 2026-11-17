@extends('layout')
@section('title', 'Masuk staf')
@section('content')
    <span class="eyebrow pill">RUANG KERJA STAF</span>
    <h1>Selamat datang.</h1>
    <p class="lead">Masuk untuk mengelola antrean pelanggan.</p>
    <form method="post" action="{{ route('login.store') }}">
        @csrf
        <label for="email">Email staf</label>
        <input id="email" type="email" name="email" autocomplete="username" value="{{ old('email') }}" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror

        <label for="password">Kata sandi</label>
        <div class="password-field">
            <input id="password" type="password" name="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            <button type="button" class="password-toggle" data-password-toggle="password" aria-controls="password" aria-pressed="false" aria-label="Tampilkan kata sandi">Lihat</button>
        </div>
        @error('password')<p id="password-error" class="field-error">{{ $message }}</p>@enderror
        <button class="button full join-button" data-loading-label="Memeriksa akun…">Masuk ke dasbor</button>
    </form>
    <p class="fine-print">Akses khusus petugas restoran. Hubungi pengelola jika Anda belum memiliki akun.</p>
    <a class="back-link login-back" href="{{ route('home') }}">← Kembali ke halaman pelanggan</a>
@endsection
