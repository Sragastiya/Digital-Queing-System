@extends('layout')
@section('title','Masuk staf')
@section('content')
<span class="eyebrow pill">RUANG KERJA STAF</span>
<h1>Selamat datang.</h1>
<p class="lead">Masuk untuk mengelola antrean pelanggan.</p>
<form method="post" action="{{ route('login.store') }}">@csrf<label for="email">Email staf</label>
<input id="email" type="email" name="email" autocomplete="username" value="{{ old('email') }}" required autofocus>
<label for="password">Kata sandi</label>
<input id="password" type="password" name="password" autocomplete="current-password" required>
<button class="button full join-button">Masuk ke dasbor</button>
</form>
<p class="fine-print">Akses khusus petugas restoran.</p>
@endsection
