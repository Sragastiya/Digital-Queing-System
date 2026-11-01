<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="no-referrer">
<title>@yield('title','Antrean Digital') · Bu Andika</title>
<link rel="stylesheet" href="{{ asset('app.css') }}">
<script src="{{ asset('app.js') }}" defer>
</script>
</head>
<body class="{{ ($staff??false)?'staff-app':'customer-app' }}">
<a class="skip-link" href="#main">Langsung ke konten</a>
@if($staff??false)<aside class="sidebar">
<a class="brand inverse" href="{{ route('staff.index') }}">
<span class="brand-mark">
<img src="{{ asset('design/ticket.svg') }}" alt="">
</span>
<span>
<strong>Antrean Digital</strong>
<small>Bu Andika · Kuta</small>
</span>
</a>
<p class="eyebrow">RUANG KERJA</p>
<a class="nav-active" href="{{ route('staff.index') }}">
<img src="{{ asset('design/queue.svg') }}" alt=""> Kelola antrean</a>
<div class="staff-identity">
<strong>{{ auth()->user()->name }}</strong>
<small>Petugas antrean</small>
<form method="post" action="{{ route('staff.logout') }}">@csrf<button class="logout">Keluar</button>
</form>
</div>
</aside>
<div class="staff-workspace">
<header class="topbar">
<span>Nasi Pedas Bu Andika / Cabang Utama</span>
<a href="{{ route('home') }}">Halaman pelanggan ↗</a>
</header>

@else

<header class="customer-header">
<a class="brand" href="{{ route('home') }}">
<span class="brand-mark">
<img src="{{ asset('design/ticket.svg') }}" alt="">
</span>
<span>
<strong>Nasi Pedas Bu Andika</strong>
<small>Cabang Utama · Kuta</small>
</span>
</a>
</header>

@endif


<main id="main" class="{{ ($staff??false)?'staff-main':'customer-main' }}">@if(session('notice'))<div class="notice" role="status">{{ session('notice') }}</div>

@endif

 @if($errors->any())<div class="errors" role="alert">
<strong>Periksa kembali isian Anda.</strong>
<ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>

@endforeach

</ul>
</div>

@endif

 @yield('content')</main>
@if($staff??false)</div>

@else

<footer class="customer-footer">
<p>Antrean untuk kunjungan saat ini</p>
<small>Data pribadi tidak ditampilkan ke pelanggan lain.</small>
<a href="{{ route('login') }}">Masuk staf</a>
</footer>

@endif


<dialog id="confirm-dialog" aria-labelledby="confirm-title">
<h2 id="confirm-title">Batalkan antrean?</h2>
<p>Nomor akan keluar dari antrean. Untuk kembali, pelanggan perlu mengambil nomor baru.</p>
<div class="dialog-actions">
<button type="button" class="button secondary" data-close-dialog>Kembali</button>
<button type="button" class="button danger" id="confirm-submit">Ya, lanjutkan</button>
</div>
</dialog>
</body>
</html>
