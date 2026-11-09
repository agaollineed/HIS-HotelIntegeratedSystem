@extends('layouts.panel')

@section('title', 'Kalender Kamar')

@push('head')
<style>
    /* ---------- Kalender ---------- */
    .cal-card { padding: 18px; margin-bottom: 26px; }
    .cal-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .cal-title { font-size: 1.15rem; font-weight: 800; letter-spacing: -.02em; }
    .cal-nav a {
        display: inline-flex; align-items: center; justify-content: center;
        width: 38px; height: 38px; border-radius: 999px; text-decoration: none;
        color: var(--text); border: 1px solid var(--border-2); font-weight: 700;
        transition: border-color .2s, color .2s;
    }
    .cal-nav a:hover { border-color: var(--accent); color: var(--accent); }

    .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
    .cal-h {
        text-align: center; padding: 6px 0 10px;
        font-size: .7rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--muted);
    }
    .cal-blank { min-height: 74px; }
    .cal-day {
        position: relative; min-height: 74px; padding: 8px;
        display: flex; flex-direction: column; justify-content: space-between; align-items: flex-start;
        background: var(--surface); border: 1px solid var(--border-2); border-radius: 12px;
        text-decoration: none; color: var(--text);
        transition: transform .12s, border-color .15s, background .15s, box-shadow .15s;
    }
    .cal-day:hover { border-color: var(--border); background: var(--hover); transform: translateY(-2px); }
    .cal-day .d { font-variant-numeric: tabular-nums; font-weight: 700; font-size: .88rem; }
    .cal-day .cnt {
        font-variant-numeric: tabular-nums; font-size: .7rem; font-weight: 700;
        padding: 2px 9px; border-radius: 999px;
        background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border);
    }
    .cal-day.today { box-shadow: 0 0 0 2px var(--accent) inset; }
    .cal-day.selected { background: var(--accent-soft); border-color: var(--border); }
    .cal-note { margin: 12px 2px 0; font-size: .8rem; color: var(--muted); }

    /* ---------- Panel tanggal terpilih ---------- */
    .day-card { padding: 22px; }
    .day-head h2 { margin: 0 0 18px; font-size: 1.15rem; font-weight: 800; letter-spacing: -.02em; }

    /* Dua angka besar */
    .stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 22px; }
    @media (max-width: 720px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .stat { border: 1px solid var(--border-2); border-radius: 14px; padding: 18px; background: var(--surface); }
    .stat .n { font-variant-numeric: tabular-nums; font-size: 2.1rem; font-weight: 700; line-height: 1; }
    .stat .n small { font-size: 1rem; color: var(--muted); }
    .stat .l { margin-top: 7px; font-size: .75rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
    .stat.isi    { border-color: var(--border); }
    .stat.isi .n { color: var(--accent); }
    .stat.kosong    { border-color: var(--border-orange); }
    .stat.kosong .n { color: var(--accent-2); }
    .stat.ci    { border-color: rgba(74,222,128,.28); }
    .stat.ci .n { color: var(--ok); }
    .stat.belum    { border-color: var(--alert-line); }
    .stat.belum .n { color: var(--alert); }

    .co-ring {
        display: inline-flex; align-items: center; gap: 8px; margin: -8px 0 20px;
        border: 1px solid var(--border-2); border-radius: 999px; padding: 6px 14px;
        font-size: .84rem; color: var(--muted);
    }
    .co-ring b { color: var(--text); font-variant-numeric: tabular-nums; }

    /* Daftar entri kamar terisi */
    .head-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .src-badge {
        display: inline-block; padding: 2px 10px; border-radius: 999px;
        font-size: .64rem; font-weight: 700; letter-spacing: .04em; flex: 0 0 auto;
    }
    .src-badge.umum  { background: var(--hover); color: var(--muted); border: 1px solid var(--border-2); }
    .src-badge.tiket { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-orange); }
    .src-badge.diskon { background: rgba(74,222,128,.12); color: var(--ok); border: 1px solid rgba(74,222,128,.28); font-variant-numeric: tabular-nums; }

    /* ---------- Pengajuan perubahan dari staff ----------
       Mengikuti pola kartu .ent di halaman ini: permukaan --surface, garis
       --border-2, dan penanda ungu di tepi kiri. */
    .req-box { margin-bottom: 26px; }
    .req-box > h2 {
        display: flex; align-items: center; gap: 9px; margin: 0 0 12px;
        font-size: .72rem; letter-spacing: .09em; text-transform: uppercase;
        color: var(--muted); font-weight: 700;
    }
    .req-box > h2 .jml {
        background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border);
        border-radius: 999px; padding: 2px 9px; font-size: .7rem; letter-spacing: 0;
    }
    .req {
        background: var(--surface); border: 1px solid var(--border-2);
        border-left: 3px solid var(--accent); border-radius: 14px;
        padding: 16px 18px; margin-bottom: 10px;
    }
    .req:last-child { margin-bottom: 0; }
    .req-head { display: flex; gap: 12px; align-items: baseline; flex-wrap: wrap; margin-bottom: 12px; }
    .req-head .jenis { font-weight: 800; font-size: .96rem; }
    .req-head .siapa { color: var(--muted); font-size: .8rem; }
    .req-diff {
        display: grid; gap: 6px; margin-bottom: 12px;
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 12px; padding: 12px 14px;
    }
    .req-diff .baris {
        display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap;
        font-size: .88rem; font-variant-numeric: tabular-nums;
    }
    .req-diff .apa {
        min-width: 104px; font-size: .7rem; letter-spacing: .06em;
        text-transform: uppercase; color: var(--muted);
    }
    .req-diff .dari { text-decoration: line-through; color: var(--muted); }
    .req-diff .jadi { font-weight: 700; color: var(--accent); }
    .req-alasan {
        border-left: 2px solid var(--border-2); padding: 2px 0 2px 12px; margin-bottom: 14px;
        color: var(--muted); font-size: .86rem; line-height: 1.6;
    }
    .req-usang {
        display: flex; gap: 10px; align-items: flex-start;
        background: var(--accent-2-soft); border: 1px solid var(--border-violet); color: var(--accent-2);
        border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; font-size: .84rem; line-height: 1.55;
    }
    .req-tukar {
        display: flex; gap: 10px; align-items: flex-start;
        background: var(--accent-2-soft); border: 1px solid var(--border-violet); color: var(--accent-2);
        border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; font-size: .84rem; line-height: 1.55;
    }
    .req-tukar b { font-weight: 800; }
    .req-tukar.galat { background: var(--alert-soft); border-color: var(--alert-line); color: var(--alert); }
    .req-aksi { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .req-aksi form { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .req-aksi input[type=text] {
        font-family: inherit; font-size: .85rem; padding: 9px 12px; min-width: 220px;
        background: var(--bg); color: var(--text);
        border: 1px solid var(--border-2); border-radius: 10px;
    }
    .req-aksi input[type=text]::placeholder { color: var(--muted); }
    .req-aksi input[type=text]:focus { outline: none; border-color: var(--accent); }
    .btn-tolak { background: transparent; color: var(--alert); border: 1px solid var(--alert-line); }
    .btn-tolak:hover { background: var(--alert-soft); }
    .ent-aksi { margin-bottom: 16px; }
    .ent-list { display: flex; flex-direction: column; gap: 10px; }
    .ent { border: 1px solid var(--border-2); border-radius: 14px; background: var(--surface); overflow: hidden; }
    .ent.pending { border-left: 3px solid var(--accent-2); }
    .ent.done    { border-left: 3px solid var(--accent); }
    .ent-top {
        width: 100%; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
        padding: 14px 16px; background: transparent; border: none; cursor: pointer;
        font-family: inherit; text-align: left; color: var(--text);
        transition: background .15s;
    }
    .ent-top:hover { background: var(--hover); }
    .ent-no { font-variant-numeric: tabular-nums; font-weight: 700; font-size: .98rem; }
    .ent-guest { flex: 1 1 160px; font-weight: 600; }
    .ent-chev { color: var(--muted); display: inline-flex; transition: transform .18s; }
    .ent.open .ent-chev { transform: rotate(180deg); }

    .tipe-badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: .66rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .tipe-king   { background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border); }
    .tipe-twin   { background: var(--info-soft);  color: var(--info);       border: 1px solid var(--info-line); }
    .tipe-family { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-orange); }
    /* Pesanan banyak kamar: satu kotak, jadi butuh penanda jumlahnya. */
    .tipe-grup   { background: var(--accent-soft); color: var(--accent-600); border: 1px solid var(--border); }

    .bf-badge {
        display: inline-block; padding: 3px 9px; border-radius: 999px;
        font-size: .64rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    }
    .bf-badge.ada   { background: rgba(74,222,128,.12); color: var(--ok); border: 1px solid rgba(74,222,128,.28); }
    .bf-badge.tanpa { background: rgba(255,255,255,.04); color: var(--muted); border: 1px solid var(--border-2); }
    .bf-badge.campur { background: var(--warn-soft); color: var(--warn); border: 1px solid var(--warn-line); }
    .bf-ring {
        display: flex; align-items: center; gap: 9px; margin-bottom: 16px;
        border: 1px solid var(--border-2); border-radius: 12px; padding: 11px 14px;
        background: var(--surface); font-size: .86rem; color: var(--muted);
    }
    .bf-ring b { color: var(--ok); font-variant-numeric: tabular-nums; }

    .src-badge.dp { background: var(--warn-soft); color: var(--warn); border: 1px solid var(--warn-line); }
    .src-badge.belum { background: var(--alert-soft); color: var(--alert); border: 1px solid var(--alert-line); }
    .src-badge.extend { background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border); }
    .menu-edit.dalam[open] { flex-basis: 100%; }

    .st-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; font-size: .74rem; font-weight: 700; }
    .st-done    { background: var(--accent-soft); color: var(--accent);       border: 1px solid var(--border); }
    .st-pending { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-orange); }
    .st-out  { background: rgba(74,222,128,.12); color: var(--ok); border: 1px solid rgba(74,222,128,.28); }
    .st-awal { background: var(--alert-soft); color: var(--alert); border: 1px solid var(--alert-line); }
    .st-telat { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-orange); }
    /* Sebagian kamar sudah check-in, sebagian belum — keadaan yang baru bisa
       terjadi sejak kamar dalam satu pesanan boleh berbeda tanggalnya. */
    .st-sebagian { background: var(--warn-soft); color: var(--warn); border: 1px solid var(--warn-line); }

    /* ---------- Rincian per kamar di dalam kotak pesanan ----------
       Satu kotak enak dibaca, tetapi mengubah booking tetap per kamar:
       101 boleh diperpanjang tanpa menyeret 103. */
    .kamar-list { display: grid; gap: 10px; margin: 14px 0 4px; }
    .kmr {
        border: 1px solid var(--border-2); border-radius: 12px;
        background: var(--surface-2); padding: 12px 14px;
    }
    .kmr.done    { border-color: var(--border); }
    .kmr.out     { opacity: .72; }
    .kmr-head { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; margin-bottom: 7px; }
    .kmr-no { font-variant-numeric: tabular-nums; font-weight: 800; font-size: 1rem; }
    .kmr-harga { margin-left: auto; font-size: .78rem; color: var(--muted); }
    .kmr-tgl { font-size: .84rem; color: var(--text); display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .kmr-st { font-size: .7rem; font-weight: 700; padding: 2px 9px; border-radius: 999px; }
    .kmr-st.done    { background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border); }
    .kmr-st.pending { background: var(--warn-soft); color: var(--warn); border: 1px solid var(--warn-line); }
    .kmr-st.out     { background: var(--ok-soft); color: var(--ok); border: 1px solid var(--ok-line); }
    .kmr-aksi { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 11px; }
    @media (max-width: 560px) {
        .kmr-harga { margin-left: 0; width: 100%; }
    }

    /* Detail (accordion) */
    .ent-detail { padding: 4px 16px 18px; border-top: 1px solid var(--border-2); }
    .ent-detail[hidden] { display: none !important; }
    .ent-detail dl {
        margin: 14px 0 0; display: grid; grid-template-columns: 132px 1fr;
        row-gap: 9px; column-gap: 12px; font-size: .9rem;
    }
    .ent-detail dt { color: var(--muted); font-size: .72rem; letter-spacing: .05em; text-transform: uppercase; align-self: center; }
    .ent-detail dd { margin: 0; font-weight: 600; }
    .ent-detail dd.mono { font-variant-numeric: tabular-nums; }
    .ktp-wrap { margin-top: 14px; }
    .ktp-wrap img {
        width: 220px; max-width: 100%; border-radius: 12px; cursor: zoom-in;
        border: 1px solid var(--border-2); box-shadow: 0 16px 34px -18px var(--accent-glow);
    }
    .ktp-none { margin-top: 14px; border: 1px dashed var(--border-orange); border-radius: 12px; padding: 14px; text-align: center; color: var(--muted); font-size: .84rem; flex: 1 1 200px; }

    /* Dua bukti berdampingan: KTP saat datang, kartu saat pergi. */
    .foto-row { display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-start; }
    .foto-item { margin-top: 14px; }
    .foto-lbl { font-size: .7rem; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); margin-bottom: 7px; }
    .foto-item img {
        width: 220px; max-width: 100%; border-radius: 12px; cursor: zoom-in; display: block;
        border: 1px solid var(--border-2); box-shadow: 0 16px 34px -18px var(--accent-glow);
    }

    .ent.out { border-left: 3px solid var(--ok); }
    .ent.awal { border-left: 3px solid var(--alert); }
    .ent.telat { border-left: 3px solid var(--accent-2-600); }

    .day-empty { text-align: center; color: var(--muted); padding: 34px 20px; }

    @media (max-width: 640px) {
        .cal-day, .cal-blank { min-height: 58px; }
        .cal-day .cnt { padding: 1px 7px; }
        .ent-detail dl { grid-template-columns: 106px 1fr; }
    }
