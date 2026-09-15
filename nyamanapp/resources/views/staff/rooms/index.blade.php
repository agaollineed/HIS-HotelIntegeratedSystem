@extends('layouts.panel')

@section('title', 'Kamar')

@push('head')
<style>
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

    .day-empty { text-align: center; color: var(--muted); padding: 34px 20px; }

    /* Filter tamu di dalam panel booking — menyaring daftar hari itu saja. */
    .filter-bar {
        display: flex; align-items: center; gap: 9px; flex-wrap: wrap;
        border: 1px solid var(--border-2); border-radius: 12px; background: var(--bg);
        padding: 4px 14px; margin: 14px 0 18px;
    }
    .filter-bar .ic-sm { color: var(--muted); flex: 0 0 auto; }
    .filter-bar input {
        flex: 1 1 200px; font-family: inherit; font-size: .93rem; color: var(--text);
        background: transparent; border: none; outline: none; padding: 10px 0;
    }
    .filter-bar input::placeholder { color: var(--muted); }
    .filter-bar .hitung { font-size: .78rem; color: var(--muted); font-variant-numeric: tabular-nums; }
    .filter-bar .bersih {
        background: none; border: none; cursor: pointer; color: var(--muted); display: inline-flex; padding: 3px;
    }
    .filter-bar .bersih:hover { color: var(--text); }
    .filter-bar .bersih[hidden] { display: none !important; }
    .bk[hidden] { display: none !important; }
    .filter-kosong { text-align: center; color: var(--muted); padding: 26px 20px; }
    .filter-kosong[hidden] { display: none !important; }

    /* ---------- Toast late check-out ---------- */
    .toast-late {
        position: fixed; right: 22px; bottom: 22px; z-index: 40; width: min(380px, calc(100vw - 44px));
        background: linear-gradient(180deg, var(--surface-2), var(--bg-2));
        border: 1px solid var(--alert-line); border-radius: 16px; padding: 16px 18px;
        box-shadow: 0 30px 70px -28px rgba(0,0,0,.95);
    }
    .toast-late[hidden] { display: none !important; }
    .toast-head { display: flex; align-items: center; gap: 9px; color: var(--alert); font-weight: 800; font-size: .88rem; margin-bottom: 4px; }
    .toast-head .x {
        margin-left: auto; background: none; border: none; cursor: pointer; color: var(--muted);
        display: inline-flex; padding: 2px;
    }
    .toast-head .x:hover { color: var(--text); }
    .toast-sub { font-size: .8rem; color: var(--muted); margin-bottom: 12px; }
    .toast-item { border-top: 1px solid var(--border-2); padding-top: 11px; margin-top: 11px; }
    .toast-item:first-of-type { border-top: none; padding-top: 0; margin-top: 0; }
    .toast-room { font-weight: 700; font-size: .9rem; font-variant-numeric: tabular-nums; }
    .toast-meta { font-size: .78rem; color: var(--muted); margin-top: 2px; }
    .toast-actions { display: flex; gap: 8px; margin-top: 9px; flex-wrap: wrap; }
    .toast-ditandai { font-size: .74rem; color: var(--alert); font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }

    @media (max-width: 560px) {
        .toast-late { right: 12px; left: 12px; bottom: 12px; width: auto; }
    }

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

    @if ($lewatJam->isNotEmpty())
        {{-- Kamar yang lewat jam 12.00 tetapi belum di-check-out. Muncul di
             semua tanggal, bukan hanya tanggal terpilih, supaya tidak lolos
             hanya karena staff sedang melihat bulan lain. --}}
        <div class="toast-late" id="toast-late">
            <div class="toast-head">
                @include('partials.icon', ['name' => 'clock', 'class' => 'ic-sm'])
                Lewat jam check-out
                <button type="button" class="x" id="toast-tutup" title="Tutup">
                    @include('partials.icon', ['name' => 'x', 'class' => 'ic-sm'])
                </button>
            </div>
            <div class="toast-sub">Batas {{ \App\Models\Booking::JAM_CHECKOUT }} sudah lewat, kamar belum di-check-out.</div>

            @foreach ($lewatJam as $grup)
                @php $u = $grup->first(); @endphp
                <div class="toast-item">
                    <div class="toast-room">{{ $grup->map(fn ($b) => $b->room?->nomor)->filter()->implode(', ') }}</div>
                    <div class="toast-meta">
                        {{ $u->nama_tamu }} · telat
                        {{ \App\Models\Booking::labelDurasi((int) $u->batasCheckout()->diffInMinutes(now())) }}
                    </div>
                    <div class="toast-actions">
                        @if ($u->lateDitandai())
                            <span class="toast-ditandai">
                                @include('partials.icon', ['name' => 'check', 'class' => 'ic-xs']) Ditandai late
                            </span>
                        @else
                            <form method="POST" action="{{ route('staff.rooms.late', $u) }}">
                                @csrf
                                <button type="submit" class="btn btn-danger btn-sm">Tandai Late Check-out</button>
                            </form>
                        @endif
                        <a href="{{ route('staff.rooms.checkout', $u) }}" class="btn btn-accent btn-sm">Check-out</a>
                    </div>
                </div>
            @endforeach
        </div>

        @push('scripts')
        <script>
            (function () {
                const t = document.getElementById('toast-late');
                const x = document.getElementById('toast-tutup');
                if (t && x) x.addEventListener('click', () => { t.hidden = true; });
            })();
        </script>
        @endpush
    @endif
@endsection

@push('scripts')
<script>
    // ---------- Filter nama tamu di dalam panel booking ----------
    // Dipasang lewat delegasi pada document, bukan pada elemennya langsung,
    // karena panel di bawah kalender diganti isinya lewat fetch — pendengar
    // yang dipasang ke elemen lama akan ikut hilang saat panel diganti.
    function jalankanFilter() {
        const input = document.getElementById('filter-tamu');
        if (!input) return;

        const kata = input.value.trim().toLowerCase();
        const kartu = document.querySelectorAll('#day-panel .bk');
        let tampil = 0;

        kartu.forEach(function (k) {
            const teks = (k.dataset.cari || k.textContent || '').toLowerCase();
            const cocok = kata === '' || teks.includes(kata);
            k.hidden = !cocok;
            if (cocok) tampil++;
        });

        const hitung = document.getElementById('filter-hitung');
        if (hitung) hitung.textContent = kata === '' ? '' : tampil + ' dari ' + kartu.length;

        const kosong = document.getElementById('filter-kosong');
        if (kosong) kosong.hidden = !(kata !== '' && tampil === 0);

        const bersih = document.getElementById('filter-bersih');
        if (bersih) bersih.hidden = kata === '';
    }

    document.addEventListener('input', function (e) {
        if (e.target && e.target.id === 'filter-tamu') jalankanFilter();
    });
    document.addEventListener('click', function (e) {
        const b = e.target.closest('#filter-bersih');
        if (!b) return;
        const input = document.getElementById('filter-tamu');
        if (input) { input.value = ''; input.focus(); jalankanFilter(); }
    });

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
                    jalankanFilter();
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
