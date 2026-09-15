{{-- Grafik pendapatan harian (live) — hanya untuk admin. --}}
@push('head')
<style>
    .rv-card { padding: 22px; margin-bottom: 26px; }

    .rv-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 6px; }
    .rv-top h2 { margin: 0 0 4px; font-size: 1.15rem; font-weight: 800; letter-spacing: -.02em; }
    .rv-top p { margin: 0; color: var(--muted); font-size: .86rem; }
    .rv-live { display: inline-flex; align-items: center; gap: 7px; font-size: .74rem; color: var(--muted); }
    .rv-live .dot {
        width: 8px; height: 8px; border-radius: 50%; background: var(--accent-2);
        box-shadow: 0 0 0 3px var(--accent-2-soft); animation: rvPulse 2s ease-in-out infinite;
    }
    @keyframes rvPulse { 0%,100% { opacity: 1; } 50% { opacity: .35; } }
    @media (prefers-reduced-motion: reduce) { .rv-live .dot { animation: none; } }

    .rv-nav { display: flex; align-items: center; gap: 10px; }
    .rv-nav button {
        width: 34px; height: 34px; border-radius: 999px; cursor: pointer;
        background: var(--surface); color: var(--text); border: 1px solid var(--border-2);
        font-family: inherit; font-weight: 700; transition: border-color .2s, color .2s;
    }
    .rv-nav button:hover { border-color: var(--accent); color: var(--accent); }
    .rv-nav .bulan { font-weight: 800; letter-spacing: -.01em; min-width: 128px; text-align: center; }

    /* Ringkasan */
    .rv-sum { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 16px 0 18px; }
    .rv-sum .box { border: 1px solid var(--border-2); border-radius: 12px; padding: 14px; background: var(--surface); }
    .rv-sum .n { font-variant-numeric: tabular-nums; font-size: 1.15rem; font-weight: 700; color: var(--accent); }
    .rv-sum .box.alt .n { color: var(--accent-2); }
    .rv-sum .l { margin-top: 4px; font-size: .7rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); font-weight: 700; }

    /* Grafik batang */
    .rv-chart {
        display: flex; align-items: flex-end; gap: 3px; height: 190px;
        padding: 10px 4px 0; border-bottom: 1px solid var(--border-2); overflow-x: auto;
    }
    .rv-col {
        flex: 1 1 0; min-width: 14px; display: flex; flex-direction: column; align-items: center; gap: 6px;
        height: 100%; justify-content: flex-end;
        background: none; border: none; padding: 0; cursor: pointer; font-family: inherit;
    }
    .rv-bar {
        width: 100%; border-radius: 5px 5px 0 0; min-height: 3px;
        background: linear-gradient(180deg, var(--accent), var(--accent-600));
        transition: filter .15s, transform .15s;
    }
    .rv-col:hover .rv-bar { filter: brightness(1.12); transform: scaleY(1.02); transform-origin: bottom; }
    .rv-col.kosong .rv-bar { background: var(--border-2); }
    .rv-col.today .rv-bar { background: linear-gradient(180deg, var(--accent-2), var(--accent-2-600)); }
    .rv-col.aktif .rv-bar { outline: 2px solid var(--accent-2); outline-offset: 1px; }
    .rv-col .d { font-variant-numeric: tabular-nums; font-size: .62rem; color: var(--muted); }
    .rv-col.today .d { color: var(--accent-2-600); font-weight: 700; }
    .rv-hint { margin: 10px 2px 0; font-size: .78rem; color: var(--muted); }

    /* Panel rincian tanggal */
    .rv-detail { margin-top: 18px; border-top: 1px solid var(--border-2); padding-top: 16px; }
    .rv-detail[hidden] { display: none !important; }
    .rv-day-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 14px; }
    .rv-day-head h3 { margin: 0 0 3px; font-size: 1rem; font-weight: 800; }
    .rv-day-head p { margin: 0; color: var(--muted); font-size: .84rem; }
    .rv-day-total { text-align: right; }
    .rv-day-total .n { font-variant-numeric: tabular-nums; font-size: 1.3rem; font-weight: 700; color: var(--accent); }
    .rv-day-total .l { font-size: .7rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); font-weight: 700; }

    .rv-tipe { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
    .rv-tipe-item {
        display: inline-flex; align-items: center; gap: 9px;
        border: 1px solid var(--border-2); border-radius: 999px; padding: 7px 14px; background: var(--surface);
        font-size: .84rem;
    }
    .rv-tipe-calc { color: var(--muted); font-variant-numeric: tabular-nums; font-size: .78rem; }
    .rv-tipe-total { font-weight: 700; font-variant-numeric: tabular-nums; }

    .rv-tscroll { overflow-x: auto; }
    .rv-table { width: 100%; border-collapse: collapse; min-width: 620px; }
    .rv-table thead th {
        text-align: left; font-size: .68rem; letter-spacing: .07em; text-transform: uppercase;
        color: var(--muted); font-weight: 700; padding: 10px 12px; border-bottom: 1px solid var(--border-2);
    }
    .rv-table th.num, .rv-table td.num { text-align: right; }
    .rv-table tbody td { padding: 11px 12px; border-bottom: 1px solid var(--border-2); font-size: .88rem; }
    .rv-table tbody tr:hover td { background: var(--hover); }
    .rv-no { font-variant-numeric: tabular-nums; font-weight: 700; margin-right: 7px; }
    .rv-muted { color: var(--muted); font-size: .82rem; }
    .rv-money { font-variant-numeric: tabular-nums; }
    .rv-foot-label { text-align: right; font-weight: 700; padding: 12px; }
    .rv-foot-total { font-variant-numeric: tabular-nums; font-weight: 700; color: var(--accent); padding: 12px; }
    .rv-empty { text-align: center; color: var(--muted); padding: 26px; }

    .tipe-badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: .64rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .tipe-king   { background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border); }
    .tipe-twin   { background: var(--info-soft);  color: var(--info);       border: 1px solid var(--info-line); }
    .tipe-family { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-orange); }
    .bf-badge {
        display: inline-block; padding: 3px 9px; border-radius: 999px;
        font-size: .62rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    }
    .bf-badge.ada   { background: rgba(74,222,128,.12); color: var(--ok); border: 1px solid rgba(74,222,128,.28); }
    .bf-badge.tanpa { background: rgba(255,255,255,.04); color: var(--muted); border: 1px solid var(--border-2); }

    .st-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: .7rem; font-weight: 700; }
    .st-done    { background: var(--accent-soft); color: var(--accent);       border: 1px solid var(--border); }
    .st-pending { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-orange); }

    @media (max-width: 640px) {
        .rv-sum { grid-template-columns: 1fr; }
        .rv-chart { height: 150px; }
    }
