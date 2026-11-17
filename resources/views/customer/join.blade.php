@extends('layout')
@section('title', 'Ambil nomor')
@section('content')
    <span class="eyebrow pill">ANTREAN DIGITAL</span>
    <h1>Antre lebih nyaman.</h1>
    <p class="lead">Ambil nomor sekarang, lalu pantau giliranmu dari halaman tiket.</p>

    <section class="queue-snapshot" aria-label="Kondisi antrean">
        <h2><img src="{{ asset('design/clock.svg') }}" alt=""> Kondisi antrean saat ini</h2>
        <div class="snapshot-columns">
            <div><strong>{{ $snapshot['waiting_count'] }} antrean</strong><small>sedang menunggu</small></div>
            <div><strong>{{ $snapshot['estimate_minutes'] === null ? 'Belum tersedia' : '± '.$snapshot['estimate_minutes'].' menit' }}</strong><small>perkiraan tunggu</small></div>
        </div>
    </section>

    <section class="registration-section" aria-labelledby="registration-title">
        <h2 id="registration-title">Siapa yang ikut antre?</h2>
        <p class="muted section-copy">Isi data rombongan untuk mendapatkan satu nomor antrean.</p>
        <form action="{{ route('queue.join') }}" method="post">
            @csrf
            @include('partials.fields')
            <button class="button full join-button" data-loading-label="Mengambil nomor…">Ambil nomor antrean</button>
        </form>
    </section>
    <p class="fine-print">Nomor antrean belum menjamin meja tersedia. Perkiraan dapat berubah mengikuti layanan.</p>

    <section class="how-it-works" aria-labelledby="how-title">
        <h2 id="how-title">Setelah mendapatkan nomor</h2>
        <ol>
            <li>Simpan halaman tiket untuk memantau giliran.</li>
            <li>Tunjukkan nomor kepada staf saat dipanggil.</li>
            <li>Ikuti arahan staf menuju meja.</li>
        </ol>
    </section>
@endsection
