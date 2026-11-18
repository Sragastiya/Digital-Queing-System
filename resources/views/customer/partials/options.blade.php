<div class="customer-options">
    <span class="dialog-eyebrow">PILIHAN ANTREAN</span>
    <h2 id="ticket-options-title">Ubah rencana antrean?</h2>
    <p class="option-identity">{{ $ticket->queue_type->label() }} · <strong>{{ $ticket->displayNumber() }}</strong></p>
    <a class="button full" href="{{ route('ticket.show', ['token' => $token]) }}" data-close-options autofocus>Tetap di antrean</a>
    <p class="option-hint">Menutup pilihan ini tidak mengubah tiket Anda.</p>

    @if($ticket->status === \App\Enums\TicketStatus::Waiting)
        <details class="queue-option">
            <summary>Pindah layanan <span>Ambil nomor baru di {{ $destination->label() }}</span></summary>
            <p>Nomor {{ $ticket->displayNumber() }} akan dinonaktifkan. Tiket baru masuk di akhir antrean {{ $destination->label() }}. Nama, jumlah orang, dan nomor telepon tetap digunakan.</p>
            <form method="post" action="{{ route('ticket.switch', ['token' => $token]) }}"
                  data-confirm="Pindah ke {{ $destination->label() }}?"
                  data-confirm-copy="Nomor lama tidak aktif lagi. Anda mendapat nomor baru di akhir antrean tujuan; posisi lama tidak dibawa."
                  data-confirm-label="Ya, pindah layanan" data-confirm-kind="change">
                @csrf
                <input type="hidden" name="operation_id" value="{{ $operations['switch'] }}">
                <input type="hidden" name="queue_type" value="{{ $destination->value }}">
                <button class="button secondary full" data-loading-label="Memindahkan layanan…">Pindah ke {{ $destination->label() }}</button>
            </form>
        </details>
        <details class="queue-option">
            <summary>Beri giliran <span>Mundur hingga 3 posisi dalam layanan ini</span></summary>
            <p>Pelanggan yang menunggu di belakang Anda mendapat giliran lebih dulu. Jika kurang dari 3, Anda pindah ke akhir antrean. Nomor tetap {{ $ticket->displayNumber() }}; perkiraan tunggu menyesuaikan.</p>
            <form method="post" action="{{ route('ticket.give', ['token' => $token]) }}"
                  data-confirm="Beri giliran kepada pelanggan lain?"
                  data-confirm-copy="Anda mundur di belakang hingga 3 pelanggan yang masih menunggu dalam layanan ini. Nomor tiket tetap sama."
                  data-confirm-label="Ya, beri giliran" data-confirm-kind="change">
                @csrf
                <input type="hidden" name="operation_id" value="{{ $operations['give'] }}">
                <button class="button secondary full" data-loading-label="Memperbarui posisi…">Beri giliran</button>
            </form>
        </details>
    @else
        <p class="notice">Nomor sudah dipanggil. Pindah layanan dan beri giliran hanya tersedia saat menunggu; Anda masih dapat membatalkan antrean.</p>
    @endif

    <details class="queue-option cancellation-option" @if(old('reason') || $errors->has('reason')) open @endif>
        <summary>Batalkan antrean <span>Keluar dari antrean dengan memilih alasan</span></summary>
        <p>Nomor ini tidak aktif lagi setelah dibatalkan. Untuk kembali, Anda perlu mengambil nomor baru.</p>
        <form method="post" action="{{ route('ticket.cancel', ['token' => $token]) }}"
              data-confirm="Batalkan antrean {{ $ticket->queue_type->label() }} {{ $ticket->displayNumber() }}?" class="cancel-ticket">
            @csrf
            <input type="hidden" name="operation_id" value="{{ $operations['cancel'] }}">
            <label for="customer-cancellation-reason">Alasan pembatalan</label>
            <select name="reason" id="customer-cancellation-reason" required @if($errors->has('reason')) aria-invalid="true" @endif>
                <option value="">Pilih alasan</option>
                @foreach($customerReasons as $reason)
                    <option value="{{ $reason->value }}" @selected(old('reason') === $reason->value)>{{ $reason->label() }}</option>
                @endforeach
            </select>
            <button class="button danger full" data-loading-label="Membatalkan antrean…">Batalkan antrean</button>
        </form>
    </details>
</div>