</style>
@endpush

<div class="card rv-card" id="rv-card"
     data-month-url="{{ route('admin.revenue.month') }}"
     data-day-url="{{ url('admin/revenue/day') }}">
    <div class="rv-top">
        <div>
            <h2>Pendapatan Harian</h2>
            <p>Tarif per malam: King &amp; Twin Rp 300.000 · Family Rp 450.000. Klik batang untuk rinciannya.</p>
        </div>
        <div class="rv-nav">
            <button type="button" id="rv-prev" title="Bulan sebelumnya">@include('partials.icon', ['name' => 'chevron-left', 'class' => 'ic'])</button>
            <span class="bulan" id="rv-bulan">…</span>
            <button type="button" id="rv-next" title="Bulan berikutnya">@include('partials.icon', ['name' => 'chevron-right', 'class' => 'ic'])</button>
        </div>
    </div>

    <div class="rv-sum">
        <div class="box"><div class="n" id="rv-total">—</div><div class="l">Total Bulan Ini</div></div>
        <div class="box alt"><div class="n" id="rv-avg">—</div><div class="l">Rata-rata / Hari Terisi</div></div>
        <div class="box"><div class="n" id="rv-peak">—</div><div class="l">Hari Tertinggi</div></div>
    </div>

    <div class="rv-chart" id="rv-chart"></div>

    <div class="rv-hint">
        <span class="rv-live"><span class="dot"></span> Live — diperbarui otomatis <span id="rv-time"></span></span>
    </div>

    <div class="rv-detail" id="rv-detail" hidden></div>
</div>

