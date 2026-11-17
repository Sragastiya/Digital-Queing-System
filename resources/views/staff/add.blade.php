@extends('layout', ['staff' => true])
@section('title', 'Tambah antrean')
@section('content')
    <a class="back-link" href="{{ route('staff.index', ['queue_type' => $selectedQueueType]) }}">← Kembali ke dasbor</a>
    <h1>Tambah antrean</h1>
    <p class="lead">Bantu pelanggan mendaftar ke antrean layanan yang dipilih.</p>
    <form class="form-card" method="post" action="{{ route('staff.store') }}">
        @csrf
        <h2>Data pelanggan</h2>
        @include('partials.queue-selector')
        @include('partials.fields')
        <button class="button full join-button" data-loading-label="Menambahkan pelanggan…">Tambahkan pelanggan</button>
        <a class="form-cancel" href="{{ route('staff.index', ['queue_type' => $selectedQueueType]) }}">Batal dan kembali</a>
    </form>
@endsection
