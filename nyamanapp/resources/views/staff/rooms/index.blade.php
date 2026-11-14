@extends('layouts.panel')

@section('title', 'Kamar')

@push('head')
<style>
    a.wa-link { color: var(--ok); font-weight: 700; text-decoration: none; }
    a.wa-link:hover { text-decoration: underline; }
    .head-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }

    /* ---------- Kalender bulanan ---------- */
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
    .cal-blank { min-height: 64px; }
    .cal-day {
        position: relative; min-height: 64px; padding: 8px;
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

    /* ---------- Panel daftar booking tanggal terpilih ---------- */
    .day-card { padding: 20px; }
    .day-head h2 { margin: 0 0 4px; font-size: 1.15rem; font-weight: 800; letter-spacing: -.02em; }

    /* Ringkasan hunian: satu baris kecil */
    .occ-line {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        margin: 8px 0 18px; font-size: .82rem; color: var(--muted);
    }
    .occ-line .pill {
        display: inline-flex; align-items: center; gap: 6px;
        border-radius: 999px; padding: 5px 12px; border: 1px solid var(--border-2);
        background: var(--surface);
    }
    .occ-line .pill b { font-variant-numeric: tabular-nums; }
    .occ-line .pill.isi b   { color: var(--accent); }
    .occ-line .pill.kosong b { color: var(--accent-2); }

    /* Kartu grup booking */
    .bk-list { display: flex; flex-direction: column; gap: 12px; }
    .bk {
        border: 1px solid var(--border-2); border-radius: 14px; padding: 16px;
        background: var(--surface); display: flex; gap: 16px; justify-content: space-between; flex-wrap: wrap;
    }
    .bk.pending { border-left: 3px solid var(--accent-2); }
    .bk.done    { border-left: 3px solid var(--accent); }
    .bk.out     { border-left: 3px solid var(--ok); }
    .bk-main { flex: 1 1 320px; min-width: 0; }
    .bk-rooms { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 8px; }
    .bk-room {
        display: inline-flex; align-items: center; gap: 7px;
        border: 1px solid var(--border-2); border-radius: 10px; padding: 5px 10px; background: var(--bg);
    }
    .bk-room .no { font-variant-numeric: tabular-nums; font-weight: 700; font-size: .9rem; }
    .bk-guest { font-weight: 700; font-size: 1rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .sumber-tag {
        display: inline-block; padding: 2px 10px; border-radius: 999px;
        font-size: .66rem; font-weight: 700; letter-spacing: .04em;
    }
    .sumber-tag.umum  { background: var(--hover); color: var(--muted); border: 1px solid var(--border-2); }
    .sumber-tag.tiket { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-orange); }
    .sumber-tag.lunas { background: rgba(74,222,128,.12); color: var(--ok); border: 1px solid rgba(74,222,128,.28); }
    .sumber-tag.belum { background: var(--alert-soft); color: var(--alert); border: 1px solid var(--alert-line); }
    .sumber-tag.diskon { background: rgba(74,222,128,.12); color: var(--ok); border: 1px solid rgba(74,222,128,.28); font-variant-numeric: tabular-nums; }

    /* Penanda bahwa baris ini sedang menunggu keputusan admin. */
    .bk-tertunda {
        display: inline-flex; align-items: center; gap: 7px; margin-top: 8px;
        border: 1px solid var(--border); background: var(--accent-soft); color: var(--accent);
        border-radius: 999px; padding: 4px 12px; font-size: .8rem; font-weight: 600;
    }
    .bk-tertunda b { font-weight: 800; }
    .bk-meta { margin-top: 3px; font-size: .84rem; color: var(--muted); line-height: 1.5; }

    /* Detail booking & pembayaran (bisa dibuka) */
    .bk-detail { margin-top: 10px; border: 1px solid var(--border-2); border-radius: 12px; background: rgba(255,255,255,.02); }
    .bk-detail > summary {
        list-style: none; cursor: pointer; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        padding: 9px 12px; font-size: .82rem; font-weight: 700; color: var(--accent);
    }
    .bk-detail > summary::-webkit-details-marker { display: none; }
    .bk-detail > summary .ringkas { margin-left: auto; color: var(--muted); font-weight: 600; font-variant-numeric: tabular-nums; }
    .bk-detail > summary .ringkas b { color: var(--warn); }
    .bk-detail[open] > summary { border-bottom: 1px solid var(--border-2); }
    .bk-detail-isi { padding: 10px 12px 12px; }
    .bk-tabel { display: grid; gap: 6px; margin-bottom: 10px; }
    .bk-baris {
        display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1.4fr) auto; gap: 10px; align-items: center;
        font-size: .82rem; padding: 7px 10px; border-radius: 10px; background: var(--bg); font-variant-numeric: tabular-nums;
    }
    .bk-baris .km b { font-size: .92rem; margin-right: 4px; }
    .bk-baris .tg { color: var(--muted); }
    .bk-baris .rp { font-weight: 800; }
    .bk-uang { display: grid; grid-template-columns: 120px 1fr; gap: 5px 12px; margin: 0; font-size: .84rem; font-variant-numeric: tabular-nums; }
    .bk-uang dt { color: var(--muted); }
    .bk-uang dd { margin: 0; }
    .bk-uang .hijau { color: var(--ok); }
    .bk-uang .merah { color: var(--warn); }
    .bk-bukti { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; }
    @media (max-width: 640px) { .bk-baris { grid-template-columns: 1fr auto; } .bk-baris .tg { grid-column: 1 / -1; grid-row: 2; } }
    .bk-meta .tel { font-variant-numeric: tabular-nums; }
    .bk-side { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
    .bk-actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }

    .tipe-badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: .66rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .tipe-king   { background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border); }
    .tipe-twin   { background: var(--info-soft);  color: var(--info);       border: 1px solid var(--info-line); }
    .tipe-family { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-orange); }

    .bf-badge {
        display: inline-block; padding: 3px 9px; border-radius: 999px;
        font-size: .66rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    }
    .bf-badge.ada   { background: rgba(74,222,128,.12); color: var(--ok); border: 1px solid rgba(74,222,128,.28); }
    .bf-badge.tanpa { background: rgba(255,255,255,.04); color: var(--muted); border: 1px solid var(--border-2); }
    .occ-line .pill.bf b { color: var(--ok); }

    .st-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 13px; border-radius: 999px; font-size: .76rem; font-weight: 700; }
    .st-done    { background: var(--accent-soft); color: var(--accent);     border: 1px solid var(--border); }
    .st-pending { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-orange); }
    /* Sudah check-out: netral untuk yang wajar, merah untuk yang perlu dilihat. */
    .st-out   { background: rgba(74,222,128,.12); color: var(--ok); border: 1px solid rgba(74,222,128,.28); }
    .st-awal  { background: var(--alert-soft); color: var(--alert); border: 1px solid var(--alert-line); }
    .st-telat { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-violet); }

    .st-sebagian { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-orange); }
    .km-st { font-size: .66rem; font-weight: 700; color: var(--muted); }
    .km-st.keluar { color: var(--ok); }
    .km-st.menginap { color: var(--warn); }

    .day-empty { text-align: center; color: var(--muted); padding: 34px 20px; }

    .sumber-tag.extend { background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border); }

    @media (max-width: 640px) {
        .cal-day, .cal-blank { min-height: 52px; }
        .cal-day .cnt { padding: 1px 7px; }
        .bk-side { align-items: flex-start; }
        .bk-actions { justify-content: flex-start; }
    }
