@extends('layout')
@section('title','Antrean kamu')
@section('content')
<div data-ticket-poll="{{ route('ticket.status',['token'=>$token]) }}" data-status="{{ $ticket->status->value }}">
<h1>Antrean kamu</h1>
<p class="refresh-state" id="refresh-state" role="status">● Diperbarui otomatis</p>
<section class="ticket-card {{ $ticket->status->value }}">
<span class="ticket-badge">{{ $ticket->status->label() }}</span>
<p class="eyebrow">NOMOR ANTREAN</p>
<strong class="ticket-number">{{ $ticket->displayNumber() }}</strong>
<p class="ticket-person">{{ $ticket->customer_name }} · {{ $ticket->party_size }} orang</p>
<div class="ticket-bottom">
<span>Nomor terakhir dipanggil</span>
<strong data-latest-called>{{ $snapshot['latest_called']??'—' }}</strong>
</div>
</section>
@if($ticket->status===\App\Enums\TicketStatus::Waiting)<section class="position-card">
<div>
<strong data-ahead>{{ $snapshot['ahead'] }}</strong>
<small>antrean menunggu di depan</small>
</div>
<div>
<strong data-estimate>{{ $snapshot['estimate_minutes']===null?'Belum tersedia':'± '.$snapshot['estimate_minutes'].' mnt' }}</strong>
<small>perkiraan tunggu</small>
</div>
</section>
<h2 id="near-title">{{ $snapshot['near_turn']?'Giliran hampir tiba':'Pantau halaman ini' }}</h2>
<p class="muted" id="near-copy">{{ $snapshot['ahead']===0?'Giliran berikutnya; tunggu panggilan staf.':'Datang saat giliranmu hampir tiba. Estimasi dapat berubah mengikuti layanan.' }}</p>

@elseif($ticket->status===\App\Enums\TicketStatus::Called)<section class="arrival-card">
<h2>Silakan datang ke lokasi</h2>
<p>Tunjukkan nomor antrean kepada staf. Staf akan mengonfirmasi kedatangan Anda.</p>
<p>Batas hadir <strong>{{ $ticket->arrival_deadline->timezone('Asia/Makassar')->format('H.i') }} WITA</strong>
</p>
<p data-deadline="{{ $ticket->arrival_deadline->toIso8601String() }}" role="status">
</p>
<small>Setelah batas waktu, staf dapat membatalkan antrean. Status tidak dibatalkan otomatis.</small>
</section>

@elseif($ticket->status===\App\Enums\TicketStatus::Arrived)<section class="arrival-card">
<h2>Kedatangan dikonfirmasi</h2>
<p>Ikuti arahan staf menuju meja. Nomor akan ditandai selesai setelah Anda diarahkan ke meja.</p>
</section>

@elseif($ticket->status===\App\Enums\TicketStatus::Completed)<section class="arrival-card">
<h2>Selamat menikmati!</h2>
<p>Anda sudah diarahkan ke meja. Proses antrean selesai.</p>
</section>

@else
<section class="arrival-card">
<h2>Antrean telah dibatalkan</h2>
<p>Nomor ini sudah keluar dari antrean. Untuk kembali, ambil nomor baru.</p>
<a class="button full" href="{{ route('home') }}">Ambil nomor baru</a>
</section>
@endif

@if(in_array($ticket->status,[\App\Enums\TicketStatus::Waiting,\App\Enums\TicketStatus::Called],true))<form method="post" action="{{ route('ticket.cancel',['token'=>$token]) }}" data-confirm="Batalkan antrean?" class="cancel-ticket">@csrf<input type="hidden" name="operation_id" value="{{ $operation }}">
<button class="button secondary full">Batalkan antrean</button>
</form>
@endif

<details class="ticket-help">
<summary>Simpan akses antrean</summary>
<p>Simpan halaman ini sebagai bookmark. Tautan ini bersifat pribadi; siapa pun yang memilikinya dapat melihat atau membatalkan tiket Anda.</p>
</details>
</div>
@endsection
