@php($tickets->withPath(route('staff.index')))
<div class="metrics">
    @foreach(['waiting' => 'Menunggu', 'called' => 'Dipanggil', 'arrived' => 'Hadir', 'completed' => 'Selesai'] as $status => $label)
        <section class="metric metric-{{ $status }}">
            <h2>{{ $label }}</h2>
            <strong>{{ $counts[$status] ?? 0 }}</strong>
            <small>{{ $status === 'completed' ? 'total sejak awal' : 'antrean aktif' }}</small>
        </section>
    @endforeach
</div>

<section class="pending-section" aria-labelledby="pending-title">
    <div class="section-heading">
        <h2 id="pending-title">Menunggu kedatangan / pengarahan</h2>
        <span class="count-chip">{{ $pending->count() }} antrean</span>
    </div>
    <p class="muted">Setiap pelanggan yang dipanggil memiliki batas hadir sendiri.</p>
    @forelse($pending as $ticket)
        <article class="pending-ticket {{ $ticket->arrival_deadline?->isPast() && $ticket->status === \App\Enums\TicketStatus::Called ? 'overdue' : '' }}" aria-label="{{ $ticket->displayNumber() }} {{ $ticket->status->label() }}">
            <div>
                <span class="eyebrow">{{ $ticket->status->label() }}</span>
                <strong class="pending-number">{{ $ticket->displayNumber() }}</strong>
            </div>
            <div class="pending-person">
                <strong>{{ $ticket->customer_name }} · {{ $ticket->party_size }} orang</strong>
                <small>
                    @if($ticket->status === \App\Enums\TicketStatus::Called)
                        Dipanggil {{ $ticket->called_at->timezone('Asia/Makassar')->format('H.i') }} · batas hadir {{ $ticket->arrival_deadline->timezone('Asia/Makassar')->format('H.i') }} WITA
                        @if($ticket->arrival_deadline->isPast())<b class="danger-text"> · Batas terlewati</b>@endif
                    @else
                        Hadir {{ $ticket->arrived_at->timezone('Asia/Makassar')->format('H.i') }} · arahkan ke meja
                    @endif
                </small>
            </div>
            <div class="pending-actions">@include('staff.partials.actions', ['compact' => true])</div>
        </article>
    @empty
        <div class="empty-state pending-empty">
            <strong>Belum ada pelanggan yang dipanggil</strong>
            <p>Pelanggan yang dipanggil akan tampil di sini sampai diarahkan ke meja.</p>
        </div>
    @endforelse
</section>

<section class="queue-list" aria-labelledby="queue-list-title">
    <div class="list-heading">
        <div><h2 id="queue-list-title">Daftar antrean</h2><p class="muted section-copy">Urutan nomor mengikuti waktu pendaftaran.</p></div>
        <div class="button-row">
            <a class="button secondary" href="{{ route('staff.create') }}">+ Tambah antrean</a>
            <form method="post" action="{{ route('staff.call') }}">
                @csrf
                <input type="hidden" name="operation_id" value="{{ $operation }}">
                <button class="button" data-loading-label="Memanggil…" @disabled(($counts['waiting'] ?? 0) === 0)>Panggil berikutnya</button>
            </form>
        </div>
    </div>
    @if(($counts['waiting'] ?? 0) === 0)
        <p class="queue-empty-hint">Tidak ada pelanggan yang menunggu untuk dipanggil.</p>
    @endif

    <form method="get" action="{{ route('staff.index') }}" class="filters">
        <div class="filter-search">
            <label for="search">Cari antrean</label>
            <input id="search" name="q" value="{{ request('q') }}" placeholder="Nomor atau nama pelanggan" maxlength="120" type="search">
        </div>
        <div class="filter-status">
            <label for="filter-status">Status</label>
            <select id="filter-status" name="status">
                <option value="">Semua status</option>
                @foreach(\App\Enums\TicketStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected(request('status') === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>
        <button class="button secondary">Terapkan</button>
        @if(request()->filled('q') || request()->filled('status'))
            <a class="filter-reset" href="{{ route('staff.index') }}">Hapus filter</a>
        @endif
    </form>

    <div class="table-scroll" tabindex="0" role="region" aria-label="Daftar antrean pelanggan">
        <table class="queue-table">
            <caption class="sr-only">Daftar antrean pelanggan. Waktu ditampilkan dalam WITA.</caption>
            <thead><tr><th scope="col">Nomor</th><th scope="col">Nama pelanggan</th><th scope="col">Orang</th><th scope="col">Daftar</th><th scope="col">Status</th><th scope="col">Tindakan</th></tr></thead>
            <tbody>
                @forelse($tickets as $ticket)
                    <tr>
                        <td data-label="Nomor"><strong class="table-number">{{ $ticket->displayNumber() }}</strong></td>
                        <td data-label="Pelanggan"><span class="table-person">{{ $ticket->customer_name }}</span>@if($ticket->phone)<small class="phone">{{ $ticket->phone }}</small>@endif</td>
                        <td data-label="Jumlah orang">{{ $ticket->party_size }}</td>
                        <td data-label="Terdaftar"><time datetime="{{ $ticket->joined_at->toIso8601String() }}">{{ $ticket->joined_at->timezone('Asia/Makassar')->format('d/m H.i') }}</time></td>
                        <td data-label="Status"><span class="status-badge {{ $ticket->status->value }}">{{ $ticket->status->label() }}</span></td>
                        <td data-label="Tindakan" class="table-actions">
                            @include('staff.partials.actions', ['compact' => false])
                            @include('staff.partials.details')
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="6" class="empty-state">
                        @if($tickets->total() > 0)
                            <strong>Halaman ini tidak memiliki antrean</strong>
                            <p><a href="{{ route('staff.index', request()->only('q', 'status')) }}">Kembali ke halaman pertama</a></p>
                        @elseif(request()->filled('q') || request()->filled('status'))
                            <strong>Tidak ada antrean yang cocok</strong>
                            <p>Coba nama, nomor, atau status lainnya. <a href="{{ route('staff.index') }}">Hapus filter</a></p>
                        @else
                            <strong>Belum ada antrean</strong>
                            <p>Antrean akan muncul setelah pelanggan mendaftar. Anda juga dapat <a href="{{ route('staff.create') }}">menambahkan pelanggan</a>.</p>
                        @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <nav class="pagination" aria-label="Halaman daftar antrean">
        <span>{{ $tickets->count() ? $tickets->firstItem().'–'.$tickets->lastItem() : '0' }} dari {{ $tickets->total() }} antrean · WITA</span>
        @if($tickets->hasPages())
            <div>
                @if($tickets->previousPageUrl())<a href="{{ $tickets->previousPageUrl() }}" rel="prev">← Sebelumnya</a>@else<span class="pagination-disabled">← Sebelumnya</span>@endif
                <span class="page-number">{{ $tickets->currentPage() }} / {{ $tickets->lastPage() }}</span>
                @if($tickets->hasMorePages())<a href="{{ $tickets->nextPageUrl() }}" rel="next">Berikutnya →</a>@else<span class="pagination-disabled">Berikutnya →</span>@endif
            </div>
        @endif
    </nav>
</section>