</style>
@endpush

@section('content')
    @if (($permintaan ?? collect())->isNotEmpty())
        <div class="req-box">
            <h2>
                @include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-sm'])
                Pengajuan Perubahan dari Staff
                <span class="jml">{{ $permintaan->count() }}</span>
            </h2>

            @foreach ($permintaan as $r)
                <div class="req">
                    <div class="req-head">
                        <span class="jenis">{{ $r->labelJenis() }}</span>
                        <span class="siapa">
                            kamar {{ $r->booking?->room?->nomor ?? '—' }} · {{ $r->booking?->nama_tamu }}
                            · diajukan {{ $r->pengaju?->name ?? '—' }},
                            {{ $r->created_at->translatedFormat('j M Y H:i') }}
                        </span>
                    </div>

                    @if ($r->usang())
                        <div class="req-usang">
                            Booking-nya sudah berubah sejak pengajuan ini dibuat — tidak ada lagi
                            yang akan berubah bila disetujui.
                        </div>
                    @else
                        <div class="req-diff">
                            @foreach ($r->perubahan() as $u)
                                <div class="baris">
                                    <span class="apa">{{ $u['label'] }}</span>
                                    <span class="dari">{{ $u['dari'] }}</span>
                                    <span>&rarr;</span>
                                    <span class="jadi">{{ $u['jadi'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Pindah ke kamar terisi = tukar kamar. Admin diberi tahu siapa
                         yang ikut bergeser (atau kenapa tidak bisa) SEBELUM menekan setujui. --}}
                    {{-- Direset tiap kartu: variabel Blade bertahan antar putaran. --}}
                    @php $rencana = $r->usang() ? null : $r->rencanaPindah(); @endphp
                    @if ($rencana !== null)
                        @if ($rencana->galat !== null)
                            <div class="req-tukar galat">
                                <span><b>Belum bisa disetujui.</b> {{ $rencana->galat }}</span>
                            </div>
                        @else
                            @if ($rencana->menukar())
                                <div class="req-tukar">
                                    <span><b>Tukar kamar.</b> {{ $rencana->keteranganTukar() }} Berlaku otomatis bila disetujui.</span>
                                </div>
                            @endif
                            @if ($rencana->selisih() !== 0)
                                @php $pv = $rencana->pratinjauBayar(); @endphp
                                <div class="req-tukar">
                                    <span>
                                        <b>Tagihan berubah.</b>
                                        Tarif {{ \App\Models\Booking::rupiah($rencana->tarifLama()) }} &rarr;
                                        {{ \App\Models\Booking::rupiah($rencana->tarifBaru()) }} per malam
                                        untuk {{ $rencana->malamPindah() }} malam
                                        ({{ $rencana->selisih() > 0 ? '+' : '−' }}{{ \App\Models\Booking::rupiah(abs($rencana->selisih())) }}).
                                        Tagihan pesanan {{ \App\Models\Booking::rupiah($pv['tagihan_lama']) }} &rarr;
                                        {{ \App\Models\Booking::rupiah($pv['tagihan_baru']) }},
                                        sudah dibayar {{ \App\Models\Booking::rupiah($pv['dibayar']) }}.
                                        @if ($pv['sisa_baru'] > 0)
                                            Sisa {{ \App\Models\Booking::rupiah($pv['sisa_baru']) }} ditagih saat check-in atau check-out.
                                        @elseif ($pv['dibayar'] > $pv['tagihan_baru'])
                                            Tamu kelebihan bayar {{ \App\Models\Booking::rupiah($pv['dibayar'] - $pv['tagihan_baru']) }}.
                                        @endif
                                    </span>
                                </div>
                            @endif
                        @endif
                    @endif

                    <div class="req-alasan">{{ $r->alasan }}</div>

                    <div class="req-aksi">
                        <form method="POST" action="{{ route('admin.perubahan.setujui', $r) }}">
                            @csrf
                            <button type="submit" class="btn btn-accent btn-sm">
                                @include('partials.icon', ['name' => 'check', 'class' => 'ic-xs']) Setujui &amp; Terapkan
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.perubahan.tolak', $r) }}">
                            @csrf
                            <input type="text" name="catatan_admin" maxlength="500"
                                   placeholder="Alasan penolakan…" required>
                            <button type="submit" class="btn btn-tolak btn-sm">Tolak</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="page-head head-row">
        <div><h1>Kalender Kamar</h1></div>
        <div style="display:flex; gap:10px; flex-wrap:wrap">
            <a href="{{ route('admin.rooms.create') }}" class="btn btn-accent">
                @include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm']) Tambah Booking
            </a>
            <a href="{{ route('admin.rooms.export', ['bulan' => $month->format('Y-m')]) }}" class="btn btn-ghost">
                @include('partials.icon', ['name' => 'download', 'class' => 'ic-sm']) Unduh Excel
            </a>
            @include('partials.tombol-purge', [
                'action' => route('admin.purge.bookings'),
                'bulan'  => $month->format('Y-m'),
                'label'  => 'booking kamar',
            ])
        </div>
    </div>

    {{-- Cari tamu lintas tanggal --}}
    @include('partials.cari-tamu')

    {{-- 1. Kalender bulanan --}}
    <div class="card cal-card">
        <div class="cal-top">
            <div class="cal-nav">
                <a href="{{ route('admin.rooms.index', ['bulan' => $prevMonth]) }}" title="Bulan sebelumnya">@include('partials.icon', ['name' => 'chevron-left', 'class' => 'ic'])</a>
            </div>
            <div class="cal-title">{{ $month->translatedFormat('F Y') }}</div>
            <div class="cal-nav">
                <a href="{{ route('admin.rooms.index', ['bulan' => $nextMonth]) }}" title="Bulan berikutnya">@include('partials.icon', ['name' => 'chevron-right', 'class' => 'ic'])</a>
            </div>
        </div>

        @php
            $daysInMonth = $month->daysInMonth;
            $offset = $month->copy()->startOfMonth()->dayOfWeekIso - 1; // Senin = kolom pertama
        @endphp

        <div class="cal-grid" id="cal-grid">
            @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $h)
                <div class="cal-h">{{ $h }}</div>
            @endforeach

            @for ($i = 0; $i < $offset; $i++)
                <div class="cal-blank"></div>
            @endfor

            @for ($d = 1; $d <= $daysInMonth; $d++)
                @php
                    $tgl = $month->copy()->day($d);
                    $key = $tgl->toDateString();
                    $n   = $terisiPerHari->get($key, 0);
                @endphp
                <a href="{{ route('admin.rooms.index', ['bulan' => $month->format('Y-m'), 'tanggal' => $key]) }}"
                   class="cal-day {{ $tgl->isToday() ? 'today' : '' }} {{ $key === $selected->toDateString() ? 'selected' : '' }}"
                   data-day-url="{{ route('admin.rooms.day', ['tanggal' => $key]) }}"
                   data-tanggal="{{ $key }}"
                   @if ($n > 0) title="{{ $n }} kamar terisi" @endif>
                    <span class="d">{{ $d }}</span>
                    @if ($n > 0)
                        <span class="cnt">{{ $n }}</span>
                    @endif
                </a>
            @endfor
        </div>
        <p class="cal-note">Angka pada sel = jumlah kamar yang terisi malam itu. Kotak bergaris ungu = hari ini.</p>
    </div>

    {{-- 2 & 3. Ringkasan + daftar entri tanggal terpilih --}}
    <div class="card day-card" id="day-panel">
        @include('admin.rooms._day')
    </div>

    @include('partials.alarm-checkout')
