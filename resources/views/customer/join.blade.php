@extends('layout')
@section('title','Ambil nomor')
@section('content')
<span class="eyebrow pill">ANTREAN DIGITAL</span>
<h1>Antre lebih nyaman.</h1>
<p class="lead">Ambil nomor sekarang, lalu pantau<br> giliranmu dari mana saja.</p>
<section class="queue-snapshot" aria-label="Kondisi antrean">
<h2>
<img src="{{ asset('design/clock.svg') }}" alt=""> Kondisi antrean saat ini</h2>
<div class="snapshot-columns">
<div>
<strong>{{ $snapshot['waiting_count'] }} antrean</strong>
<small>sedang menunggu</small>
</div>
<div>
<strong>{{ $snapshot['estimate_minutes']===null?'Belum tersedia':'± '.$snapshot['estimate_minutes'].' menit' }}</strong>
<small>perkiraan tunggu</small>
</div>
</div>
</section>
<form action="{{ route('queue.join') }}" method="post">@csrf @include('partials.fields')<button class="button full join-button">Ambil nomor antrean</button>
</form>
<p class="fine-print">Nomor antrean belum menjamin meja tersedia. Perkiraan dapat berubah mengikuti layanan.</p>
@endsection
