@extends('layouts.panel')

@section('title', 'Buat Invoice')

@push('head')
<style>
    .inv-card { padding: 28px; max-width: 760px; }
    .fld { margin-bottom: 20px; }
    .fld > label { display: block; font-size: .75rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
    .fld input[type=text], .fld input[type=number], .fld input[type=date], .fld textarea {
        width: 100%; font-family: inherit; font-size: 1rem; color: var(--text);
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; padding: 12px 14px;
    }
    .fld textarea { min-height: 90px; resize: vertical; line-height: 1.55; }
    .fld input:focus, .fld textarea:focus { outline: none; border-color: var(--accent); }
    .fld input[type=date] { color-scheme: dark; }
    .fld .hint { margin-top: 6px; font-size: .78rem; color: var(--muted); }

    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

    .nomor-chip {
        display: inline-flex; align-items: center; gap: 8px; margin-bottom: 22px;
        background: var(--accent-soft); border: 1px solid var(--border); color: var(--accent);
        border-radius: 999px; padding: 7px 15px; font-weight: 700; font-size: .84rem;
        font-variant-numeric: tabular-nums;
    }

    /* ---------- Baris rincian (kamar & biaya) ---------- */
    .baris {
        border: 1px solid var(--border-2); border-radius: 14px; background: var(--bg);
        padding: 14px; margin-bottom: 10px;
    }
    .baris-atas { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
    .baris-no {
        font-size: .7rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
        color: var(--muted); flex: 1 1 auto;
    }
    .baris-hapus {
        background: transparent; border: 1px solid var(--danger-line); color: var(--danger);
        border-radius: 9px; cursor: pointer; padding: 5px 10px; font-family: inherit;
        font-size: .76rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;
    }
    .baris-hapus:hover { background: var(--danger-soft); }

    .baris-grid { display: grid; grid-template-columns: 1.5fr 1.2fr; gap: 10px; }
    .baris-angka { display: grid; grid-template-columns: 1fr 1fr 1.4fr; gap: 10px; margin-top: 10px; }
    .baris label { display: block; font-size: .68rem; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); margin-bottom: 5px; }
    .baris input {
        width: 100%; font-family: inherit; font-size: .93rem; color: var(--text);
        background: var(--surface); border: 1px solid var(--border-2); border-radius: 9px; padding: 10px 12px;
    }
    .baris input:focus { outline: none; border-color: var(--accent); }
    .baris-sub {
        margin-top: 10px; text-align: right; font-size: .84rem; color: var(--muted);
        font-variant-numeric: tabular-nums;
    }
    .baris-sub b { color: var(--accent); font-size: .95rem; }

    .tambah-baris {
        display: inline-flex; align-items: center; gap: 7px; cursor: pointer; font-family: inherit;
        background: var(--accent-soft); color: var(--accent); border: 1px dashed var(--border);
        border-radius: 11px; padding: 11px 18px; font-weight: 700; font-size: .86rem;
    }
    .tambah-baris:hover { background: var(--hover); }
    .tambah-grup { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 4px; }

    .seksi-judul {
        font-size: .75rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted);
        font-weight: 700; margin: 26px 0 10px;
    }

    .opsi-grup { display: flex; gap: 10px; flex-wrap: wrap; }
    .opsi {
        flex: 1 1 170px; display: flex; align-items: center; gap: 11px; cursor: pointer;
        border: 1px solid var(--border-2); border-radius: 12px; padding: 13px 15px;
        background: var(--surface); transition: border-color .15s, background .15s;
    }
    .opsi:hover { border-color: var(--accent); background: var(--hover); }
    .opsi.aktif { border-color: var(--accent); background: var(--accent-soft); }
    .opsi input { width: 17px; height: 17px; accent-color: var(--accent); cursor: pointer; flex: 0 0 auto; }
    .opsi .t { font-weight: 700; font-size: .9rem; }

    .total-box {
        display: flex; align-items: baseline; justify-content: space-between; gap: 14px; flex-wrap: wrap;
        border: 1px solid var(--border); border-radius: 14px; background: var(--accent-soft);
        padding: 16px 18px; margin-top: 18px;
    }
    .total-box .l { font-size: .78rem; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
    .total-box .n { font-size: 1.5rem; font-weight: 800; color: var(--accent); font-variant-numeric: tabular-nums; }

    .form-actions { display: flex; gap: 12px; margin-top: 26px; padding-top: 22px; border-top: 1px solid var(--border-2); }

    @media (max-width: 620px) {
        .grid-2, .baris-grid { grid-template-columns: 1fr; }
        .baris-angka { grid-template-columns: 1fr 1fr; }
    }
</style>
@endpush

@php
    /**
     * Setelah submit yang gagal, old() dipakai supaya isian staff tidak
     * hilang. Tetapi bila kegagalannya justru karena baris kamar kosong,
     * old('kamar') ikut kosong dan formulir akan tampil tanpa satu baris pun.
     * Karena itu isian bawaan dipakai kembali saat old-nya kosong.
     */
    $barisKamar = old('kamar', $awal['kamar']);
    if (! is_array($barisKamar) || $barisKamar === []) {
        $barisKamar = $awal['kamar'];
    }

    $barisBiaya = old('biaya', $awal['biaya']);
    if (! is_array($barisBiaya)) {
        $barisBiaya = [];
    }
@endphp

@section('content')
    <div class="page-head">
        <h1>Buat Invoice</h1>
        <p>Bisa memuat beberapa kamar sekaligus, plus biaya tambahan.</p>
    </div>

    <div class="card inv-card">
        <div class="nomor-chip">@include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-xs']) {{ $nomor }}</div>

        <form method="POST" action="{{ \App\Support\Panel::rute('invoices.store') }}">
            @csrf
            <input type="hidden" name="booking_id" value="{{ old('booking_id', $awal['booking_id']) }}">

            <div class="fld">
                <label for="nama_tamu">Nama Tamu</label>
                <input id="nama_tamu" type="text" name="nama_tamu" required
                       value="{{ old('nama_tamu', $awal['nama_tamu']) }}" placeholder="Contoh: Rizky Pratama">
            </div>

            <div class="grid-2">
                <div class="fld">
                    <label for="tanggal_masuk">Tanggal Masuk</label>
                    <input id="tanggal_masuk" type="date" name="tanggal_masuk"
                           value="{{ old('tanggal_masuk', $awal['tanggal_masuk']) }}">
                </div>
                <div class="fld">
                    <label for="tanggal_keluar">Tanggal Keluar</label>
                    <input id="tanggal_keluar" type="date" name="tanggal_keluar"
                           value="{{ old('tanggal_keluar', $awal['tanggal_keluar']) }}">
                </div>
            </div>

            {{-- ---------- Baris kamar ---------- --}}
            <div class="seksi-judul">Rincian Kamar</div>
            <div id="kamar-list">
                @foreach ($barisKamar as $i => $k)
                    <div class="baris" data-baris="kamar">
                        <div class="baris-atas">
                            <span class="baris-no">Kamar <span class="nomor-urut">{{ $i + 1 }}</span></span>
                            <button type="button" class="baris-hapus" data-hapus>
                                @include('partials.icon', ['name' => 'x', 'class' => 'ic-xs']) Hapus
                            </button>
                        </div>
                        <div class="baris-grid">
                            <div>
                                <label>Tipe Kamar</label>
                                <input type="text" name="kamar[{{ $i }}][uraian]" required list="tipe-list"
                                       value="{{ $k['uraian'] }}" placeholder="Kamar King">
                            </div>
                            <div>
                                <label>Nomor Kamar</label>
                                <input type="text" name="kamar[{{ $i }}][keterangan]"
                                       value="{{ $k['keterangan'] }}" placeholder="101, 102">
                            </div>
                        </div>
                        <div class="baris-angka">
                            <div>
                                <label>Jumlah Kamar</label>
                                <input type="number" name="kamar[{{ $i }}][jumlah_kamar]" min="1" max="99" required
                                       value="{{ $k['jumlah_kamar'] }}" data-hitung>
                            </div>
                            <div>
                                <label>Malam</label>
                                <input type="number" name="kamar[{{ $i }}][malam]" min="1" max="365" required
                                       value="{{ $k['malam'] }}" data-hitung>
                            </div>
                            <div>
                                <label>Harga / Malam</label>
                                <input type="number" name="kamar[{{ $i }}][harga]" min="0" step="1000" required
                                       value="{{ $k['harga'] }}" data-hitung>
                            </div>
                        </div>
                        <div class="baris-sub">Subtotal: <b class="sub">Rp 0</b></div>
                    </div>
                @endforeach
            </div>
            <button type="button" class="tambah-baris" id="tambah-kamar">
                @include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm']) Tambah Kamar
            </button>

            <datalist id="tipe-list">
                <option value="Kamar King"></option>
                <option value="Kamar Twin"></option>
                <option value="Kamar Family"></option>
            </datalist>

            {{-- ---------- Baris biaya tambahan ---------- --}}
            <div class="seksi-judul">Biaya Tambahan</div>
            <div id="biaya-list">
                @foreach ($barisBiaya as $i => $b)
                    <div class="baris" data-baris="biaya">
                        <div class="baris-atas">
                            <span class="baris-no">Biaya <span class="nomor-urut">{{ $i + 1 }}</span></span>
                            <button type="button" class="baris-hapus" data-hapus>
                                @include('partials.icon', ['name' => 'x', 'class' => 'ic-xs']) Hapus
                            </button>
                        </div>
                        <div class="baris-grid">
                            <div>
                                <label>Uraian</label>
                                <input type="text" name="biaya[{{ $i }}][uraian]"
                                       value="{{ $b['uraian'] }}" placeholder="Biaya late check-out">
                            </div>
                            <div>
                                <label>Keterangan</label>
                                <input type="text" name="biaya[{{ $i }}][keterangan]"
                                       value="{{ $b['keterangan'] ?? '' }}" placeholder="2 jam">
                            </div>
                        </div>
                        <div class="baris-angka" style="grid-template-columns: 1fr">
                            <div>
                                <label>Jumlah (Rp)</label>
                                <input type="number" name="biaya[{{ $i }}][harga]" min="0" step="1000"
                                       value="{{ $b['harga'] }}" data-hitung>
                            </div>
                        </div>
                        <div class="baris-sub">Subtotal: <b class="sub">Rp 0</b></div>
                    </div>
                @endforeach
            </div>
            <div class="tambah-grup">
                <button type="button" class="tambah-baris" data-tambah-biaya="Biaya late check-out">
                    @include('partials.icon', ['name' => 'clock', 'class' => 'ic-sm']) Late Check-out
                </button>
                <button type="button" class="tambah-baris" data-tambah-biaya="Biaya early check-in">
                    @include('partials.icon', ['name' => 'clock', 'class' => 'ic-sm']) Early Check-in
                </button>
                <button type="button" class="tambah-baris" data-tambah-biaya="">
                    @include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm']) Biaya Lain
                </button>
            </div>
            <div class="hint" style="margin-top:8px; font-size:.78rem; color:var(--muted)">
                Baris biaya yang jumlahnya 0 tidak akan tercetak.
            </div>

            <div class="total-box">
                <span class="l">Total Tagihan</span>
                <span class="n" id="total-preview">Rp 0</span>
            </div>

            <div class="fld" style="margin-top: 24px">
                <label>Status Pembayaran</label>
                <div class="opsi-grup" id="opsi-grup">
                    <label class="opsi {{ old('lunas', $awal['lunas'] ? '1' : '0') == '1' ? 'aktif' : '' }}">
                        <input type="radio" name="lunas" value="1" @checked(old('lunas', $awal['lunas'] ? '1' : '0') == '1')>
                        <span class="t">Lunas</span>
                    </label>
                    <label class="opsi {{ old('lunas', $awal['lunas'] ? '1' : '0') == '0' ? 'aktif' : '' }}">
                        <input type="radio" name="lunas" value="0" @checked(old('lunas', $awal['lunas'] ? '1' : '0') == '0')>
                        <span class="t">Belum Lunas</span>
                    </label>
                </div>
            </div>

            <div class="fld">
                <label for="catatan">Catatan <span style="text-transform:none; letter-spacing:0">(opsional)</span></label>
                <textarea id="catatan" name="catatan" maxlength="1000"
                          placeholder="Mis. pembayaran via transfer BCA">{{ old('catatan', $awal['catatan']) }}</textarea>
            </div>

            <div class="form-actions">
                <a href="{{ \App\Support\Panel::rute('invoices.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-accent">Buat &amp; Cetak</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const listKamar = document.getElementById('kamar-list');
    const listBiaya = document.getElementById('biaya-list');
    const totalEl   = document.getElementById('total-preview');
    const grup      = document.getElementById('opsi-grup');

    const rp = n => 'Rp ' + Math.max(0, n).toLocaleString('id-ID');
    const angka = el => Math.max(0, parseInt((el && el.value) || '0', 10) || 0);

    // Nama field diberi indeks ulang setiap ada baris ditambah/dihapus,
    // supaya array yang diterima server tidak berlubang di tengah.
    function reindex(list, prefix) {
        Array.from(list.children).forEach(function (baris, i) {
            baris.querySelectorAll('input').forEach(function (inp) {
                if (inp.name) inp.name = inp.name.replace(/^(\w+)\[\d+\]/, prefix + '[' + i + ']');
            });
            const no = baris.querySelector('.nomor-urut');
            if (no) no.textContent = i + 1;
        });
    }

    function hitung() {
        let total = 0;

        Array.from(listKamar.children).forEach(function (b) {
            const inp = b.querySelectorAll('[data-hitung]');
            const sub = angka(inp[0]) * angka(inp[1]) * angka(inp[2]);
            b.querySelector('.sub').textContent = rp(sub);
            total += sub;
        });

        Array.from(listBiaya.children).forEach(function (b) {
            const sub = angka(b.querySelector('[data-hitung]'));
            b.querySelector('.sub').textContent = rp(sub);
            total += sub;
        });

        totalEl.textContent = rp(total);

        // Baris kamar terakhir tidak boleh bisa dihapus: invoice harus punya
        // minimal satu kamar, dan server akan menolaknya kalau kosong.
        const tombol = listKamar.querySelectorAll('[data-hapus]');
        tombol.forEach(t => { t.style.display = tombol.length > 1 ? '' : 'none'; });
    }

    function barisKamar(i) {
        return '<div class="baris" data-baris="kamar">'
            + '<div class="baris-atas"><span class="baris-no">Kamar <span class="nomor-urut">' + (i + 1) + '</span></span>'
            + '<button type="button" class="baris-hapus" data-hapus>Hapus</button></div>'
            + '<div class="baris-grid">'
            + '<div><label>Tipe Kamar</label><input type="text" name="kamar[' + i + '][uraian]" required list="tipe-list" value="Kamar King" placeholder="Kamar King"></div>'
            + '<div><label>Nomor Kamar</label><input type="text" name="kamar[' + i + '][keterangan]" placeholder="101, 102"></div>'
            + '</div><div class="baris-angka">'
            + '<div><label>Jumlah Kamar</label><input type="number" name="kamar[' + i + '][jumlah_kamar]" min="1" max="99" required value="1" data-hitung></div>'
            + '<div><label>Malam</label><input type="number" name="kamar[' + i + '][malam]" min="1" max="365" required value="1" data-hitung></div>'
            + '<div><label>Harga / Malam</label><input type="number" name="kamar[' + i + '][harga]" min="0" step="1000" required value="300000" data-hitung></div>'
            + '</div><div class="baris-sub">Subtotal: <b class="sub">Rp 0</b></div></div>';
    }

    function barisBiaya(i, uraian) {
        return '<div class="baris" data-baris="biaya">'
            + '<div class="baris-atas"><span class="baris-no">Biaya <span class="nomor-urut">' + (i + 1) + '</span></span>'
            + '<button type="button" class="baris-hapus" data-hapus>Hapus</button></div>'
            + '<div class="baris-grid">'
            + '<div><label>Uraian</label><input type="text" name="biaya[' + i + '][uraian]" value="' + uraian + '" placeholder="Biaya late check-out"></div>'
            + '<div><label>Keterangan</label><input type="text" name="biaya[' + i + '][keterangan]" placeholder="2 jam"></div>'
            + '</div><div class="baris-angka" style="grid-template-columns: 1fr">'
            + '<div><label>Jumlah (Rp)</label><input type="number" name="biaya[' + i + '][harga]" min="0" step="1000" value="0" data-hitung></div>'
            + '</div><div class="baris-sub">Subtotal: <b class="sub">Rp 0</b></div></div>';
    }

    document.getElementById('tambah-kamar').addEventListener('click', function () {
        listKamar.insertAdjacentHTML('beforeend', barisKamar(listKamar.children.length));
        reindex(listKamar, 'kamar');
        hitung();
    });

    document.querySelectorAll('[data-tambah-biaya]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            listBiaya.insertAdjacentHTML('beforeend', barisBiaya(listBiaya.children.length, this.dataset.tambahBiaya));
            reindex(listBiaya, 'biaya');
            hitung();
            const baru = listBiaya.lastElementChild.querySelector('input');
            if (baru) baru.focus();
        });
    });

    document.addEventListener('click', function (e) {
        const b = e.target.closest('[data-hapus]');
        if (!b) return;
        const baris = b.closest('.baris');
        const list  = baris.parentElement;
        if (list === listKamar && list.children.length <= 1) return;
        baris.remove();
        reindex(list, list === listKamar ? 'kamar' : 'biaya');
        hitung();
    });

    document.addEventListener('input', function (e) {
        if (e.target.matches('[data-hitung]')) hitung();
    });

    if (grup) {
        grup.addEventListener('change', function () {
            grup.querySelectorAll('.opsi').forEach(function (l) {
                l.classList.toggle('aktif', l.querySelector('input').checked);
            });
        });
    }

    hitung();
})();
</script>
@endpush