@push('scripts')
<script>
(function () {
    const card = document.getElementById('rv-card');
    if (!card) return;

    const MONTH_URL = card.dataset.monthUrl;
    const DAY_URL   = card.dataset.dayUrl;

    const chartEl  = document.getElementById('rv-chart');
    const bulanEl  = document.getElementById('rv-bulan');
    const totalEl  = document.getElementById('rv-total');
    const avgEl    = document.getElementById('rv-avg');
    const peakEl   = document.getElementById('rv-peak');
    const timeEl   = document.getElementById('rv-time');
    const detailEl = document.getElementById('rv-detail');

    let bulan = null;          // 'YYYY-MM' — null = bulan berjalan
    let tanggalAktif = null;   // tanggal yang rinciannya sedang dibuka
    let nav = { prev: null, next: null };

    const rupiah = n => 'Rp ' + (n || 0).toLocaleString('id-ID');

    function render(data) {
        bulan = data.bulan;
        nav = { prev: data.prev, next: data.next };

        bulanEl.textContent = data.label;
        totalEl.textContent = rupiah(data.ringkasan.total);
        avgEl.textContent   = rupiah(data.ringkasan.rata_rata);
        peakEl.textContent  = data.ringkasan.puncak
            ? 'Tgl ' + data.ringkasan.puncak.hari + ' · ' + rupiah(data.ringkasan.puncak.total)
            : '—';
        timeEl.textContent  = data.diperbarui;

        const maks = Math.max(1, ...data.hari.map(h => h.total));

        chartEl.innerHTML = '';
        data.hari.forEach(function (h) {
            const col = document.createElement('button');
            col.type = 'button';
            col.className = 'rv-col'
                + (h.total === 0 ? ' kosong' : '')
                + (h.tanggal === data.today ? ' today' : '')
                + (h.tanggal === tanggalAktif ? ' aktif' : '');
            col.dataset.tanggal = h.tanggal;
            col.title = 'Tgl ' + h.hari + ' — ' + rupiah(h.total) + ' (' + h.kamar + ' kamar)';

            const bar = document.createElement('div');
            bar.className = 'rv-bar';
            bar.style.height = Math.max(3, Math.round((h.total / maks) * 100)) + '%';

            const lbl = document.createElement('span');
            lbl.className = 'd';
            // Tampilkan label tiap 2 hari agar tidak berdesakan.
            lbl.textContent = (h.hari % 2 === 1 || h.hari === data.hari.length) ? h.hari : '';

            col.appendChild(bar);
            col.appendChild(lbl);
            chartEl.appendChild(col);
        });
    }

    function muat(bln) {
        const url = new URL(MONTH_URL, window.location.origin);
        if (bln) url.searchParams.set('bulan', bln);

        return fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(render)
            .catch(() => { chartEl.innerHTML = '<div class="rv-empty">Gagal memuat data pendapatan.</div>'; });
    }

    function muatRincian(tanggal) {
        fetch(DAY_URL + '/' + tanggal, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.ok ? r.text() : Promise.reject())
            .then(function (html) {
                detailEl.innerHTML = html;
                detailEl.hidden = false;
                detailEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            })
            .catch(() => {
                detailEl.innerHTML = '<div class="rv-empty">Gagal memuat rincian tanggal ini.</div>';
                detailEl.hidden = false;
            });
    }

    // Klik batang -> tampilkan rincian tanggal tsb.
    chartEl.addEventListener('click', function (e) {
        const col = e.target.closest('.rv-col');
        if (!col) return;

        tanggalAktif = col.dataset.tanggal;
        chartEl.querySelectorAll('.rv-col.aktif').forEach(el => el.classList.remove('aktif'));
        col.classList.add('aktif');
        muatRincian(tanggalAktif);
    });

    document.getElementById('rv-prev').addEventListener('click', function () {
        tanggalAktif = null; detailEl.hidden = true;
        muat(nav.prev);
    });
    document.getElementById('rv-next').addEventListener('click', function () {
        tanggalAktif = null; detailEl.hidden = true;
        muat(nav.next);
    });

    // Muat awal + segarkan berkala (live).
    muat(null);
    setInterval(function () {
        muat(bulan).then(function () {
            if (tanggalAktif) muatRincian(tanggalAktif);
        });
    }, 60000);
})();
</script>
@endpush
