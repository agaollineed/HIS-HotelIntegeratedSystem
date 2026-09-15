@extends('layouts.panel')

@section('title', 'Input Stok')

@push('head')
<style>
    .staff-card { padding: 26px; }
    .rows { display: flex; flex-direction: column; gap: 14px; margin-bottom: 8px; }
    .item-row {
        display: grid; grid-template-columns: 1fr auto auto auto; gap: 14px; align-items: end;
        background: rgba(255,255,255,.02); border: 1px solid var(--border-2);
        border-radius: var(--radius); padding: 14px;
    }
    .field label { display: block; font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 7px; }

    /* Autocomplete nama stok */
    .ac { position: relative; }
    .ac-input {
        width: 100%; box-sizing: border-box; font-family: inherit; font-size: .95rem; color: var(--text);
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; padding: 11px 12px;
    }
    .ac-input:focus { outline: none; border-color: var(--accent); }
    .ac-input::placeholder { color: var(--muted); }
    .ac-list {
        position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 30;
        max-height: 240px; overflow-y: auto; padding: 4px;
        background: var(--surface-2); border: 1px solid var(--border);
        border-radius: 10px; box-shadow: 0 20px 40px -20px rgba(0,0,0,.8);
    }
    .ac-item {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        padding: 9px 11px; border-radius: 8px; cursor: pointer;
    }
    .ac-item:hover, .ac-item.active { background: var(--accent-soft); }
    .ac-nama { font-weight: 600; font-size: .92rem; }
    .ac-stok { font-variant-numeric: tabular-nums; font-size: .78rem; color: var(--muted); white-space: nowrap; }
    .ac-empty { padding: 12px; text-align: center; color: var(--muted); font-size: .88rem; }

    /* Toggle Tambah / Kurangi */
    .mode-toggle { display: inline-flex; border: 1px solid var(--border-2); border-radius: 10px; overflow: hidden; }
    .mode-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        font-family: inherit; font-weight: 700; font-size: .82rem; cursor: pointer;
        padding: 0 14px; height: 44px; border: none; white-space: nowrap;
        background: var(--surface-2); color: var(--muted);
        transition: background .15s, color .15s;
    }
    .mode-btn + .mode-btn { border-left: 1px solid var(--border-2); }
    .mode-btn:hover { color: var(--text); }
    .mode-btn.active[data-mode="add"]      { background: var(--accent-soft); color: var(--accent); }
    .mode-btn.active[data-mode="subtract"] { background: rgba(255,92,92,.14); color: var(--danger); }

    .stepper { display: inline-flex; align-items: center; gap: 0; border: 1px solid var(--border-2); border-radius: 10px; overflow: hidden; }
    .stepper button { display: inline-flex; align-items: center; justify-content: center;
        width: 42px; height: 44px; font-size: 1.3rem; font-weight: 700; cursor: pointer;
        background: var(--surface-2); color: var(--accent); border: none; transition: background .15s;
    }
    .stepper button:hover { background: var(--accent-soft); }
    .stepper input {
        width: 68px; height: 44px; text-align: center; border: none; outline: none;
        background: var(--bg); color: var(--text);
        font-variant-numeric: tabular-nums; font-size: 1.05rem; font-weight: 700;
        -moz-appearance: textfield;
    }
    .stepper input::-webkit-outer-spin-button,
    .stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

    .row-remove { display: inline-flex; align-items: center; justify-content: center;
        width: 44px; height: 44px; border-radius: 10px; cursor: pointer;
        background: transparent; color: var(--muted); border: 1px solid var(--border-2);
        font-size: 1.1rem; transition: .15s; align-self: end;
    }
    .row-remove:hover { color: var(--danger); border-color: rgba(255,92,92,.35); }

    .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 22px; padding-top: 22px; border-top: 1px solid var(--border-2); }
    .empty { text-align: center; color: var(--muted); padding: 30px 0; }

    /* Search box tabel stok */
    .staff-toolbar { margin: 0 0 16px; }
    .search { position: relative; max-width: 420px; }
    .search input {
        width: 100%; height: 44px; padding: 0 16px 0 38px; box-sizing: border-box;
        font-family: inherit; font-size: .95rem; color: var(--text);
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px;
    }
    .search input:focus { outline: none; border-color: var(--accent); }
    .search input::placeholder { color: var(--muted); }
    .search .search-ic { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); display: flex; pointer-events: none; }

    /* Tabel stok terkini (read-only) */
    .table-card { padding: 6px; overflow: hidden; margin-top: 16px; }
    .tscroll { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; min-width: 560px; }
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

    .sortbox { display: inline-flex; align-items: center; gap: 8px; }
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

    .subhead { margin: 34px 0 14px; }
    .subhead h2 { margin: 0 0 4px; font-size: 1.2rem; font-weight: 800; letter-spacing: -.02em; }
    .subhead p { margin: 0; color: var(--muted); font-size: .9rem; }

    @media (max-width: 640px) {
        .item-row { grid-template-columns: 1fr 1fr; }
        .item-row .field.nama-field { grid-column: 1 / -1; }
        .item-row .mode-toggle { width: 100%; }
        .item-row .row-remove { justify-self: end; }
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
    <div class="page-head">
        <h1>Input Stok</h1>
    </div>

    @if ($stocks->isEmpty())
        <div class="card staff-card">
            <div class="empty">Belum ada item stok. Minta admin menambahkan stok terlebih dahulu.</div>
        </div>
    @else
        <div class="card staff-card">
            <form method="POST" action="{{ $aksiSimpan }}" id="staff-form">
                @csrf

                <div class="rows" id="rows">
                    {{-- Baris awal (mendukung repopulasi jika validasi gagal) --}}
                    @php $oldItems = old('items', [[]]); @endphp
                    @foreach ($oldItems as $i => $item)
                        @php
                            $mode    = ($item['mode'] ?? 'add') === 'subtract' ? 'subtract' : 'add';
                            $selId   = $item['stock_id'] ?? '';
                            $selNama = $selId !== '' ? optional($stocks->firstWhere('id', (int) $selId))->nama : '';
                        @endphp
                        <div class="item-row" data-index="{{ $i }}">
                            <div class="field nama-field">
                                <label>Nama Stok</label>
                                <div class="ac">
                                    <input type="text" class="ac-input" placeholder="Ketik nama stok…" autocomplete="off"
                                           value="{{ $selNama }}">
                                    <input type="hidden" class="ac-id" name="items[{{ $i }}][stock_id]" value="{{ $selId }}">
                                    <div class="ac-list" hidden></div>
                                </div>
                            </div>
                            <div class="field">
                                <label>Aksi</label>
                                <div class="mode-toggle">
                                    <button type="button" class="mode-btn {{ $mode === 'add' ? 'active' : '' }}" data-mode="add">@include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm']) Tambah</button>
                                    <button type="button" class="mode-btn {{ $mode === 'subtract' ? 'active' : '' }}" data-mode="subtract">@include('partials.icon', ['name' => 'minus', 'class' => 'ic-sm']) Kurangi</button>
                                    <input type="hidden" name="items[{{ $i }}][mode]" value="{{ $mode }}">
                                </div>
                            </div>
                            <div class="field qty">
                                <label>Jumlah</label>
                                <div class="stepper">
                                    <button type="button" data-step="-1" aria-label="Kurangi">@include('partials.icon', ['name' => 'minus', 'class' => 'ic-sm'])</button>
                                    <input type="number" name="items[{{ $i }}][jumlah]" min="1"
                                           value="{{ $item['jumlah'] ?? 1 }}">
                                    <button type="button" data-step="1" aria-label="Tambah">@include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm'])</button>
                                </div>
                            </div>
                            <button type="button" class="row-remove" title="Hapus baris">@include('partials.icon', ['name' => 'x', 'class' => 'ic-sm'])</button>
                        </div>
                    @endforeach
                </div>

                {{-- Sumber ikon utk baris yang dibuat lewat JS (agar tidak menduplikasi SVG) --}}
                <span id="ic-src-plus"  hidden>@include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm'])</span>
                <span id="ic-src-minus" hidden>@include('partials.icon', ['name' => 'minus', 'class' => 'ic-sm'])</span>
                <span id="ic-src-x"     hidden>@include('partials.icon', ['name' => 'x', 'class' => 'ic-sm'])</span>

                <div class="actions">
                    <button type="button" class="btn btn-ghost" id="add-item">@include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm']) Tambah Item</button>
                    <button type="submit" class="btn btn-accent">Simpan</button>
                </div>
            </form>
        </div>

        {{-- Tabel seluruh stok terkini (read-only) + pencarian --}}
        <div class="subhead">
            <h2>Stok Saat Ini</h2>
            <p>Daftar lengkap seluruh stok beserta jumlah dan pembaruan terakhir.</p>
        </div>
        <div class="staff-toolbar">
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
        </div>
        <div class="card table-card">
            <div class="tscroll">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Stok</th>
                            <th class="num">Jumlah</th>
                            <th class="num">Harga</th>
                            <th>Last Update</th>
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
                            </tr>
                        @endforeach
                        <tr class="no-match" style="display:none;">
                            <td colspan="4" style="text-align:center; color:var(--muted); padding:26px;">Tidak ada stok yang cocok.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif

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
                    <a href="{{ route('staff.reports.index') }}" class="btn btn-ghost" data-zclose>Laporkan ke Admin</a>

                    <button type="button" class="btn btn-accent" data-zclose>Mengerti</button>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        const rows = document.getElementById('rows');
        if (!rows) return;

        // Daftar seluruh stok untuk autocomplete.
        const IC_PLUS  = document.getElementById('ic-src-plus').innerHTML;
        const IC_MINUS = document.getElementById('ic-src-minus').innerHTML;
        const IC_X     = document.getElementById('ic-src-x').innerHTML;

        const STOCKS = @json($stocks->map(fn ($s) => ['id' => (string) $s->id, 'nama' => $s->nama, 'jumlah' => (int) $s->jumlah])->values());

        let counter = rows.querySelectorAll('.item-row').length;

        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }

        // ---------- Autocomplete ----------
        function renderList(listEl, query) {
            const qq = query.trim().toLowerCase();
            const matches = STOCKS.filter(s => s.nama.toLowerCase().includes(qq)).slice(0, 5);
            if (matches.length === 0) {
                listEl.innerHTML = '<div class="ac-empty">Tidak ada stok cocok</div>';
            } else {
                listEl.innerHTML = matches.map(s =>
                    '<div class="ac-item" data-id="' + s.id + '" data-nama="' + escapeHtml(s.nama) + '">' +
                        '<span class="ac-nama">' + escapeHtml(s.nama) + '</span>' +
                        '<span class="ac-stok">stok: ' + s.jumlah + '</span>' +
                    '</div>'
                ).join('');
            }
            listEl.hidden = false;
        }

        function closeAllLists(except) {
            rows.querySelectorAll('.ac-list').forEach(l => { if (l !== except) l.hidden = true; });
        }

        // Set hidden id kalau teks persis sama dengan salah satu nama.
        function syncExact(input) {
            const ac = input.closest('.ac');
            const hidden = ac.querySelector('.ac-id');
            const val = input.value.trim().toLowerCase();
            const found = STOCKS.find(s => s.nama.toLowerCase() === val);
            hidden.value = found ? found.id : '';
        }

        function selectItem(item) {
            const ac = item.closest('.ac');
            ac.querySelector('.ac-input').value = item.dataset.nama;
            ac.querySelector('.ac-id').value = item.dataset.id;
            ac.querySelector('.ac-list').hidden = true;
        }

        rows.addEventListener('input', function (e) {
            if (e.target.classList.contains('ac-input')) {
                renderList(e.target.closest('.ac').querySelector('.ac-list'), e.target.value);
                syncExact(e.target);
            }
        });

        rows.addEventListener('focusin', function (e) {
            if (e.target.classList.contains('ac-input')) {
                const list = e.target.closest('.ac').querySelector('.ac-list');
                closeAllLists(list);
                renderList(list, e.target.value);
            }
        });

        rows.addEventListener('keydown', function (e) {
            if (!e.target.classList.contains('ac-input')) return;
            const list = e.target.closest('.ac').querySelector('.ac-list');
            if (list.hidden) return;
            const items = Array.from(list.querySelectorAll('.ac-item'));
            if (items.length === 0) return;
            let idx = items.findIndex(i => i.classList.contains('active'));

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                idx = (idx + 1) % items.length;
                items.forEach(i => i.classList.remove('active'));
                items[idx].classList.add('active');
                items[idx].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                idx = (idx - 1 + items.length) % items.length;
                items.forEach(i => i.classList.remove('active'));
                items[idx].classList.add('active');
                items[idx].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                e.preventDefault();
                selectItem(idx >= 0 ? items[idx] : items[0]);
            } else if (e.key === 'Escape') {
                list.hidden = true;
            }
        });

        function buildRow(index) {
            const wrap = document.createElement('div');
            wrap.className = 'item-row';
            wrap.dataset.index = index;
            wrap.innerHTML =
                '<div class="field nama-field">' +
                    '<label>Nama Stok</label>' +
                    '<div class="ac">' +
                        '<input type="text" class="ac-input" placeholder="Ketik nama stok…" autocomplete="off">' +
                        '<input type="hidden" class="ac-id" name="items[' + index + '][stock_id]">' +
                        '<div class="ac-list" hidden></div>' +
                    '</div>' +
                '</div>' +
                '<div class="field">' +
                    '<label>Aksi</label>' +
                    '<div class="mode-toggle">' +
                        '<button type="button" class="mode-btn active" data-mode="add">' + IC_PLUS + ' Tambah</button>' +
                        '<button type="button" class="mode-btn" data-mode="subtract">' + IC_MINUS + ' Kurangi</button>' +
                        '<input type="hidden" name="items[' + index + '][mode]" value="add">' +
                    '</div>' +
                '</div>' +
                '<div class="field qty">' +
                    '<label>Jumlah</label>' +
                    '<div class="stepper">' +
                        '<button type="button" data-step="-1" aria-label="Kurangi">' + IC_MINUS + '</button>' +
                        '<input type="number" name="items[' + index + '][jumlah]" min="1" value="1">' +
                        '<button type="button" data-step="1" aria-label="Tambah">' + IC_PLUS + '</button>' +
                    '</div>' +
                '</div>' +
                '<button type="button" class="row-remove" title="Hapus baris">' + IC_X + '</button>';
            return wrap;
        }

        rows.addEventListener('click', function (e) {
            // Pilih item autocomplete
            const acItem = e.target.closest('.ac-item');
            if (acItem) { selectItem(acItem); return; }

            // Stepper +/- (jumlah perubahan, minimal 1)
            const stepBtn = e.target.closest('button[data-step]');
            if (stepBtn) {
                const input = stepBtn.parentElement.querySelector('input[type=number]');
                const next = (parseInt(input.value || '1', 10) || 1) + parseInt(stepBtn.dataset.step, 10);
                input.value = Math.max(1, next);
                return;
            }

            // Toggle Tambah / Kurangi
            const modeBtn = e.target.closest('.mode-btn');
            if (modeBtn) {
                const group = modeBtn.closest('.mode-toggle');
                group.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
                modeBtn.classList.add('active');
                group.querySelector('input[type=hidden]').value = modeBtn.dataset.mode;
                return;
            }

            // Hapus baris
            const rm = e.target.closest('.row-remove');
            if (rm) {
                if (rows.querySelectorAll('.item-row').length > 1) {
                    rm.closest('.item-row').remove();
                } else {
                    const row = rm.closest('.item-row');
                    row.querySelector('.ac-input').value = '';
                    row.querySelector('.ac-id').value = '';
                    row.querySelector('input[type=number]').value = 1;
                    const group = row.querySelector('.mode-toggle');
                    group.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
                    group.querySelector('.mode-btn[data-mode="add"]').classList.add('active');
                    group.querySelector('input[type=hidden]').value = 'add';
                }
            }
        });

        // Tutup dropdown saat klik di luar
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.ac')) closeAllLists();
        });

        const addBtn = document.getElementById('add-item');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                rows.appendChild(buildRow(counter++));
            });
        }

        // ---------- Pencarian tabel stok (client-side) ----------
        const tSearch = document.getElementById('stok-search');
        const tBody = document.getElementById('stok-tbody');
        if (tSearch && tBody) {
            tSearch.addEventListener('input', function () {
                const qq = this.value.trim().toLowerCase();
                let visible = 0;
                tBody.querySelectorAll('tr:not(.no-match)').forEach(tr => {
                    const show = (tr.dataset.nama || '').toLowerCase().includes(qq);
                    tr.style.display = show ? '' : 'none';
                    if (show) visible++;
                });
                const nm = tBody.querySelector('.no-match');
                if (nm) nm.style.display = visible === 0 ? '' : 'none';
            });
        }

        // ---------- Urutkan tabel stok ----------
        const tSort = document.getElementById('stok-sort');
        if (tSort && tBody) {
            const rowsAll = Array.from(tBody.querySelectorAll('tr:not(.no-match)'));
            const noMatch = tBody.querySelector('.no-match');
            const urutanAwal = rowsAll.slice();
            const jml = tr => parseInt(tr.dataset.jumlah || '0', 10);

            tSort.addEventListener('change', function () {
                let urut;
                switch (this.value) {
                    case 'jumlah-asc':  urut = rowsAll.slice().sort((a, b) => jml(a) - jml(b)); break;
                    case 'jumlah-desc': urut = rowsAll.slice().sort((a, b) => jml(b) - jml(a)); break;
                    case 'habis':
                        urut = rowsAll.slice().sort((a, b) => (jml(a) === 0 ? 0 : 1) - (jml(b) === 0 ? 0 : 1) || jml(a) - jml(b));
                        break;
                    default: urut = urutanAwal.slice();
                }
                urut.forEach(tr => tBody.insertBefore(tr, noMatch));
            });
        }
    })();
</script>
@endpush

@push('scripts')
<script>

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
