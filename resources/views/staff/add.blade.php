@extends('layout',['staff'=>true])
@section('title','Tambah antrean')
@section('content')
<a class="back-link" href="{{ route('staff.index') }}">← Kembali ke dasbor</a>
<h1>Tambah antrean</h1>
<p class="lead">Nomor pelanggan mengikuti urutan yang sama.</p>
<form class="form-card" method="post" action="{{ route('staff.store') }}">@csrf @include('partials.fields')<button class="button full join-button">Tambahkan pelanggan</button>
</form>
@endsection
