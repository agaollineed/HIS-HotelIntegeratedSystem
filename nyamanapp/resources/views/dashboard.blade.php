@extends('layouts.panel')

@section('title', 'Dashboard')

@php
    $admin = Auth::user()->isAdmin();

    // Pintasan fitur untuk staff, dalam satu carousel. Admin dan superadmin
    // tidak memakainya: semua fiturnya sudah ada di menu samping, dan
    // berandanya berisi grafik pendapatan serta jadwal hari ini.
    // Setiap baris: [judul, ikon, url, kalimat singkat, lencana (opsional)].
    $slides = $admin ? [] : [
        ['Kamar', 'bed', route('staff.rooms.index'), 'Catat booking, check-in, pindah kamar, dan check-out.', null],
        ['Invoice', 'clipboard', route('staff.invoices.index'), 'Buat dan cetak tagihan tamu.', null],
        ['Stok', 'box', route('staff.stocks.index'), 'Tambah atau kurangi jumlah stok harian.', null],
        ['Jadwal & Absensi', 'clock', route('staff.absensi.index'), 'Susun shift H-1 dan absen dengan foto.', null],
        ['Laporan', 'inbox', route('staff.reports.index'), 'Kirim kendala ke admin dan pantau statusnya.', null],
    ];
@endphp

@push('head')
<style>
    .dash-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(290px, 330px); gap: 18px; align-items: start; }
    .dash-grid > * { min-width: 0; }
    .tautan-laporan { color: var(--warn); font-weight: 700; text-decoration: none; }
    .tautan-laporan:hover { text-decoration: underline; }

    /* Lambaian tangan di sapaan */
    .lambai { display: inline-block; transform-origin: 70% 80%; animation: lambai 3.2s ease-in-out infinite; }
    @keyframes lambai {
        0%, 60%, 100% { transform: rotate(0); }
        65% { transform: rotate(16deg); } 70% { transform: rotate(-8deg); }
        75% { transform: rotate(14deg); } 80% { transform: rotate(-4deg); } 85% { transform: rotate(10deg); }
    }

    /* ---------- Carousel pintasan ---------- */
    .karosel { position: relative; padding: 18px 18px 14px; margin-bottom: 18px; }
    .karosel-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
    .karosel-head h2 { margin: 0; font-size: .72rem; letter-spacing: .09em; text-transform: uppercase; color: var(--muted); font-weight: 800; }
    .karosel-nav { display: flex; gap: 6px; }
    .karosel-nav button {
        width: 34px; height: 34px; border-radius: 999px; cursor: pointer; display: grid; place-items: center;
        background: rgba(255,255,255,.03); color: var(--text); border: 1px solid var(--border-2);
    }
    .karosel-nav button:hover { border-color: var(--accent); color: var(--accent); }
    .karosel-jalur {
        display: grid; grid-auto-flow: column; grid-auto-columns: calc((100% - 24px) / 3); gap: 12px;
        overflow-x: auto; scroll-snap-type: x mandatory; scroll-behavior: smooth; scrollbar-width: none;
        padding-bottom: 2px;
    }
    .karosel-jalur::-webkit-scrollbar { display: none; }
    .slide {
        scroll-snap-align: start; position: relative; display: flex; flex-direction: column; gap: 10px;
        padding: 18px; border-radius: 16px; text-decoration: none; color: var(--text);
        background: linear-gradient(160deg, rgba(139,92,246,.16), rgba(139,92,246,.03));
        border: 1px solid var(--border); min-height: 150px;
        transition: transform .18s, box-shadow .18s, border-color .18s;
    }
    .slide:hover { transform: translateY(-3px); border-color: var(--accent); box-shadow: 0 22px 44px -26px var(--accent-glow); }
    .slide .ikon {
        width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center;
        background: var(--accent-soft); border: 1px solid var(--border); color: var(--accent);
    }
    .slide b { font-size: 1.02rem; font-weight: 800; letter-spacing: -.01em; }
    .slide span.t { font-size: .84rem; color: var(--muted); line-height: 1.45; }
    .slide .lencana {
        position: absolute; top: 14px; right: 14px; font-size: .7rem; font-weight: 800;
        padding: 3px 10px; border-radius: 999px; background: var(--grad); color: #fff;
    }
    .karosel-titik { display: flex; justify-content: center; gap: 6px; margin-top: 12px; }
    .karosel-titik button {
        width: 7px; height: 7px; padding: 0; border-radius: 999px; border: 0; cursor: pointer;
        background: var(--border); transition: width .2s, background .2s;
    }
    .karosel-titik button[aria-current="true"] { width: 22px; background: var(--accent); }

    @media (max-width: 1100px) {
        .dash-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 760px) {
        .karosel-jalur { grid-auto-columns: calc((100% - 12px) / 2); }
    }
    @media (max-width: 520px) {
        .karosel-jalur { grid-auto-columns: 86%; }
    }
    @media (prefers-reduced-motion: reduce) { .lambai { animation: none; } .karosel-jalur { scroll-behavior: auto; } }
