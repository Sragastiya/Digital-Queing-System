<div class="metrics">@foreach(['waiting'=>'Menunggu','called'=>'Dipanggil','arrived'=>'Hadir','completed'=>'Selesai'] as $status=>$label)<section class="metric">
<h2>{{ $label }}</h2>
<strong>{{ $counts[$status]??0 }}</strong>
<small>{{ $status==='completed'?'total sejak awal':'antrean aktif' }}</small>
</section>
@endforeach
</div>
<section class="pending-section">
<h2>Menunggu kedatangan / pengarahan</h2>
<p class="muted">Setiap pelanggan yang dipanggil memiliki batas hadir sendiri.</p>
@forelse($pending as $ticket)<div class="pending-ticket {{ $ticket->arrival_deadline?->isPast() && $ticket->status===\App\Enums\TicketStatus::Called?'overdue':'' }}">
<div>
<span class="eyebrow">{{ $ticket->status->label() }}</span>
<strong class="pending-number">{{ $ticket->displayNumber() }}</strong>
</div>
<div class="pending-person">
<strong>{{ $ticket->customer_name }} · {{ $ticket->party_size }} orang</strong>
<small>@if($ticket->status===\App\Enums\TicketStatus::Called)Dipanggil {{ $ticket->called_at->timezone('Asia/Makassar')->format('H.i') }} · batas hadir {{ $ticket->arrival_deadline->timezone('Asia/Makassar')->format('H.i') }} WITA @if($ticket->arrival_deadline->isPast())<b class="danger-text"> · Batas terlewati</b>
@endif
 
@else
 Hadir {{ $ticket->arrived_at->timezone('Asia/Makassar')->format('H.i') }} · arahkan ke meja 
@endif
</small>
</div>
<div class="pending-actions">@include('staff.partials.actions',['compact'=>true])</div>
</div>
@empty
<div class="empty-state">Belum ada pelanggan yang menunggu kedatangan atau pengarahan.</div>
@endforelse
</section>
<div class="list-heading">
<h2>Daftar antrean</h2>
<div class="button-row">
<a class="button secondary" href="{{ route('staff.create') }}">+ Tambah antrean</a>
<form method="post" action="{{ route('staff.call') }}">@csrf<input type="hidden" name="operation_id" value="{{ $operation }}">
<button class="button">Panggil berikutnya</button>
</form>
</div>
</div>
<form method="get" action="{{ route('staff.index') }}" class="filters">
<label class="sr-only" for="search">Cari nomor atau nama</label>
<input id="search" name="q" value="{{ request('q') }}" placeholder="Cari nomor atau nama pelanggan" maxlength="120">
<label class="sr-only" for="filter-status">Status</label>
<select id="filter-status" name="status">
<option value="">Semua status</option>@foreach(\App\Enums\TicketStatus::cases() as $case)<option value="{{ $case->value }}" @selected(request('status')===$case->value)>{{ $case->label() }}</option>
@endforeach
</select>
<button class="button secondary">Cari</button>
</form>
<div class="table-scroll">
<table>
<caption class="sr-only">Daftar antrean pelanggan</caption>
<thead>
<tr>
<th>Nomor</th>
<th>Nama pelanggan</th>
<th>Orang</th>
<th>Daftar</th>
<th>Status</th>
<th>Tindakan</th>
</tr>
</thead>
<tbody>@forelse($tickets as $ticket)<tr>
<td>
<strong>{{ $ticket->displayNumber() }}</strong>
</td>
<td>{{ $ticket->customer_name }} @if($ticket->phone)<small class="phone">{{ $ticket->phone }}</small>
@endif
</td>
<td>{{ $ticket->party_size }}</td>
<td>{{ $ticket->joined_at->timezone('Asia/Makassar')->format('d/m H.i') }}</td>
<td>
<span class="status-badge {{ $ticket->status->value }}">{{ $ticket->status->label() }}</span>
</td>
<td>@include('staff.partials.actions',['compact'=>false])</td>
</tr>
@empty
<tr>
<td colspan="6" class="empty-state">Tidak ada antrean yang cocok.</td>
</tr>
@endforelse
</tbody>
</table>
</div>
<div class="pagination">
<span>Menampilkan {{ $tickets->count() }} dari {{ $tickets->total() }} antrean</span>
<div>@if($tickets->previousPageUrl())<a href="{{ $tickets->previousPageUrl() }}">← Sebelumnya</a>
@endif
 @if($tickets->hasMorePages())<a href="{{ $tickets->nextPageUrl() }}">Berikutnya →</a>
@endif
</div>
</div>
