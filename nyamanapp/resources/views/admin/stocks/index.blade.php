@extends('layouts.panel')

@section('title', 'Data Stok')

@push('head')
<style>
    .head-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .head-actions { display: flex; gap: 10px; flex-shrink: 0; flex-wrap: wrap; }

    .stats { display: flex; gap: 26px; margin: 4px 0 24px; }
    .stat .k { font-variant-numeric: tabular-nums; font-size: 1.6rem; font-weight: 700; color: var(--accent); }
    .stat .l { font-size: .75rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }

    .table-card { padding: 6px; overflow: hidden; }
    .tscroll { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; min-width: 640px; }
    thead th {
        text-align: left; font-size: .72rem; letter-spacing: .07em; text-transform: uppercase;
        color: var(--muted); font-weight: 700; padding: 15px 16px; border-bottom: 1px solid var(--border-2);
    }
    thead th.num, tbody td.num { text-align: right; }
    tbody td { padding: 15px 16px; border-bottom: 1px solid var(--border-2); font-size: .94rem; vertical-align: middle; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover td { background: var(--hover); }

    .nama-cell { font-weight: 600; }
    /* Titik merah penanda stok habis */
    .nama-cell .zero-dot {
        display: inline-block; width: 9px; height: 9px; border-radius: 50%;
        background: var(--alert); margin-left: 8px; vertical-align: middle;
        box-shadow: 0 0 0 3px rgba(248,113,113,.22);
        animation: zeroPulse 1.8s ease-in-out infinite;
    }
    @keyframes zeroPulse {
        0%, 100% { box-shadow: 0 0 0 3px rgba(248,113,113,.22); }
        50%      { box-shadow: 0 0 0 6px rgba(248,113,113,.06); }
    }
    @media (prefers-reduced-motion: reduce) { .nama-cell .zero-dot { animation: none; } }

    /* Kontrol urutan */
    .sortbox { position: relative; display: inline-flex; align-items: center; gap: 8px; }
    .sortbox label { font-size: .78rem; color: var(--muted); font-weight: 600; }
    .sortbox select {
        font-family: inherit; font-size: .86rem; font-weight: 600; color: var(--text);
        background: var(--surface); border: 1px solid var(--border-2); border-radius: 999px;
        padding: 9px 14px; cursor: pointer;
    }
    .sortbox select:focus { outline: none; border-color: var(--accent); }
    .num { font-variant-numeric: tabular-nums; }
    .qty-pill {
        display: inline-block; min-width: 40px; text-align: center;
        padding: 4px 10px; border-radius: 8px; font-weight: 700;
        background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border);
    }
    .qty-pill.zero { background: var(--danger-soft); color: var(--danger); border-color: var(--danger-line); }

    .upd { line-height: 1.3; }
    .upd .when { font-weight: 500; }
    .upd .by { font-size: .78rem; color: var(--muted); }

    .row-actions { display: flex; gap: 8px; justify-content: flex-end; }
    .empty { text-align: center; color: var(--muted); padding: 46px 20px; }
    .empty .btn { margin-top: 16px; }

    /* Toolbar: pencarian + unduh Excel */
    .toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 0 0 18px; }
    .search { position: relative; flex: 1 1 240px; max-width: 420px; }
    .search input {
        width: 100%; height: 44px; padding: 0 38px; box-sizing: border-box;
        font-family: inherit; font-size: .95rem; color: var(--text);
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px;
    }
    .search input:focus { outline: none; border-color: var(--accent); }
    .search input::placeholder { color: var(--muted); }
    .search .search-ic { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); display: flex; pointer-events: none; }
    .search .search-clear { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); text-decoration: none; font-size: .9rem; }
    .search .search-clear:hover { color: var(--danger); }
    .toolbar .download-btn { margin-left: auto; }
    @media (max-width: 560px) {
        .toolbar .download-btn { margin-left: 0; width: 100%; }
    }

    /* ---------- Popup stok habis ---------- */
    .zmodal {
        position: fixed; inset: 0; z-index: 55;
        display: flex; align-items: center; justify-content: center; padding: 20px;
        background: var(--overlay); backdrop-filter: blur(3px);
    }
    .zmodal[hidden] { display: none !important; }
    .zmodal-card {
        width: min(94vw, 430px); background: var(--surface);
        border: 1px solid var(--alert-line); border-radius: 18px; padding: 24px;
        box-shadow: 0 40px 90px -30px rgba(0,0,0,.88);
    }
    .zmodal-top { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .zmodal-top .zmodal-ic {
        color: var(--alert);
        flex: 0 0 auto; width: 42px; height: 42px; border-radius: 12px;
        display: grid; place-items: center; font-size: 1.25rem;
        background: var(--alert-soft); border: 1px solid var(--alert-line);
    }
    .zmodal-top h3 { margin: 0; font-size: 1.1rem; font-weight: 800; letter-spacing: -.02em; }
    .zmodal p { margin: 0 0 12px; color: var(--muted); font-size: .9rem; }
    .zmodal ul { margin: 0 0 18px; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 8px; }
    .zmodal li {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        background: var(--alert-soft); border: 1px solid var(--alert-line);
        border-radius: 10px; padding: 10px 14px; font-weight: 600; font-size: .92rem;
    }
    .zmodal li .z {
        font-variant-numeric: tabular-nums; font-weight: 700; font-size: .78rem;
        color: var(--alert); background: var(--alert-soft); border-radius: 999px; padding: 3px 10px;
    }
    .zmodal-actions { display: flex; gap: 10px; justify-content: flex-end; }
</style>
@endpush

@section('content')
    @php
        $totalItem = $stocks->count();
        $totalUnit = $stocks->sum('jumlah');
        $totalNilai = $stocks->sum(fn ($s) => $s->jumlah * $s->harga);
    @endphp

    <div class="page-head head-row">
        <div>
            <h1>Data Stok</h1>
            <p>Kelola item stok — tambah, ubah, dan hapus. Jumlah &amp; last update ikut terbarui dari input staff.</p>
        </div>
        <div class="head-actions">
            <a href="{{ route('log') }}" class="btn btn-ghost">@include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-sm']) Log Perubahan</a>
            <a href="{{ route('admin.stocks.jumlah') }}" class="btn btn-ghost">@include('partials.icon', ['name' => 'box', 'class' => 'ic-sm']) Tambah / Kurangi Jumlah</a>
            <a href="{{ route('admin.stocks.create') }}" class="btn btn-accent">@include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm']) Tambah Stok</a>
        </div>
    </div>

    <div class="stats">
        <div class="stat"><div class="k">{{ number_format($totalItem, 0, ',', '.') }}</div><div class="l">Jenis Item</div></div>
        <div class="stat"><div class="k">{{ number_format($totalUnit, 0, ',', '.') }}</div><div class="l">Total Unit</div></div>
        <div class="stat"><div class="k">Rp {{ number_format($totalNilai, 0, ',', '.') }}</div><div class="l">Nilai Stok</div></div>
    </div>

    <div class="toolbar">
        <div class="search">
            <span class="search-ic">@include('partials.icon', ['name' => 'search', 'class' => 'ic-sm'])</span>
            <input type="text" id="stok-search" placeholder="Cari nama stok…" autocomplete="off">
        </div>
        <div class="sortbox">
            <label for="stok-sort">Urutkan</label>
            <select id="stok-sort">
                <option value="default">Nama (A–Z)</option>
                <option value="jumlah-asc">Jumlah: sedikit → banyak</option>
                <option value="jumlah-desc">Jumlah: banyak → sedikit</option>
                <option value="habis">Stok habis dulu</option>
            </select>
        </div>
        <a href="{{ route('admin.stocks.export') }}" data-export-base="{{ route('admin.stocks.export') }}"
           class="btn btn-accent download-btn" id="export-btn">@include('partials.icon', ['name' => 'download', 'class' => 'ic-sm']) Unduh Excel</a>
    </div>

    <div class="card table-card">
        @if ($stocks->isEmpty())
            <div class="empty">
                Belum ada data stok.
                <div><a href="{{ route('admin.stocks.create') }}" class="btn btn-accent">@include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm']) Tambah Stok Pertama</a></div>
            </div>
        @else
            <div class="tscroll">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Stok</th>
                            <th class="num">Jumlah</th>
                            <th class="num">Harga</th>
                            <th>Last Update</th>
                            <th class="num">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="stok-tbody">
                        @foreach ($stocks as $stock)
                            <tr data-nama="{{ $stock->nama }}" data-jumlah="{{ $stock->jumlah }}">
                                <td class="nama-cell">
                                    {{ $stock->nama }}
                                    @if ((int) $stock->jumlah === 0)
                                        <span class="zero-dot" title="Stok habis" aria-label="Stok habis"></span>
                                    @endif
                                </td>
                                <td class="num">
                                    <span class="qty-pill {{ $stock->jumlah === 0 ? 'zero' : '' }}">{{ $stock->jumlah }}</span>
                                </td>
                                <td class="num">Rp {{ number_format($stock->harga, 0, ',', '.') }}</td>
                                <td>
                                    <div class="upd">
                                        <div class="when">{{ $stock->updated_at?->translatedFormat('d M Y, H:i') }}</div>
                                        <div class="by">{{ $stock->updated_at?->diffForHumans() }}{{ $stock->updated_by ? ' · ' . $stock->updated_by : '' }}</div>
                                    </div>
                                </td>
                                <td class="num">
                                    <div class="row-actions">
                                        <a href="{{ route('admin.stocks.edit', $stock) }}" class="btn btn-ghost btn-sm">Ubah</a>
                                        <form method="POST" action="{{ route('admin.stocks.destroy', $stock) }}"
                                              onsubmit="return confirm('Hapus stok &quot;{{ $stock->nama }}&quot;?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        <tr class="no-match" style="display:none;">
                            <td colspan="5" style="text-align:center; color:var(--muted); padding:26px;">Tidak ada stok yang cocok.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Popup peringatan: ada item dengan stok 0 --}}
    @php $stokHabis = $stocks->filter(fn ($s) => (int) $s->jumlah === 0)->values(); @endphp
    @if ($stokHabis->isNotEmpty())
        <div class="zmodal" id="zero-modal" hidden>
            <div class="zmodal-card">
                <div class="zmodal-top">
                    <span class="zmodal-ic">@include('partials.icon', ['name' => 'alert', 'class' => 'ic-lg'])</span>
                    <h3>{{ $stokHabis->count() }} item stok HABIS</h3>
                </div>
                <p>Item berikut jumlahnya 0 dan tidak bisa dipakai sampai diisi ulang:</p>
                <ul>
                    @foreach ($stokHabis as $s)
                        <li><span>{{ $s->nama }}</span><span class="z">0 tersisa</span></li>
                    @endforeach
                </ul>
                <div class="zmodal-actions">

                    <button type="button" class="btn btn-accent" data-zclose>Mengerti</button>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        const input = document.getElementById('stok-search');
        const tbody = document.getElementById('stok-tbody');
        const exportBtn = document.getElementById('export-btn');
        if (!input || !tbody) return;

        const base = exportBtn ? exportBtn.getAttribute('data-export-base') : '';

        input.addEventListener('input', function () {
            const val = this.value.trim();
            const qq = val.toLowerCase();

            let visible = 0;
            tbody.querySelectorAll('tr:not(.no-match)').forEach(tr => {
                const show = (tr.dataset.nama || '').toLowerCase().includes(qq);
                tr.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            const nm = tbody.querySelector('.no-match');
            if (nm) nm.style.display = visible === 0 ? '' : 'none';

            // Samakan tombol Unduh Excel dengan filter yang sedang aktif.
            if (exportBtn && base) {
                exportBtn.href = val ? base + '?q=' + encodeURIComponent(val) : base;
            }
        });
    })();

    // Urutkan baris tabel (tetap kompatibel dengan pencarian di atas).
    (function () {
        const sel = document.getElementById('stok-sort');
        const tbody = document.getElementById('stok-tbody');
        if (!sel || !tbody) return;

        const rows = Array.from(tbody.querySelectorAll('tr:not(.no-match)'));
        const noMatch = tbody.querySelector('.no-match');
        const urutanAwal = rows.slice();

        const jml = tr => parseInt(tr.dataset.jumlah || '0', 10);

        sel.addEventListener('change', function () {
            let urut;
            switch (this.value) {
                case 'jumlah-asc':  urut = rows.slice().sort((a, b) => jml(a) - jml(b)); break;
                case 'jumlah-desc': urut = rows.slice().sort((a, b) => jml(b) - jml(a)); break;
                case 'habis':
                    urut = rows.slice().sort((a, b) => (jml(a) === 0 ? 0 : 1) - (jml(b) === 0 ? 0 : 1) || jml(a) - jml(b));
                    break;
                default: urut = urutanAwal.slice();
            }
            urut.forEach(tr => tbody.insertBefore(tr, noMatch));
        });
    })();

    // Popup peringatan stok habis (muncul saat halaman dibuka).
    (function () {
        const modal = document.getElementById('zero-modal');
        if (!modal) return;

        modal.hidden = false;

        function tutup() { modal.hidden = true; }
        modal.querySelectorAll('[data-zclose]').forEach(el => el.addEventListener('click', tutup));
        modal.addEventListener('click', function (e) { if (e.target === modal) tutup(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) tutup(); });
    })();
</script>
@endpush