</style>
@endpush

@section('content')
    <div class="page-head">
        <h1>Hai, {{ Auth::user()->name }} <span class="lambai" aria-hidden="true">👋🏻</span></h1>
        <p>
            {{ now()->translatedFormat('l, j F Y') }}
            @if ($admin && $openReports > 0)
                · <a href="{{ route('admin.reports.index') }}" class="tautan-laporan">{{ $openReports }} laporan terbuka</a>
            @endif
        </p>
    </div>

    @include('partials.alarm-checkout')

    @unless ($admin)
    {{-- Pintasan fitur dalam satu carousel: bergeser sendiri, berhenti saat
         disentuh atau diarahkan kursor, dan bisa digeser dengan jari. --}}
    <section class="card karosel" id="karosel" aria-roledescription="carousel" aria-label="Pintasan fitur">
        <div class="karosel-head">
            <h2>Pintasan</h2>
            <div class="karosel-nav">
                <button type="button" data-arah="-1" aria-label="Sebelumnya">@include('partials.icon', ['name' => 'chevron-left', 'class' => 'ic-xs'])</button>
                <button type="button" data-arah="1" aria-label="Berikutnya">@include('partials.icon', ['name' => 'chevron-right', 'class' => 'ic-xs'])</button>
            </div>
        </div>
        <div class="karosel-jalur" id="karosel-jalur">
            @foreach ($slides as [$judul, $ikon, $url, $kalimat, $lencana])
                <a href="{{ $url }}" class="slide" aria-roledescription="slide" aria-label="{{ $judul }}">
                    @if ($lencana)<span class="lencana">{{ $lencana }}</span>@endif
                    <span class="ikon">@include('partials.icon', ['name' => $ikon, 'class' => 'ic'])</span>
                    <b>{{ $judul }}</b>
                    <span class="t">{{ $kalimat }}</span>
                </a>
            @endforeach
        </div>
        <div class="karosel-titik" id="karosel-titik"></div>
    </section>
    @endunless

    @if ($admin)
        <div class="dash-grid">
            <div>@include('admin.revenue.chart')</div>
            @include('partials.jadwal-hari-ini', ['vertikal' => true])
        </div>
    @endif
@endsection

@push('scripts')
<script>
(function () {
    const akar  = document.getElementById('karosel');
    const jalur = document.getElementById('karosel-jalur');
    const titik = document.getElementById('karosel-titik');
    if (!akar || !jalur) return;

    const slides = Array.from(jalur.children);
    const langkah = () => slides.length > 1 ? slides[1].offsetLeft - slides[0].offsetLeft : jalur.clientWidth;
    const tampil  = () => Math.max(1, Math.round(jalur.clientWidth / langkah()));
    const halaman = () => Math.max(1, slides.length - tampil() + 1);
    const posisi  = () => Math.round(jalur.scrollLeft / langkah());

    function ke(i) {
        const n = halaman();
        i = ((i % n) + n) % n;
        jalur.scrollTo({ left: i * langkah() });
    }

    function gambarTitik() {
        const n = halaman();
        titik.innerHTML = '';
        for (let i = 0; i < n; i++) {
            const b = document.createElement('button');
            b.type = 'button';
            b.setAttribute('aria-label', 'Ke pintasan ' + (i + 1));
            b.addEventListener('click', () => ke(i));
            titik.appendChild(b);
        }
        tandai();
    }

    function tandai() {
        const p = Math.min(posisi(), halaman() - 1);
        Array.from(titik.children).forEach((b, i) => b.setAttribute('aria-current', i === p ? 'true' : 'false'));
    }

    akar.querySelectorAll('[data-arah]').forEach(b =>
        b.addEventListener('click', () => ke(posisi() + parseInt(b.dataset.arah, 10))));
    jalur.addEventListener('scroll', () => window.requestAnimationFrame(tandai), { passive: true });
    window.addEventListener('resize', gambarTitik);

    // Bergeser sendiri tiap 5 detik; berhenti selama disentuh/diarahkan kursor
    // dan bila pengguna meminta gerak dikurangi.
    const diam = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let jeda = false;
    ['mouseenter', 'focusin', 'touchstart'].forEach(e => akar.addEventListener(e, () => { jeda = true; }, { passive: true }));
    ['mouseleave', 'focusout', 'touchend'].forEach(e => akar.addEventListener(e, () => { jeda = false; }, { passive: true }));
    if (!diam) setInterval(() => { if (!jeda && !document.hidden) ke(posisi() + 1); }, 5000);

    gambarTitik();
})();
</script>
@endpush