@endsection

@push('scripts')
<script>
    // Klik tanggal -> ganti panel via fetch (fallback: link biasa tetap bekerja).
    (function () {
        const grid = document.getElementById('cal-grid');
        const panel = document.getElementById('day-panel');
        if (!grid || !panel) return;

        grid.addEventListener('click', function (e) {
            const cell = e.target.closest('a.cal-day');
            if (!cell) return;

            e.preventDefault();

            fetch(cell.dataset.dayUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => { if (!r.ok) throw new Error(); return r.text(); })
                .then(html => {
                    panel.innerHTML = html;
                    if (window.jalankanFilter) jalankanFilter();
                    grid.querySelectorAll('.cal-day.selected').forEach(el => el.classList.remove('selected'));
                    cell.classList.add('selected');
                    history.replaceState(null, '', cell.getAttribute('href'));
                    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                })
                .catch(() => { window.location.href = cell.getAttribute('href'); });
        });
    })();

    // Klik entri -> buka/tutup detail (delegasi: isi panel bisa berganti via fetch).
    (function () {
        const panel = document.getElementById('day-panel');
        if (!panel) return;

        panel.addEventListener('click', function (e) {
            const top = e.target.closest('.ent-top');
            if (!top) return;

            const ent = top.closest('.ent');
            const detail = ent.querySelector('.ent-detail');
            if (!detail) return;

            const buka = detail.hidden;
            detail.hidden = !buka;
            ent.classList.toggle('open', buka);
            top.setAttribute('aria-expanded', buka ? 'true' : 'false');
        });
    })();
</script>
@endpush
