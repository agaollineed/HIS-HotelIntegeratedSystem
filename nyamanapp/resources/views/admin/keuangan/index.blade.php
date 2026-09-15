@extends('layouts.panel')

@section('title', 'Keuangan Bulanan')

@push('head')
<style>
    .head-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .head-actions { display: flex; gap: 10px; flex-wrap: wrap; }

    /* ---------- Ringkasan ---------- */
    .ring { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; margin-bottom: 22px; }
    .ring .kotak { padding: 16px 18px; }
    .ring .lbl { font-size: .7rem; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); font-weight: 700; margin-bottom: 6px; }
    .ring .n { font-size: 1.32rem; font-weight: 800; font-variant-numeric: tabular-nums; letter-spacing: -.02em; }
    .ring .n.masuk { color: var(--ok); }
    .ring .n.keluar { color: var(--danger); }
    .ring .n.laba  { color: var(--accent); }
    .ring .n.netral { color: var(--text); }

    /* ---------- Grafik ---------- */
    .chart-card { padding: 22px; margin-bottom: 24px; }
    .chart-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 6px; }
    .chart-top h2 { margin: 0 0 4px; font-size: 1.12rem; font-weight: 800; letter-spacing: -.02em; }
    .chart-top p { margin: 0; color: var(--muted); font-size: .85rem; }

    .legenda { display: flex; gap: 14px; flex-wrap: wrap; margin: 12px 0 18px; font-size: .78rem; color: var(--muted); }
    .legenda span { display: inline-flex; align-items: center; gap: 7px; }
    .legenda i { width: 12px; height: 12px; border-radius: 3px; display: inline-block; }
    .legenda i.masuk    { background: var(--grad-bar); }
    .legenda i.tercatat { background: var(--accent-2-soft); border: 1px solid var(--border-violet); }
    .legenda i.keluar   { background: var(--danger-soft); border: 1px solid var(--danger-line); }

    .plot { position: relative; overflow-x: auto; padding-bottom: 4px; }
    .bars { display: flex; align-items: flex-end; gap: 18px; min-height: 220px; padding: 8px 4px 0; }
    .kol { display: flex; flex-direction: column; align-items: center; gap: 8px; flex: 0 0 auto; width: 68px; }
    .duo { display: flex; align-items: flex-end; gap: 4px; height: 190px; }
    .bar { width: 20px; border-radius: 5px 5px 0 0; position: relative; transition: filter .15s; }
    .bar.masuk    { background: var(--grad-bar); }
    .bar.tercatat { background: var(--accent-2-soft); border: 1px solid var(--border-violet); }
    .bar.keluar   { background: var(--danger-soft); border: 1px solid var(--danger-line); }
    .kol:hover .bar { filter: brightness(1.18); }
    .kol .bln { font-size: .7rem; color: var(--muted); font-weight: 700; white-space: nowrap; }
    .kol .val { font-size: .68rem; font-variant-numeric: tabular-nums; color: var(--accent); font-weight: 700; }

    .chart-kosong { text-align: center; color: var(--muted); padding: 44px 20px; font-size: .92rem; }

    /* ---------- Formulir ---------- */
    .form-card { padding: 22px; margin-bottom: 24px; }
    .form-card h2 { margin: 0 0 4px; font-size: 1.02rem; font-weight: 800; }
    .form-card .ket { margin: 0 0 18px; font-size: .85rem; color: var(--muted); line-height: 1.6; }
    .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; }
    .fld { margin-bottom: 16px; }
    .fld label { display: block; font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 7px; font-weight: 700; }
    .fld input[type=month], .fld input[type=text], .fld textarea {
        width: 100%; font-family: inherit; font-size: 1rem; color: var(--text); color-scheme: dark;
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; padding: 12px 14px;
    }
    .fld textarea { min-height: 78px; resize: vertical; font-size: .92rem; }
    .fld input:focus, .fld textarea:focus { outline: none; border-color: var(--accent); }
    .fld .hint { margin-top: 6px; font-size: .77rem; color: var(--muted); }
    .rp-input { display: flex; align-items: stretch; }
    .rp-input .rp {
        display: flex; align-items: center; font-weight: 700; font-size: .92rem; color: var(--muted);
        border: 1px solid var(--border-2); border-right: none; border-radius: 10px 0 0 10px;
        padding: 0 13px; background: var(--surface-2);
    }
    .rp-input input { border-radius: 0 10px 10px 0; font-weight: 700; font-variant-numeric: tabular-nums; }
    .form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 6px; }

    /* ---------- Bulan yang belum dicatat ---------- */
    .belum-bar {
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        padding: 13px 16px; margin-bottom: 20px; font-size: .86rem;
        border: 1px solid var(--warn-line); background: var(--warn-soft); border-radius: 12px; color: var(--warn);
    }
    .belum-bar .ic { flex: 0 0 auto; }
    .belum-bar button {
        font-family: inherit; font-size: .76rem; font-weight: 700; cursor: pointer;
        background: var(--warn-soft); color: var(--warn); border: 1px solid var(--warn-line);
        border-radius: 999px; padding: 5px 12px;
    }
    .belum-bar button:hover { background: var(--hover); }

    /* ---------- Tabel ---------- */
    .tabel-card { padding: 6px; }
    .tscroll { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; min-width: 780px; }
    thead th {
        text-align: left; font-size: .7rem; letter-spacing: .07em; text-transform: uppercase;
        color: var(--muted); font-weight: 700; padding: 14px 14px; border-bottom: 1px solid var(--border-2);
    }
    thead th.num, tbody td.num { text-align: right; }
    tbody td { padding: 14px; border-bottom: 1px solid var(--border-2); font-size: .92rem; vertical-align: top; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover td { background: var(--hover); }
    .num { font-variant-numeric: tabular-nums; }
    .bln-sel { font-weight: 700; }
    .bln-sel .oleh { display: block; font-size: .74rem; color: var(--muted); font-weight: 500; margin-top: 3px; }
    .catatan-sel { color: var(--muted); font-size: .84rem; max-width: 280px; }

    .sel-chip {
        display: inline-block; border-radius: 999px; padding: 3px 10px;
        font-size: .74rem; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap;
    }
    .sel-chip.pas    { background: var(--ok-soft);     color: var(--ok);     border: 1px solid var(--ok-line); }
    .sel-chip.kurang { background: var(--danger-soft); color: var(--danger); border: 1px solid var(--danger-line); }
    .sel-chip.lebih  { background: var(--info-soft);   color: var(--info);   border: 1px solid var(--info-line); }

    .row-actions { display: flex; gap: 8px; justify-content: flex-end; }
    .kosong { text-align: center; color: var(--muted); padding: 42px 20px; }

    @media (max-width: 640px) {
        .form-actions { justify-content: stretch; }
        .form-actions .btn { flex: 1; }
    }
</style>
@endpush

@section('content')
    <div class="page-head head-row">
        <div>
            <h1>Keuangan Bulanan</h1>
            <p>Setoran yang <strong>benar-benar diterima</strong>, dicatat manual — lalu disandingkan dengan pendapatan menurut catatan kamar.</p>
        </div>
        <div class="head-actions">
            <a href="{{ route('admin.keuangan.export') }}" class="btn btn-ghost">
                @include('partials.icon', ['name' => 'download', 'class' => 'ic-sm']) Unduh Excel
            </a>
        </div>
    </div>

    {{-- Ringkasan seluruh bulan yang tercatat --}}
    <div class="ring">
        <div class="card kotak">
            <div class="lbl">Total Pemasukan</div>
            <div class="n masuk">{{ \App\Models\Keuangan::rupiah($ringkas['pemasukan']) }}</div>
        </div>
        <div class="card kotak">
            <div class="lbl">Total Pengeluaran</div>
            <div class="n keluar">{{ \App\Models\Keuangan::rupiah($ringkas['pengeluaran']) }}</div>
        </div>
        <div class="card kotak">
            <div class="lbl">Laba Bersih</div>
            <div class="n laba">{{ \App\Models\Keuangan::rupiah($ringkas['laba']) }}</div>
        </div>
        <div class="card kotak">
            <div class="lbl">Bulan Tercatat</div>
            <div class="n netral">{{ $ringkas['bulan'] }}</div>
        </div>
    </div>

    {{-- Bulan yang terlewat. Yang paling gampang hilang dari catatan manual
         adalah bulan yang tidak pernah diisi sama sekali — ia tidak muncul
         di mana pun, jadi tidak ada yang menyadarinya. --}}
    @if ($belum->isNotEmpty())
        <div class="belum-bar">
            @include('partials.icon', ['name' => 'alert', 'class' => 'ic-sm'])
            <span>Belum dicatat:</span>
            @foreach ($belum as $b)
                <button type="button" data-isi-bulan="{{ $b->format('Y-m') }}">{{ $b->translatedFormat('F Y') }}</button>
            @endforeach
        </div>
    @endif

    {{-- ---------- Grafik ---------- --}}
    <div class="card chart-card">
        <div class="chart-top">
            <div>
                <h2>Pemasukan Nyata vs Pendapatan Tercatat</h2>
                <p>Batang ungu = uang yang masuk. Batang pudar = yang seharusnya masuk menurut booking.</p>
            </div>
        </div>

        <div class="legenda">
            <span><i class="masuk"></i> Pemasukan nyata</span>
            <span><i class="tercatat"></i> Pendapatan tercatat</span>
            <span><i class="keluar"></i> Pengeluaran</span>
        </div>

        <div class="plot">
            <div class="bars" id="bars">
                <div class="chart-kosong" id="chart-kosong">Memuat grafik…</div>
            </div>
        </div>
    </div>

    {{-- ---------- Formulir catat / koreksi ---------- --}}
    <div class="card form-card">
        <h2>Catat Bulan</h2>
        <p class="ket">
            Isi dari mutasi rekening dan buku kas — <strong>bukan</strong> dari laporan aplikasi.
            Angkanya sengaja tidak diisi otomatis: yang dicari justru selisihnya terhadap catatan kamar,
            dan selisih itu tidak akan pernah muncul kalau keduanya berasal dari sumber yang sama.
            Mengisi bulan yang sudah ada akan <strong>memperbarui</strong> barisnya, bukan menambah baris kedua.
        </p>

        <form method="POST" action="{{ route('admin.keuangan.store') }}">
            @csrf
            <div class="grid-3">
                <div class="fld">
                    <label for="periode">Bulan</label>
                    <input id="periode" type="month" name="periode" required
                           max="{{ $bulanIni }}" value="{{ old('periode', $bulanIni) }}">
                </div>
                <div class="fld">
                    <label for="pemasukan_tampil">Pemasukan Nyata</label>
                    <div class="rp-input">
                        <span class="rp">Rp</span>
                        <input id="pemasukan_tampil" type="text" inputmode="numeric" autocomplete="off"
                               value="{{ old('pemasukan') ? number_format((int) old('pemasukan'), 0, ',', '.') : '' }}"
                               placeholder="0" required>
                    </div>
                    <input type="hidden" name="pemasukan" id="pemasukan" value="{{ old('pemasukan', 0) }}">
                </div>
                <div class="fld">
                    <label for="pengeluaran_tampil">Pengeluaran</label>
                    <div class="rp-input">
                        <span class="rp">Rp</span>
                        <input id="pengeluaran_tampil" type="text" inputmode="numeric" autocomplete="off"
                               value="{{ old('pengeluaran') ? number_format((int) old('pengeluaran'), 0, ',', '.') : '' }}"
                               placeholder="0">
                    </div>
                    <input type="hidden" name="pengeluaran" id="pengeluaran" value="{{ old('pengeluaran', 0) }}">
                </div>
            </div>

            <div class="fld">
                <label for="catatan">Catatan</label>
                <textarea id="catatan" name="catatan" placeholder="Mis. Ada rombongan 8 kamar dari agen, setoran masuk 3 Mei.">{{ old('catatan') }}</textarea>
                <div class="hint">Di sinilah alasan bulan yang menyimpang dituliskan — itu yang paling sering dicari setahun kemudian.</div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-accent">
                    @include('partials.icon', ['name' => 'check', 'class' => 'ic-sm']) Simpan
                </button>
            </div>
        </form>
    </div>

    {{-- ---------- Tabel ---------- --}}
    <div class="card tabel-card">
        @if ($baris->isEmpty())
            <div class="kosong">Belum ada bulan yang dicatat. Isi formulir di atas untuk memulai.</div>
        @else
            <div class="tscroll">
                <table>
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th class="num">Pemasukan</th>
                            <th class="num">Pengeluaran</th>
                            <th class="num">Laba</th>
                            <th class="num">Tercatat</th>
                            <th class="num">Selisih</th>
                            <th>Catatan</th>
                            <th class="num">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($baris as $k)
                            @php
                                $selisih = $k->selisih();
                                $kelas   = $selisih === 0 ? 'pas' : ($selisih < 0 ? 'kurang' : 'lebih');
                            @endphp
                            <tr>
                                <td class="bln-sel">
                                    {{ $k->labelPeriode() }}
                                    <span class="oleh">{{ $k->pencatat?->name ?? '—' }} · {{ $k->updated_at?->translatedFormat('j M Y') }}</span>
                                </td>
                                <td class="num" style="color: var(--ok)">{{ \App\Models\Keuangan::rupiah($k->pemasukan) }}</td>
                                <td class="num" style="color: var(--danger)">{{ \App\Models\Keuangan::rupiah($k->pengeluaran) }}</td>
                                <td class="num" style="font-weight:700">{{ \App\Models\Keuangan::rupiah($k->laba()) }}</td>
                                <td class="num" style="color: var(--muted)">{{ \App\Models\Keuangan::rupiah($k->pendapatanTercatat()) }}</td>
                                <td class="num">
                                    <span class="sel-chip {{ $kelas }}">{{ \App\Models\Keuangan::rupiah($selisih) }}</span>
                                </td>
                                <td class="catatan-sel">{{ $k->catatan ?: '—' }}</td>
                                <td class="num">
                                    <div class="row-actions">
                                        <button type="button" class="btn btn-ghost btn-sm"
                                                data-ubah="{{ $k->kunciPeriode() }}"
                                                data-masuk="{{ $k->pemasukan }}"
                                                data-keluar="{{ $k->pengeluaran }}"
                                                data-catatan="{{ $k->catatan }}">Ubah</button>
                                        <form method="POST" action="{{ route('admin.keuangan.destroy', $k) }}"
                                              onsubmit="return confirm('Hapus catatan keuangan {{ $k->labelPeriode() }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
(function () {
    // ---------- Isian rupiah: yang dikirim angka murni, yang tampil berformat ----------
    function pasangRupiah(idTampil, idNilai) {
        const tampil = document.getElementById(idTampil);
        const nilai  = document.getElementById(idNilai);
        if (!tampil || !nilai) return;

        function sinkron() {
            const bersih = String(tampil.value).replace(/\D/g, '');
            const angka  = bersih === '' ? 0 : parseInt(bersih, 10);
            nilai.value  = angka;
            tampil.value = angka > 0 ? angka.toLocaleString('id-ID') : '';
        }

        tampil.addEventListener('input', sinkron);
        sinkron();
    }

    pasangRupiah('pemasukan_tampil', 'pemasukan');
    pasangRupiah('pengeluaran_tampil', 'pengeluaran');

    const periode = document.getElementById('periode');
    const catatan = document.getElementById('catatan');

    function isiForm(bulan, masuk, keluar, teks) {
        if (periode) periode.value = bulan;

        const pm = document.getElementById('pemasukan_tampil');
        const pk = document.getElementById('pengeluaran_tampil');
        if (pm) { pm.value = masuk > 0 ? Number(masuk).toLocaleString('id-ID') : ''; pm.dispatchEvent(new Event('input')); }
        if (pk) { pk.value = keluar > 0 ? Number(keluar).toLocaleString('id-ID') : ''; pk.dispatchEvent(new Event('input')); }
        if (catatan) catatan.value = teks || '';

        // Formulirnya ada di atas tabel; tanpa ini tombol "Ubah" terasa
        // seperti tidak melakukan apa-apa di layar ponsel.
        if (periode) periode.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    document.querySelectorAll('[data-ubah]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            isiForm(btn.dataset.ubah, parseInt(btn.dataset.masuk || '0', 10),
                parseInt(btn.dataset.keluar || '0', 10), btn.dataset.catatan);
        });
    });

    document.querySelectorAll('[data-isi-bulan]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            isiForm(btn.dataset.isiBulan, 0, 0, '');
        });
    });

    // ---------- Grafik ----------
    const bars   = document.getElementById('bars');
    const kosong = document.getElementById('chart-kosong');

    function rupiahSingkat(n) {
        const a = Math.abs(n);
        if (a >= 1000000000) return (n / 1000000000).toFixed(1).replace('.0', '') + ' M';
        if (a >= 1000000)    return (n / 1000000).toFixed(1).replace('.0', '') + ' jt';
        if (a >= 1000)       return Math.round(n / 1000) + ' rb';
        return String(n);
    }

    fetch(@json(route('admin.keuangan.data')), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function (data) {
            const bulan = data.bulan || [];

            if (!bulan.length) {
                if (kosong) kosong.textContent = 'Belum ada data untuk digambar. Catat minimal satu bulan.';
                return;
            }

            // Skala dihitung dari nilai TERTINGGI di antara ketiga deret,
            // bukan dari pemasukan saja — kalau tidak, batang "tercatat" yang
            // lebih besar akan terpotong di luar area gambar.
            let maks = 0;
            bulan.forEach(function (b) {
                maks = Math.max(maks, b.pemasukan, b.tercatat, b.pengeluaran);
            });
            if (maks <= 0) maks = 1;

            const TINGGI = 190;
            bars.innerHTML = '';

            bulan.forEach(function (b) {
                const kol = document.createElement('div');
                kol.className = 'kol';

                const duo = document.createElement('div');
                duo.className = 'duo';

                [
                    ['masuk', b.pemasukan, 'Pemasukan nyata'],
                    ['tercatat', b.tercatat, 'Pendapatan tercatat'],
                    ['keluar', b.pengeluaran, 'Pengeluaran'],
                ].forEach(function (item) {
                    const bar = document.createElement('div');
                    bar.className = 'bar ' + item[0];
                    // Batang bernilai nol tetap diberi 2px supaya kolomnya
                    // tidak terlihat hilang — bulan tanpa pengeluaran berbeda
                    // artinya dengan bulan yang tidak dicatat sama sekali.
                    bar.style.height = Math.max(2, Math.round(item[1] / maks * TINGGI)) + 'px';
                    bar.title = item[2] + ': Rp ' + Number(item[1]).toLocaleString('id-ID');
                    duo.appendChild(bar);
                });

                const val = document.createElement('div');
                val.className = 'val';
                val.textContent = rupiahSingkat(b.pemasukan);

                const bln = document.createElement('div');
                bln.className = 'bln';
                bln.textContent = b.label;

                kol.appendChild(duo);
                kol.appendChild(val);
                kol.appendChild(bln);
                bars.appendChild(kol);
            });
        })
        .catch(function () {
            if (kosong) kosong.textContent = 'Grafik gagal dimuat. Muat ulang halaman untuk mencoba lagi.';
        });
})();
</script>
@endpush