</style>
@endpush

@section('content')
    {{-- 1. Judul + tombol Tambah Kamar (di atas kalender) --}}
    <div class="page-head head-row">
        <div>
            <h1>Kamar</h1>
        </div>
        <a href="{{ route('staff.rooms.create') }}" class="btn btn-accent">@include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm']) Tambah Kamar</a>
    </div>

    {{-- Cari tamu lintas tanggal --}}
    @include('partials.cari-tamu')

    {{-- 2. Kalender bulanan --}}
    <div class="card cal-card">
        <div class="cal-top">
            <div class="cal-nav">
                <a href="{{ route('staff.rooms.index', ['bulan' => $prevMonth]) }}" title="Bulan sebelumnya">@include('partials.icon', ['name' => 'chevron-left', 'class' => 'ic'])</a>
            </div>
            <div class="cal-title">{{ $month->translatedFormat('F Y') }}</div>
            <div class="cal-nav">
                <a href="{{ route('staff.rooms.index', ['bulan' => $nextMonth]) }}" title="Bulan berikutnya">@include('partials.icon', ['name' => 'chevron-right', 'class' => 'ic'])</a>
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
                <a href="{{ route('staff.rooms.index', ['bulan' => $month->format('Y-m'), 'tanggal' => $key]) }}"
                   class="cal-day {{ $tgl->isToday() ? 'today' : '' }} {{ $key === $selected->toDateString() ? 'selected' : '' }}"
                   data-day-url="{{ route('staff.rooms.day', ['tanggal' => $key]) }}"
                   data-tanggal="{{ $key }}"
                   @if ($n > 0) title="{{ $n }} kamar terisi" @endif>
                    <span class="d">{{ $d }}</span>
                    @if ($n > 0)
                        <span class="cnt">{{ $n }}</span>
                    @endif
                </a>
            @endfor
        </div>
        <p class="cal-note">Angka pada sel = jumlah kamar yang terisi malam itu. Klik tanggal untuk melihat daftarnya.</p>
    </div>

    {{-- 3. Daftar booking untuk tanggal terpilih --}}
    <div class="card day-card" id="day-panel">
        @include('staff.rooms._day')
    </div>

    @include('partials.alarm-checkout')
@endsection

@push('scripts')
<script>
    // Klik tanggal -> ganti daftar di bawahnya via fetch (fallback: link biasa).
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
</script>
@endpush
