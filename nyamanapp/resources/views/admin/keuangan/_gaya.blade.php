{{-- Gaya & perilaku bersama halaman Keuangan.

     Pola keempat halaman sama:
       1. Angka terpenting besar di atas (hero).
       2. Satu tombol utama "Catat …" — formulirnya muncul sebagai jendela
          (dialog), bukan formulir besar yang selalu terbuka.
       3. Ekspor/impor ada di menu "Lainnya".
       4. Baris tabel diklik untuk mengubah; Hapus ada di dalam jendela.

     Jendela: <dialog class="keu-dlg" id="…">. Pembuka: [data-buka="id"] dengan
     [data-isi="{{ json_encode([...]) }}"] — kolom, _judul, _hapus (url), _kunci; di-escape Blade, aman untuk tanda petik.
     Isian rupiah: <input data-rupiah="id-hidden"> + <input type="hidden" id="id-hidden" name="…">. --}}
@once
@push('head')
<style>
    /* ---------- Kepala halaman ---------- */
    .keu-kepala { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .keu-kepala > div:first-child { flex: 1 1 260px; min-width: 0; }
    .keu-kepala h1 { margin: 0; font-size: 1.6rem; font-weight: 800; letter-spacing: -.025em; }
    .keu-kepala p { margin: 2px 0 0; color: var(--muted); font-size: .88rem; }
    .keu-aksi { display: flex; gap: 8px; align-items: center; }
    .keu-aksi .menu-edit > summary { padding: 10px 16px; }

    /* ---------- Angka utama ---------- */
    .hero { display: grid; grid-template-columns: minmax(0, 1.5fr) repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; margin-bottom: 22px; }
    .hero-utama {
        padding: 20px 22px; border-radius: 18px; border: 1px solid var(--border);
        background: radial-gradient(420px 220px at 0% 0%, rgba(124,58,237,.30), transparent 70%), linear-gradient(180deg, var(--surface), var(--bg-2));
    }
    .hero .lbl { font-size: .7rem; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); font-weight: 800; }
    .hero-utama .angka { font-size: clamp(1.7rem, 4.6vw, 2.5rem); font-weight: 800; letter-spacing: -.03em; font-variant-numeric: tabular-nums; margin: 6px 0 8px; white-space: nowrap; }
    .hero-utama .ket { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; font-size: .82rem; color: var(--muted); }
    .hero-stat { padding: 16px 18px; display: flex; flex-direction: column; justify-content: center; }
    .hero-stat .n { font-size: clamp(1.02rem, 2.1vw, 1.3rem); font-weight: 800; font-variant-numeric: tabular-nums; margin-top: 5px; white-space: nowrap; }
    .hero-stat .sub { font-size: .76rem; color: var(--muted); margin-top: 3px; }
    @media (max-width: 720px) { .hero { grid-template-columns: 1fr 1fr; } .hero-utama { grid-column: 1 / -1; } }

    .chip { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; padding: 3px 10px; font-size: .76rem; font-weight: 700; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .chip.naik  { background: var(--ok-soft, rgba(74,222,128,.12)); color: var(--ok); }
    .chip.turun { background: var(--danger-soft); color: var(--danger); }
    .chip.tetap { background: var(--hover); color: var(--muted); }
    .chip.awas  { background: var(--warn-soft); color: var(--warn); }
    .naik { color: var(--ok); } .turun { color: var(--danger); } .tetap { color: var(--muted); }

    /* Ajakan kecil di atas — mis. "saldo hari ini belum dicatat". */
    .ajak {
        display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
        padding: 12px 16px; margin-bottom: 18px; border-radius: 14px; font-size: .88rem;
        background: var(--accent-soft); border: 1px solid var(--border);
    }

    .ajak[hidden], .keu-dlg [hidden] { display: none !important; }

    /* ---------- Kartu & tabel ---------- */
    .keu-kartu { padding: 18px 20px; margin-bottom: 22px; }
    .keu-kartu-kepala { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
    .keu-kartu-kepala h2 { margin: 0; font-size: 1.05rem; font-weight: 800; letter-spacing: -.015em; }
    .keu-kartu-kepala .ket { color: var(--muted); font-size: .8rem; }
    .keu-tabel-wrap { overflow-x: auto; margin: 0 -8px; }
    .keu-tabel { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
    .keu-tabel th { text-align: left; font-size: .68rem; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); font-weight: 800; padding: 10px 12px; border-bottom: 1px solid var(--border-2); white-space: nowrap; }
    .keu-tabel td { padding: 12px; border-bottom: 1px solid var(--border-2); font-size: .9rem; white-space: nowrap; }
    .keu-tabel tr:last-child td { border-bottom: none; }
    .keu-tabel .num { text-align: right; }
    .keu-tabel .redup { color: var(--muted); font-size: .78rem; display: block; white-space: normal; }
    tr.bisa-klik { cursor: pointer; }
    tr.bisa-klik:hover td, tr.bisa-klik:focus-visible td { background: var(--hover); }
    tr.bisa-klik:focus-visible { outline: none; }
    .keu-tabel td.pena { width: 1%; text-align: right; color: var(--muted); }
    tr.bisa-klik:hover td.pena { color: var(--accent); }
    .keu-kosong { text-align: center; color: var(--muted); padding: 34px 16px; }

    /* ---------- Jendela isian ---------- */
    dialog.keu-dlg {
        width: min(500px, calc(100vw - 24px)); max-height: calc(100vh - 32px); padding: 0;
        border: 1px solid var(--border); border-radius: 20px; color: var(--text);
        background: linear-gradient(180deg, var(--surface-2), var(--bg-2));
        box-shadow: 0 40px 90px -30px rgba(0,0,0,.95);
    }
    dialog.keu-dlg::backdrop { background: rgba(5, 3, 10, .72); backdrop-filter: blur(4px); }
    dialog.keu-dlg[open] { animation: dlgMuncul .18s ease-out; }
    @keyframes dlgMuncul { from { transform: translateY(10px) scale(.98); } to { transform: none; } }
    @media (prefers-reduced-motion: reduce) { dialog.keu-dlg[open] { animation: none; } }
    .dlg-kepala { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 20px 4px; }
    .dlg-kepala h2 { margin: 0; font-size: 1.1rem; font-weight: 800; }
    .dlg-x { background: none; border: none; color: var(--muted); cursor: pointer; padding: 4px; display: inline-flex; border-radius: 8px; }
    .dlg-x:hover { color: var(--text); background: var(--hover); }
    .dlg-isi { padding: 12px 20px 6px; display: grid; gap: 14px; }
    .dlg-isi .dua { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 420px) { .dlg-isi .dua { grid-template-columns: 1fr; } }
    .dlg-kaki { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 20px 20px; }
    .dlg-kaki .kanan { display: flex; gap: 8px; margin-left: auto; }
    .dlg-catatan { font-size: .78rem; color: var(--muted); line-height: 1.5; }
    .dlg-galat { padding: 10px 12px; border-radius: 10px; font-size: .84rem; font-weight: 600; background: var(--danger-soft); border: 1px solid var(--danger-line); color: var(--danger); }

    .fld label { display: block; font-size: .7rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; font-weight: 800; }
    .fld label .bank { color: var(--accent); }
    .fld input[type=date], .fld input[type=month], .fld input[type=text], .fld input[type=file], .fld textarea {
        width: 100%; font-family: inherit; font-size: 1rem; color: var(--text); color-scheme: dark;
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; padding: 11px 13px;
    }
    .fld textarea { min-height: 70px; resize: vertical; font-size: .92rem; }
    .fld input:focus, .fld textarea:focus { outline: none; border-color: var(--accent); }
    .fld input[readonly] { color: var(--muted); }
    .rp-input { display: flex; align-items: stretch; }
    .rp-input .rp { display: flex; align-items: center; font-weight: 700; font-size: .88rem; color: var(--muted); border: 1px solid var(--border-2); border-right: none; border-radius: 10px 0 0 10px; padding: 0 12px; background: var(--surface-2); }
    .rp-input input { border-radius: 0 10px 10px 0 !important; font-weight: 700; font-variant-numeric: tabular-nums; }
    .hasil { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 12px 14px; border-radius: 12px; background: var(--accent-soft); border: 1px solid var(--border); }
    .hasil .lbl { font-size: .66rem; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); font-weight: 800; }
    .hasil .n { font-size: 1.12rem; font-weight: 800; font-variant-numeric: tabular-nums; margin-top: 2px; }
    .centang { display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700; padding: 11px 14px; border-radius: 10px; border: 1px solid var(--border-2); background: var(--bg); }
    .centang input { width: 19px; height: 19px; accent-color: var(--ok); }
    .centang:has(input:checked) { color: var(--ok); }

    /* ---------- Pemberitahuan impor ---------- */
    .keu-info { padding: 12px 16px; margin-bottom: 18px; border-radius: 14px; font-size: .84rem; line-height: 1.6; }
    .keu-info.galat { background: var(--danger-soft); border: 1px solid var(--danger-line); color: var(--danger); }
    .keu-info.awas  { background: var(--warn-soft); border: 1px solid var(--warn-line); color: var(--warn); }
    .keu-info summary { cursor: pointer; font-weight: 800; }
    .keu-info ul { margin: 6px 0 0; padding-left: 18px; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    // ---------- Isian rupiah: tampil berformat, terkirim angka murni ----------
    function pasangRupiah(akar) {
        akar.querySelectorAll('input[data-rupiah]').forEach(function (el) {
            if (el.dataset.siap) return;
            el.dataset.siap = '1';
            const tersembunyi = document.getElementById(el.dataset.rupiah);
            const tulis = function (dariTersembunyi) {
                const sumber = dariTersembunyi ? tersembunyi.value : el.value;
                const bersih = String(sumber || '').replace(/[^\d-]/g, '').replace(/(?!^)-/g, '');
                const angka = bersih === '' || bersih === '-' ? null : parseInt(bersih, 10);
                tersembunyi.value = angka === null ? '' : String(angka);
                el.value = angka === null ? '' : angka.toLocaleString('id-ID');
            };
            el.addEventListener('input', function () { tulis(false); });
            el._tulisDariTersembunyi = function () { tulis(true); };
            tulis(true);
        });
    }

    // ---------- Isi jendela dari data baris ----------
    function isi(dlg, data) {
        const judul = dlg.querySelector('[data-judul]');
        if (judul) judul.textContent = data._judul || dlg.dataset.judulBaru || '';

        Object.keys(data).forEach(function (k) {
            if (k.charAt(0) === '_') return;
            const f = dlg.querySelector('[name="' + k + '"]');
            if (!f) return;
            if (f.type === 'checkbox') { f.checked = !!data[k] && data[k] !== '0'; return; }
            f.value = data[k] === null || data[k] === undefined ? '' : data[k];
            const tampil = f.id ? dlg.querySelector('[data-rupiah="' + f.id + '"]') : null;
            if (tampil && tampil._tulisDariTersembunyi) tampil._tulisDariTersembunyi();
        });

        // Kunci (tanggal/bulan) tidak bisa diganti saat mengubah catatan lama:
        // mengganti kuncinya berarti membuat catatan lain, bukan mengubah yang ini.
        dlg.querySelectorAll('[data-kunci]').forEach(function (f) { f.readOnly = !!data._kunci; });

        const hapus = dlg.querySelector('[data-hapus]');
        const formHapus = hapus ? document.getElementById(hapus.getAttribute('form')) : null;
        if (hapus) {
            hapus.hidden = !(data._hapus && formHapus);
            if (data._hapus && formHapus) {
                formHapus.action = data._hapus;
                formHapus.dataset.tanya = data._hapusTanya || 'Hapus catatan ini?';
            }
        }
        dlg.dispatchEvent(new CustomEvent('keu:isi'));
    }

    window.keuBuka = function (id, data) {
        const dlg = document.getElementById(id);
        if (!dlg || typeof dlg.showModal !== 'function') return;
        if (data) isi(dlg, data); else dlg.dispatchEvent(new CustomEvent('keu:isi'));
        dlg.showModal();
        const pertama = dlg.querySelector('input:not([type=hidden]):not([readonly]), textarea');
        if (pertama) setTimeout(function () { pertama.focus(); }, 30);
    };

    document.addEventListener('click', function (e) {
        const tutup = e.target.closest('[data-tutup]');
        if (tutup) { tutup.closest('dialog').close(); return; }

        const pemicu = e.target.closest('[data-buka]');
        if (!pemicu || e.target.closest('[data-jangan-buka]')) return;
        e.preventDefault();
        const menu = pemicu.closest('details.menu-edit');
        if (menu) menu.open = false;
        keuBuka(pemicu.dataset.buka, pemicu.dataset.isi ? JSON.parse(pemicu.dataset.isi) : {});
    });

    // Baris yang bisa diklik juga bisa dibuka dengan Enter.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        const baris = e.target.closest && e.target.closest('tr[data-buka]');
        if (baris && e.target === baris) { e.preventDefault(); baris.click(); }
    });

    document.querySelectorAll('dialog.keu-dlg').forEach(function (d) {
        d.addEventListener('click', function (e) { if (e.target === d) d.close(); });   // klik di luar kotak
    });
    document.querySelectorAll('form.dlg-hapus').forEach(function (f) {
        f.addEventListener('submit', function (e) { if (!confirm(f.dataset.tanya || 'Hapus?')) e.preventDefault(); });
    });

    pasangRupiah(document);

    // Validasi gagal: buka lagi jendela yang tadi dikirim, isiannya tetap.
    // Pesan galatnya ikut ditulis di dalam jendela — pesan di atas halaman
    // tertutup bayangan jendela.
    const bukaLagi = @json($errors->any() ? old('_dialog') : null);
    if (bukaLagi) {
        const dlg = document.getElementById(bukaLagi);
        const isiDlg = dlg ? dlg.querySelector('.dlg-isi') : null;
        if (isiDlg) {
            const g = document.createElement('div');
            g.className = 'dlg-galat';
            g.textContent = @json($errors->first());
            isiDlg.prepend(g);
        }
        keuBuka(bukaLagi, null);
    }
})();
</script>
@endpush
@endonce
