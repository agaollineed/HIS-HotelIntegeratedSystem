{{-- Filter daftar pesanan pada tanggal terpilih: nama/nomor kamar + tombol
     Tiket.com, Umum, Breakfast, DP, Belum Lunas. Dipakai panel staff dan
     admin. Penyaringannya di peramban, jadi hasilnya seketika.

     Dalam satu kelompok berlaku "atau" (Tiket.com atau Umum), antarkelompok
     "dan" (Tiket.com dan Breakfast). Kartu pesanan cukup diberi atribut
     data-kartu, data-cari, dan data-filter. --}}
@if ($dayGroups->isNotEmpty())
    @php($jumlahFilter = \App\Support\FilterKamar::hitung($dayGroups))
    <div class="filter-bar">
        @include('partials.icon', ['name' => 'search', 'class' => 'ic-sm'])
        <input type="text" id="filter-tamu" autocomplete="off" placeholder="Filter nama tamu atau nomor kamar…">
        <span class="hitung" id="filter-hitung"></span>
        <button type="button" class="bersih" id="filter-bersih" title="Hapus filter" hidden>
            @include('partials.icon', ['name' => 'x', 'class' => 'ic-sm'])
        </button>
    </div>
    <div class="filter-chip" role="group" aria-label="Saring pesanan">
        @foreach (\App\Support\FilterKamar::PILIHAN as $kunci => [$label, $kelompok])
            <button type="button" class="chip" data-chip="{{ $kunci }}" data-kelompok="{{ $kelompok }}"
                    aria-pressed="false" @disabled($jumlahFilter[$kunci] === 0)>
                {{ $label }} <span class="n">{{ $jumlahFilter[$kunci] }}</span>
            </button>
        @endforeach
    </div>
@endif

@once
@push('head')
<style>
    .filter-bar {
        display: flex; align-items: center; gap: 9px; flex-wrap: wrap;
        border: 1px solid var(--border-2); border-radius: 12px; background: var(--bg);
        padding: 4px 14px; margin: 14px 0 10px;
    }
    .filter-bar .ic-sm { color: var(--muted); flex: 0 0 auto; }
    .filter-bar input {
        flex: 1 1 200px; font-family: inherit; font-size: .93rem; color: var(--text);
        background: transparent; border: none; outline: none; padding: 10px 0;
    }
    .filter-bar input::placeholder { color: var(--muted); }
    .filter-bar .hitung { font-size: .78rem; color: var(--muted); font-variant-numeric: tabular-nums; }
    .filter-bar .bersih { background: none; border: none; cursor: pointer; color: var(--muted); display: inline-flex; padding: 3px; }
    .filter-bar .bersih:hover { color: var(--text); }
    .filter-bar .bersih[hidden] { display: none !important; }

    .filter-chip { display: flex; gap: 7px; flex-wrap: wrap; margin: 0 0 16px; }
    .filter-chip .chip {
        font-family: inherit; font-size: .78rem; font-weight: 700; cursor: pointer;
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 12px; border-radius: 999px;
        background: var(--bg); color: var(--muted); border: 1px solid var(--border-2);
        transition: background .15s, color .15s, border-color .15s;
    }
    .filter-chip .chip .n {
        font-size: .68rem; padding: 0 6px; border-radius: 999px; background: rgba(255,255,255,.06);
        font-variant-numeric: tabular-nums;
    }
    .filter-chip .chip:hover:not(:disabled) { color: var(--text); border-color: var(--border); }
    .filter-chip .chip[aria-pressed="true"] { background: var(--grad); color: #fff; border-color: transparent; }
    .filter-chip .chip[aria-pressed="true"] .n { background: rgba(255,255,255,.22); }
    .filter-chip .chip:disabled { opacity: .45; cursor: default; }

    [data-kartu][hidden] { display: none !important; }
    .filter-kosong { text-align: center; color: var(--muted); padding: 26px 20px; }
    .filter-kosong[hidden] { display: none !important; }
</style>
@endpush

@push('scripts')
<script>
    // Dipasang lewat delegasi pada document, karena panel tanggal diganti
    // isinya lewat fetch — pendengar pada elemen lama ikut hilang.
    // Pilihan tombol disimpan di sini supaya tetap berlaku saat tanggal diganti.
    window.filterKamarAktif = window.filterKamarAktif || new Set();

    function jalankanFilter() {
        const aktif = window.filterKamarAktif;
        const input = document.getElementById('filter-tamu');
        const kata = input ? input.value.trim().toLowerCase() : '';

        // Kelompokkan pilihan: sumber / bf / bayar.
        const perKelompok = {};
        document.querySelectorAll('#day-panel .filter-chip .chip').forEach(function (c) {
            const on = aktif.has(c.dataset.chip);
            c.setAttribute('aria-pressed', on ? 'true' : 'false');
            if (on) (perKelompok[c.dataset.kelompok] = perKelompok[c.dataset.kelompok] || []).push(c.dataset.chip);
        });

        const kartu = document.querySelectorAll('#day-panel [data-kartu]');
        let tampil = 0;

        kartu.forEach(function (k) {
            const teks = (k.dataset.cari || k.textContent || '').toLowerCase();
            const tag = (k.dataset.filter || '').split(' ');
            const cocokKata = kata === '' || teks.includes(kata);
            const cocokChip = Object.values(perKelompok).every(pil => pil.some(p => tag.includes(p)));
            const cocok = cocokKata && cocokChip;
            k.hidden = !cocok;
            if (cocok) tampil++;
        });

        const menyaring = kata !== '' || aktif.size > 0;
        const hitung = document.getElementById('filter-hitung');
        if (hitung) hitung.textContent = menyaring ? tampil + ' dari ' + kartu.length : '';

        const kosong = document.getElementById('filter-kosong');
        if (kosong) kosong.hidden = !(menyaring && tampil === 0);

        const bersih = document.getElementById('filter-bersih');
        if (bersih) bersih.hidden = !menyaring;
    }

    document.addEventListener('input', function (e) {
        if (e.target && e.target.id === 'filter-tamu') jalankanFilter();
    });
    document.addEventListener('click', function (e) {
        const chip = e.target.closest('#day-panel .filter-chip .chip');
        if (chip) {
            const k = chip.dataset.chip;
            window.filterKamarAktif.has(k) ? window.filterKamarAktif.delete(k) : window.filterKamarAktif.add(k);
            jalankanFilter();
            return;
        }
        if (e.target.closest('#filter-bersih')) {
            window.filterKamarAktif.clear();
            const input = document.getElementById('filter-tamu');
            if (input) { input.value = ''; input.focus(); }
            jalankanFilter();
        }
    });
</script>
@endpush
@endonce
