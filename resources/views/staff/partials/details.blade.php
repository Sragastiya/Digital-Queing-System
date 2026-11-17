<details class="ticket-details" data-detail-id="{{ $ticket->id }}">
    <summary aria-label="Lihat detail {{ $ticket->displayNumber() }}">Lihat detail</summary>
    <dl>
        <div><dt>Nama</dt><dd>{{ $ticket->customer_name }}</dd></div>
        <div><dt>Jumlah orang</dt><dd>{{ $ticket->party_size }} orang</dd></div>
        <div><dt>Telepon</dt><dd>{{ $ticket->phone ?: 'Tidak diisi' }}</dd></div>
        @foreach(['joined_at' => 'Terdaftar', 'called_at' => 'Dipanggil', 'arrival_deadline' => 'Batas hadir', 'arrived_at' => 'Hadir', 'completed_at' => 'Selesai', 'cancelled_at' => 'Dibatalkan'] as $field => $label)
            @if($ticket->$field)
                <div><dt>{{ $label }}</dt><dd><time datetime="{{ $ticket->$field->toIso8601String() }}">{{ $ticket->$field->timezone('Asia/Makassar')->format('d/m/Y H.i') }} WITA</time></dd></div>
            @endif
        @endforeach
        @if($ticket->cancellation_reason)
            <div><dt>Alasan</dt><dd>{{ ['customer' => 'Dibatalkan pelanggan', 'staff' => 'Dibatalkan staf', 'skipped' => 'Dilewati, keluar antrean', 'no_show' => 'Tidak hadir melewati batas'][$ticket->cancellation_reason] ?? 'Dibatalkan' }}</dd></div>
        @endif
    </dl>
</details>
