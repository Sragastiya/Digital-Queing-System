@extends('layout', ['staff' => true])
@section('title', 'Kelola antrean')
@section('content')
    <div class="page-heading">
        <div>
            <h1>Kelola antrean</h1>
            <p class="muted">Pilih jenis layanan. Setiap antrean dipanggil secara terpisah.</p>
        </div>
        <div class="date-chip"><img src="{{ asset('design/calendar.svg') }}" alt="">{{ now()->timezone('Asia/Makassar')->locale('id')->translatedFormat('l, j F Y') }}</div>
    </div>
    <div class="refresh-row">
        <p class="refresh-state" id="refresh-state" role="status" aria-live="polite">● Diperbarui otomatis</p>
        <button type="button" class="refresh-button" data-refresh>Perbarui</button>
    </div>
    <section class="staff-cancellations" aria-labelledby="staff-cancellations-title">
        <h2 id="staff-cancellations-title">Pembatalan setelah dipanggil</h2>
        <div data-staff-cancellations data-latest-id="{{ $calledCancellations->first()?->id }}" aria-live="polite">
            @include('staff.partials.cancellation-notices')
        </div>
    </section>
    <div id="staff-board" data-queue-type="{{ $queueType->value }}" data-staff-poll="{{ route('staff.poll', array_merge(request()->only('q', 'status', 'page'), ['queue_type' => $queueType->value])) }}">
        @include('staff.partials.board')
    </div>

    <details class="settings-panel" @if($errors->has('estimate_interval_minutes')) open @endif>
        <summary>Pengaturan perkiraan tunggu</summary>
        <p class="muted" id="estimate-help">Interval ini berlaku untuk kedua antrean. Perkiraan masing-masing layanan dihitung dari antrean menunggu di depannya × interval panggilan. Kosongkan agar perkiraan tidak ditampilkan. Nilai perlu dikalibrasi dengan kondisi restoran.</p>
        <form method="post" action="{{ route('staff.settings') }}">
            @csrf
            <input type="hidden" name="queue_type" value="{{ $queueType->value }}">
            <label for="estimate_interval_minutes">Interval panggilan (menit, opsional)</label>
            <input id="estimate_interval_minutes" name="estimate_interval_minutes" type="number" min="1" max="1440" value="{{ old('estimate_interval_minutes', $settings->estimate_interval_minutes) }}" aria-describedby="estimate-help{{ $errors->has('estimate_interval_minutes') ? ' estimate-error' : '' }}" @error('estimate_interval_minutes') aria-invalid="true" @enderror>
            @error('estimate_interval_minutes')<p class="field-error" id="estimate-error">{{ $message }}</p>@enderror
            <button class="button" data-loading-label="Menyimpan…">Simpan perkiraan</button>
        </form>
    </details>
    @if($settings->autoCallAvailable($queueType))
        <details class="settings-panel" @if($errors->has('enabled')) open @endif>
            <summary>Panggilan otomatis · {{ $queueType->label() }}</summary>
            <p class="muted" id="auto-call-help">Saat pelanggan yang sudah dipanggil membatalkan sendiri, sistem akan memanggil satu pelanggan berikutnya jika ada yang menunggu di layanan ini.</p>
            <form method="post" action="{{ route('staff.settings.auto-call') }}">
                @csrf
                <input type="hidden" name="queue_type" value="{{ $queueType->value }}">
                <label for="auto-call-enabled">Panggilan setelah pembatalan</label>
                <select id="auto-call-enabled" name="enabled" aria-describedby="auto-call-help{{ $errors->has('enabled') ? ' auto-call-error' : '' }}" @error('enabled') aria-invalid="true" @enderror>
                    <option value="0" @selected(!old('enabled', $settings->autoCallEnabled($queueType)))>Nonaktif</option>
                    <option value="1" @selected(old('enabled', $settings->autoCallEnabled($queueType)))>Aktif</option>
                </select>
                @error('enabled')<p class="field-error" id="auto-call-error">{{ $message }}</p>@enderror
                <button class="button" data-loading-label="Menyimpan…">Simpan panggilan otomatis</button>
            </form>
        </details>
    @endif
    <noscript><p class="notice">Muat ulang halaman untuk melihat status antrean terbaru.</p></noscript>
@endsection
