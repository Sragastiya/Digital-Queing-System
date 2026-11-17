@extends('layout', ['staff' => true])
@section('title', 'Tambah antrean')
@section('content')
    <a class="back-link" href="{{ route('staff.index') }}">← Kembali ke dasbor</a>
    <h1>Tambah antrean</h1>
    <p class="lead">Bantu pelanggan mendaftar. Nomor mengikuti urutan antrean yang sama.</p>
    <form class="form-card" method="post" action="{{ route('staff.store') }}">
        @csrf
        <h2>Data pelanggan</h2>
        @include('partials.fields')
        <button class="button full join-button" data-loading-label="Menambahkan pelanggan…">Tambahkan pelanggan</button>
        <a class="form-cancel" href="{{ route('staff.index') }}">Batal dan kembali</a>
    </form>
@endsection
