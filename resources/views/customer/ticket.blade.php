@extends('layout')
@section('title', 'Antrean kamu')
@section('content')
@php
    $isTerminal = in_array($ticket->status, [\App\Enums\TicketStatus::Completed, \App\Enums\TicketStatus::Cancelled], true);
    $steps = ['waiting' => 'Menunggu', 'called' => 'Dipanggil', 'arrived' => 'Hadir', 'completed' => 'Selesai'];
    $currentStep = array_search($ticket->status->value, array_keys($steps), true);
@endphp
<div data-ticket-poll="{{ route('ticket.status', ['token' => $token]) }}" data-queue-type="{{ $ticket->queue_type->value }}" data-status="{{ $ticket->status->value }}" data-terminal="{{ $isTerminal ? 'true' : 'false' }}">
    <h1>{{ $ticket->queue_type->label() }}</h1>
    <div class="refresh-row">
        <p class="refresh-state" id="refresh-state" role="status" aria-live="polite">{{ $isTerminal ? 'Proses antrean berakhir' : '● Diperbarui otomatis' }}</p>
        @unless($isTerminal)<button type="button" class="refresh-button" data-refresh>Perbarui</button>@endunless
    </div>

    <section class="ticket-card {{ $ticket->status->value }}" aria-label="Tiket antrean {{ $ticket->displayNumber() }}">
        <span class="ticket-badge">{{ $ticket->status->label() }}</span>
        <p class="eyebrow">NOMOR ANTREAN · {{ $ticket->queue_type->label() }}</p>
        <strong class="ticket-number">{{ $ticket->displayNumber() }}</strong>
        <p class="ticket-person">{{ $ticket->customer_name }} · {{ $ticket->party_size }} orang</p>
        <div class="ticket-bottom">
            <span>Nomor terakhir dipanggil</span>
            <strong data-latest-called>{{ $snapshot['latest_called'] ?? '—' }}</strong>
        </div>
    </section>

    @if($ticket->status !== \App\Enums\TicketStatus::Cancelled)
        <ol class="ticket-progress" aria-label="Tahap antrean">
            @foreach($steps as $value => $label)
                <li class="{{ $loop->index < $currentStep ? 'is-complete' : '' }} {{ $ticket->status->value === $value ? 'is-current' : '' }}" @if($ticket->status->value === $value) aria-current="step" @endif>
                    <span class="progress-dot" aria-hidden="true">{{ $loop->iteration }}</span>
                    <span>{{ $label }}</span>
                </li>
            @endforeach
        </ol>
    @endif

    @if($ticket->status === \App\Enums\TicketStatus::Waiting)
        <section class="position-card" aria-label="Posisi dan perkiraan tunggu">
            <div><strong data-ahead>{{ $snapshot['ahead'] }}</strong><small>antrean layanan ini di depan</small></div>
            <div><strong data-estimate>{{ $snapshot['estimate_minutes'] === null ? 'Belum tersedia' : '± '.$snapshot['estimate_minutes'].' mnt' }}</strong><small>perkiraan tunggu</small></div>
        </section>
        <section class="status-guidance" aria-labelledby="near-title">
            <h2 id="near-title">{{ $snapshot['near_turn'] ? 'Giliran hampir tiba' : 'Pantau halaman ini' }}</h2>
            <p class="muted" id="near-copy">{{ $snapshot['ahead'] === 0 ? 'Giliran berikutnya; tunggu panggilan staf.' : 'Datang saat giliranmu hampir tiba. Estimasi dapat berubah mengikuti layanan.' }}</p>
        </section>
    @elseif($ticket->status === \App\Enums\TicketStatus::Called)
        <section class="arrival-card called-guidance">
            <span class="eyebrow">GILIRANMU TIBA</span>
            <h2>Silakan datang ke lokasi</h2>
            <p>Tunjukkan nomor antrean kepada staf. Staf akan mengonfirmasi kedatangan Anda.</p>
            <div class="deadline-panel">
                <span>Batas hadir</span>
                <strong>{{ $ticket->arrival_deadline->timezone('Asia/Makassar')->format('H.i') }} WITA</strong>
                <p data-deadline="{{ $ticket->arrival_deadline->toIso8601String() }}" role="status" aria-live="off"></p>
            </div>
            <small>Setelah batas waktu, staf dapat membatalkan antrean. Status tidak dibatalkan otomatis.</small>
        </section>
    @elseif($ticket->status === \App\Enums\TicketStatus::Arrived)
        <section class="arrival-card">
            <span class="eyebrow">SUDAH DI LOKASI</span>
            <h2>Kedatangan dikonfirmasi</h2>
            <p>{{ $ticket->queue_type === \App\Enums\QueueType::DineIn ? 'Ikuti arahan staf menuju meja. Nomor akan ditandai selesai setelah Anda diarahkan ke meja.' : 'Ikuti arahan staf untuk layanan Bawa Pulang. Staf akan menandai tiket selesai setelah layanan selesai.' }}</p>
        </section>
    @elseif($ticket->status === \App\Enums\TicketStatus::Completed)
        <section class="arrival-card terminal-card">
            <span class="eyebrow">ANTREAN SELESAI</span>
            <h2>{{ $ticket->queue_type === \App\Enums\QueueType::DineIn ? 'Selamat menikmati!' : 'Layanan selesai' }}</h2>
            <p>{{ $ticket->queue_type === \App\Enums\QueueType::DineIn ? 'Anda sudah diarahkan ke meja. Proses antrean selesai.' : 'Tiket Bawa Pulang telah ditandai selesai oleh staf. Proses antrean selesai.' }}</p>
            <a class="button secondary full" href="{{ route('home') }}">Kembali ke halaman awal</a>
        </section>
    @else
        <section class="arrival-card terminal-card cancelled-guidance">
            <span class="eyebrow">NOMOR TIDAK AKTIF</span>
            <h2>Antrean telah dibatalkan</h2>
            @if($ticket->cancellation_reason)
                <p>{{ ['customer' => 'Antrean dibatalkan oleh pelanggan.', 'staff' => 'Antrean dibatalkan oleh staf.', 'skipped' => 'Staf melewati nomor ini.', 'no_show' => 'Staf membatalkan karena batas hadir terlewati.'][$ticket->cancellation_reason] ?? 'Nomor ini tidak lagi aktif.' }}</p>
            @endif
            <p>Nomor ini sudah keluar dari antrean. Untuk kembali, ambil nomor baru.</p>
            <a class="button full" href="{{ route('home') }}">Ambil nomor baru</a>
        </section>
    @endif

    <p class="ticket-issued">Terdaftar <time datetime="{{ $ticket->joined_at->toIso8601String() }}">{{ $ticket->joined_at->timezone('Asia/Makassar')->format('d/m/Y · H.i') }} WITA</time></p>

    @if(in_array($ticket->status, [\App\Enums\TicketStatus::Waiting, \App\Enums\TicketStatus::Called], true))
        <form method="post" action="{{ route('ticket.cancel', ['token' => $token]) }}" data-confirm="Batalkan antrean {{ $ticket->queue_type->label() }} {{ $ticket->displayNumber() }}?" class="cancel-ticket">
            @csrf
            <input type="hidden" name="operation_id" value="{{ $operation }}">
            <button class="button secondary full" data-loading-label="Membatalkan antrean…">Batalkan antrean</button>
        </form>
    @endif

    <details class="ticket-help">
        <summary>Simpan akses antrean</summary>
        <p>Simpan halaman ini sebagai bookmark. Tautan ini bersifat pribadi; siapa pun yang memilikinya dapat melihat atau membatalkan tiket Anda.</p>
    </details>
    <noscript><p class="notice">Pembaruan otomatis memerlukan JavaScript. Muat ulang halaman untuk memeriksa status terbaru.</p></noscript>
</div>
@endsection
