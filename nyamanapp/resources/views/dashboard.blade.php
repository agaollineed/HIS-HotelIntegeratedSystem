@extends('layouts.panel')

@section('title', 'Dashboard')

@push('head')
<style>
    /* Admin: 3 kartu fitur + 1 kartu jadwal vertikal di sampingnya.
       Staff: kartu fitur saja, selebar halaman. */
    .dash-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(290px, 330px); gap: 18px; align-items: start; }
    .cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
    .dash-grid .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .card-link {
        position: relative; display: block; padding: 26px; overflow: hidden;
        color: var(--text); text-decoration: none;
        transition: transform .18s, box-shadow .18s;
    }
    /* Garis aksen tipis di atas kartu — warnanya mengikuti aksen kartu. */
    .card-link::before {
        content: ''; position: absolute; inset: 0 0 auto 0; height: 3px;
        background: var(--accent); opacity: .85;
    }
    .card-link:hover { transform: translateY(-4px); box-shadow: 0 30px 60px -26px var(--accent-glow); }
    .card-link .icon {
        width: 54px; height: 54px; border-radius: 14px; margin-bottom: 16px;
        display: flex; align-items: center; justify-content: center;
        background: var(--accent-soft); border: 1px solid var(--border); color: var(--accent);
    }

    /* Varian oranye (kartu Kamar) — aksen sekunder. */
    .card-link.accent-orange::before { background: var(--accent-2); }
    .card-link.accent-orange:hover { box-shadow: 0 30px 60px -26px var(--accent-2-glow); }
    .card-link.accent-orange .icon { background: var(--accent-2-soft); border-color: var(--border-orange); }
    .card-link h3 { margin: 0 0 6px; font-size: 1.12rem; font-weight: 800; letter-spacing: -.015em; }
    .card-link p { margin: 0; color: var(--muted); font-size: .9rem; line-height: 1.5; }
    .card-link .count {
        position: absolute; top: 18px; right: 18px;
        font-variant-numeric: tabular-nums; font-size: .72rem; font-weight: 700;
        padding: 4px 11px; border-radius: 999px;
        background: var(--grad); color: #ffffff; box-shadow: 0 8px 20px -8px var(--accent-glow);
    }
    @media (max-width: 1100px) {
        .dash-grid { grid-template-columns: 1fr; }
        .dash-grid .cards { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 860px) {
        .cards, .dash-grid .cards { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
        .cards, .dash-grid .cards { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
    <div class="page-head">
        <h1>Hai, {{ Auth::user()->name }}</h1>
        <p>
            @if (Auth::user()->isAdmin())
                Pantau stok, hunian kamar, dan laporan dari staff — semuanya dari satu tempat.
            @else
                Catat stok, hunian kamar, dan kirim laporan ke admin — semuanya dari satu tempat.
            @endif
        </p>
    </div>

    @if (Auth::user()->isAdmin())
        @include('admin.revenue.chart')
    @endif

    <div class="{{ Auth::user()->isAdmin() ? 'dash-grid' : '' }}">
    <div class="cards">
        @if (Auth::user()->isAdmin())
            <a href="{{ route('admin.stocks.index') }}" class="card card-link">
                <div class="icon">@include('partials.icon', ['name' => 'box', 'class' => 'ic-xl'])</div>
                <h3>Stok</h3>
                <p>Kelola item &amp; harga, tambah/kurangi jumlah, lengkap dengan log perubahan &amp; export Excel.</p>
            </a>
            <a href="{{ route('admin.rooms.index') }}" class="card card-link accent-orange">
                <div class="icon">@include('partials.icon', ['name' => 'bed', 'class' => 'ic-xl'])</div>
                <h3>Kamar</h3>
                <p>Kalender hunian: jumlah kamar terisi per tanggal beserta detail tiap tamu.</p>
            </a>
            <a href="{{ route('admin.tamu.index') }}" class="card card-link accent-orange">
                <div class="icon">@include('partials.icon', ['name' => 'users', 'class' => 'ic-xl'])</div>
                <h3>Riwayat Tamu</h3>
                <p>Profil tamu berulang: jumlah kunjungan, kamar, dan malam menginapnya.</p>
            </a>
            @if (Auth::user()->bolehKelolaAkun())
                <a href="{{ route('admin.akun.index') }}" class="card card-link">
                    <div class="icon">@include('partials.icon', ['name' => 'key-card', 'class' => 'ic-xl'])</div>
                    <h3>Kelola Akun</h3>
                    <p>Tambah akun, ganti kata sandi, dan nonaktifkan staff yang keluar.</p>
                </a>
                {{-- Keuangan hanya untuk superadmin: ini bukan data operasional,
                     melainkan pembanding untuk MENGUJI data operasional. --}}
                <a href="{{ route('admin.keuangan.index') }}" class="card card-link">
                    <div class="icon">@include('partials.icon', ['name' => 'chart', 'class' => 'ic-xl'])</div>
                    <h3>Keuangan Bulanan</h3>
                    <p>Setoran nyata per bulan, disandingkan dengan pendapatan menurut catatan kamar.</p>
                </a>
            @endif
            <a href="{{ route('admin.invoices.index') }}" class="card card-link">
                <div class="icon">@include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-xl'])</div>
                <h3>Invoice</h3>
                <p>Ringkasan tagihan bulan berjalan: jumlah, nilai, dan yang belum lunas.</p>
            </a>
            <a href="{{ route('admin.absensi.index') }}" class="card card-link accent-orange">
                <div class="icon">@include('partials.icon', ['name' => 'users', 'class' => 'ic-xl'])</div>
                <h3>Absensi</h3>
                <p>Kehadiran per shift beserta selfie dan foto kartu kunci di laci.</p>
            </a>
            <a href="{{ route('admin.reports.index') }}" class="card card-link">
                @if ($openReports > 0)
                    <span class="count">{{ $openReports }} Open</span>
                @endif
                <div class="icon">@include('partials.icon', ['name' => 'inbox', 'class' => 'ic-xl'])</div>
                <h3>Laporan</h3>
                <p>Baca tiket dari staff, lalu tandai selesai bila sudah ditindaklanjuti.</p>
            </a>
        @else
            <a href="{{ route('staff.stocks.index') }}" class="card card-link">
                <div class="icon">@include('partials.icon', ['name' => 'box', 'class' => 'ic-xl'])</div>
                <h3>Stok</h3>
                <p>Tambah atau kurangi jumlah stok harian — langsung tercatat di panel admin.</p>
            </a>
            <a href="{{ route('staff.rooms.index') }}" class="card card-link accent-orange">
                <div class="icon">@include('partials.icon', ['name' => 'bed', 'class' => 'ic-xl'])</div>
                <h3>Kamar</h3>
                <p>Catat booking (bisa beberapa kamar sekaligus) lalu check-in tamu saat tiba.</p>
            </a>
            <a href="{{ route('staff.absensi.index') }}" class="card card-link accent-orange">
                <div class="icon">@include('partials.icon', ['name' => 'clock', 'class' => 'ic-xl'])</div>
                <h3>Jadwal &amp; Absensi</h3>
                <p>Susun jadwal shift H-1 dan absen dengan foto saat mulai bertugas.</p>
            </a>

            <a href="{{ route('staff.invoices.index') }}" class="card card-link">
                <div class="icon">@include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-xl'])</div>
                <h3>Invoice</h3>
                <p>Buat dan cetak tagihan tamu lengkap dengan kop hotel.</p>
            </a>
            <a href="{{ route('staff.reports.index') }}" class="card card-link">
                <div class="icon">@include('partials.icon', ['name' => 'inbox', 'class' => 'ic-xl'])</div>
                <h3>Laporan</h3>
                <p>Kirim tiket kendala atau kebutuhan ke admin dan pantau statusnya.</p>
            </a>
        @endif
    </div>

    @if (Auth::user()->isAdmin())
        @include('partials.jadwal-hari-ini', ['vertikal' => true])
    @endif
    </div>
@endsection
