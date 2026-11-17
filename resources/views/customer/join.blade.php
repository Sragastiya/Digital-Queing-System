@extends('layout')
@section('title', 'Ambil nomor')
@section('content')
    <h1>Ambil nomor antrean</h1>
    <p class="lead">Pilih layanan, lalu isi data kamu.</p>
    <form action="{{ route('queue.join') }}" method="post" data-queue-registration>
        @csrf
        @include('partials.queue-selector')
        <section class="queue-snapshot" aria-label="Kondisi antrean" aria-live="polite">
            <div data-queue-snapshot="" @if(in_array(old('queue_type'), array_column($queueTypes, 'value'), true)) hidden @endif>
                <h2><img src="{{ asset('design/clock.svg') }}" alt=""> Pilih layanan untuk melihat antrean</h2>
                <div class="snapshot-columns">
                    <div><strong>—</strong><small>sesuai pilihan layanan</small></div>
                    <div><strong>—</strong><small>perkiraan tunggu</small></div>
                </div>
            </div>
            @foreach($queueTypes as $type)
                @php($snapshot = $snapshots[$type->value])
                <div data-queue-snapshot="{{ $type->value }}" @if(old('queue_type') !== $type->value) hidden @endif>
                    <h2><img src="{{ asset('design/clock.svg') }}" alt=""> Antrean {{ $type->label() }}</h2>
                    <div class="snapshot-columns">
                        <div><strong>{{ $snapshot['waiting_count'] }} antrean</strong><small>sedang menunggu</small></div>
                        <div><strong>{{ $snapshot['estimate_minutes'] === null ? 'Belum tersedia' : '± '.$snapshot['estimate_minutes'].' menit' }}</strong><small>perkiraan tunggu</small></div>
                    </div>
                </div>
            @endforeach
        </section>
        @include('partials.fields')
        <button class="button full join-button" data-queue-submit data-loading-label="Mengambil nomor…">Ambil nomor antrean</button>
    </form>
    <p class="fine-print">Setiap layanan memiliki antreannya sendiri. Perkiraan dapat berubah mengikuti layanan.</p>

    <section class="how-it-works" aria-labelledby="how-title">
        <h2 id="how-title">Setelah mendapatkan nomor</h2>
        <ol>
            <li>Simpan halaman tiket untuk memantau giliran.</li>
            <li>Tunjukkan nomor kepada staf saat dipanggil.</li>
            <li>Ikuti arahan staf sesuai layanan yang dipilih.</li>
        </ol>
    </section>
@endsection
