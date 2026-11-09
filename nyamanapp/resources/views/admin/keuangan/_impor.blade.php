{{-- Jendela impor CSV. Parameter: $impor (url), $kolom (format), $contoh (baris contoh). --}}
<dialog class="keu-dlg" id="dlg-impor" data-judul-baru="Import CSV">
    <form method="POST" action="{{ $impor }}" enctype="multipart/form-data" onsubmit="this.querySelector('[type=submit]').disabled = true;">
        @csrf
        <div class="dlg-kepala">
            <h2 data-judul>Import CSV</h2>
            <button type="button" class="dlg-x" data-tutup aria-label="Tutup">@include('partials.icon', ['name' => 'x', 'class' => 'ic-sm'])</button>
        </div>
        <div class="dlg-isi">
            <div class="fld">
                <label for="berkas-impor">Berkas CSV</label>
                <input type="file" id="berkas-impor" name="berkas" accept=".csv,text/csv,text/plain" required>
            </div>
            <div class="dlg-catatan">
                Kolom: <b>{{ $kolom }}</b>.<br>
                Contoh: <code>{{ $contoh }}</code><br>
                Pemisah koma atau titik koma; baris judul boleh ada. Data yang sudah ada untuk tanggal/bulan
                yang sama akan diperbarui. Bila ada satu baris salah, tidak ada yang disimpan.
                Cara termudah: <b>Export CSV</b> dulu, isi di Excel, lalu impor.
            </div>
        </div>
        <div class="dlg-kaki">
            <div class="kanan">
                <button type="button" class="btn btn-ghost" data-tutup>Batal</button>
                <button type="submit" class="btn btn-accent">Import</button>
            </div>
        </div>
    </form>
</dialog>
