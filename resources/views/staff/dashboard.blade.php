@extends('layout',['staff'=>true])
@section('title','Kelola antrean')
@section('content')
<div class="page-heading">
<div>
<h1>Kelola antrean</h1>
<p class="muted">Panggil pelanggan saat tempat tersedia, lalu konfirmasi kedatangannya.</p>
</div>
<div class="date-chip">
<img src="{{ asset('design/calendar.svg') }}" alt="">{{ now()->timezone('Asia/Makassar')->translatedFormat('l, j F Y') }}</div>
</div>
<p class="refresh-state" id="refresh-state" role="status">● Diperbarui otomatis</p>
<div id="staff-board" data-staff-poll="{{ route('staff.poll',request()->only('q','status','page')) }}">@include('staff.partials.board')</div>
<details class="settings-panel">
<summary>Pengaturan perkiraan tunggu</summary>
<p class="muted">Usulan: antrean menunggu di depan × interval panggilan. Kosongkan agar perkiraan tidak ditampilkan. Nilai perlu dikalibrasi dengan kondisi restoran.</p>
<form method="post" action="{{ route('staff.settings') }}">@csrf<label for="estimate_interval_minutes">Interval panggilan (menit, opsional)</label>
<input id="estimate_interval_minutes" name="estimate_interval_minutes" type="number" min="1" max="1440" value="{{ $settings->estimate_interval_minutes }}">
<button class="button">Simpan perkiraan</button>
</form>
</details>
@endsection
