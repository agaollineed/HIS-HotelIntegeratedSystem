@push('head')
<style>
    .form-card { padding: 28px; max-width: 560px; }
    .fld { margin-bottom: 20px; }
    .fld label { display: block; font-size: .75rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
    .fld input {
        width: 100%; font-family: inherit; font-size: 1rem; color: var(--text);
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; padding: 12px 14px;
    }
    .fld input:focus { outline: none; border-color: var(--accent); }
    .fld .hint { margin-top: 6px; font-size: .78rem; color: var(--muted); }
    .fld.money { position: relative; }
    .fld.money input { padding-left: 42px; }
    .fld.money .prefix {
        position: absolute; left: 14px; bottom: 12px; color: var(--muted);
        font-variant-numeric: tabular-nums;
    }
    .form-actions { display: flex; gap: 12px; margin-top: 26px; padding-top: 22px; border-top: 1px solid var(--border-2); }
    .jumlah-kunci {
        border: 1px solid var(--border-2); border-radius: 10px; background: var(--surface-2);
        padding: 12px 14px; font-size: .95rem; color: var(--text);
    }
    .jumlah-kunci b { font-variant-numeric: tabular-nums; font-size: 1.05rem; color: var(--accent); }
    .jumlah-kunci span { display: block; margin-top: 5px; font-size: .78rem; color: var(--muted); line-height: 1.5; }
</style>
@endpush

<div class="fld">
    <label for="nama">Nama Stok</label>
    <input id="nama" type="text" name="nama" value="{{ old('nama', $stock->nama ?? '') }}"
           placeholder="Contoh: Kopi Robusta 1kg" required autofocus>
</div>

{{-- Jumlah hanya bisa disetel LANGSUNG oleh superadmin.

     Menimpa angka stok tidak meninggalkan selisih yang bisa dibaca: 40 yang
     berubah menjadi 30 terlihat sama persis, entah sepuluh unit terpakai,
     hilang, atau salah hitung sejak awal. Karena itu pergerakan jumlah
     dialirkan lewat formulir tambah/kurangi yang mencatat aksi dan selisihnya
     di log stok. Formulir ini mengurus IDENTITAS item — nama dan harganya. --}}
@if (auth()->user()->bolehSetJumlahStok())
<div class="fld">
    <label for="jumlah">Jumlah</label>
    <input id="jumlah" type="number" name="jumlah" min="0" value="{{ old('jumlah', $stock->jumlah ?? 0) }}" required>
    <div class="hint">Jumlah unit saat ini. Nilai ini juga bisa diubah oleh staff.</div>
@else
    <div class="fld">
        <label>Jumlah</label>
        <div class="jumlah-kunci">
            <b>{{ $stock->jumlah ?? 0 }}</b> unit
            <span>Diubah lewat halaman <a href="{{ route('admin.stocks.jumlah') }}">Tambah / Kurangi Stok</a>, supaya setiap pergerakan tercatat di log.</span>
        </div>
    </div>
@endif
</div>

<div class="fld money">
    <label for="harga">Harga</label>
    <span class="prefix">Rp</span>
    <input id="harga" type="number" name="harga" min="0" value="{{ old('harga', $stock->harga ?? 0) }}" required>
    <div class="hint">Harga per unit dalam Rupiah.</div>
</div>
