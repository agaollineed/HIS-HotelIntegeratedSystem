@extends('layouts.panel')

@section('title', 'Keuntungan & Bonus')

@push('head')
<style>
    /* ---------- Grafik ---------- */
    .chart-card { padding: 20px 22px; margin-bottom: 22px; }
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

</style>
@endpush

@section('content')
    @php
        $rp = fn (int $n) => \App\Models\Keuangan::rupiah($n);
        $persen = \App\Models\Keuangan::PERSEN_BONUS;
        $bolehHapus = auth()->user()->bolehHapusKeuangan();
        $isiBaru = ['periode' => $bulanIni, 'pemasukan' => null, 'pengeluaran' => null, 'catatan' => '', '_judul' => 'Catat Omset & Pengeluaran'];
    @endphp

    @include('admin.keuangan._kepala', [
        'sub'     => 'Keuntungan & bonus karyawan dihitung otomatis dari omset − pengeluaran.',
        'catat'   => ['label' => 'Catat Bulan', 'dialog' => 'dlg-catat', 'isi' => $isiBaru],
        'lainnya' => [
            ['label' => 'Export CSV', 'href' => route('admin.keuangan.export.csv'), 'ket' => 'Semua bulan, bisa diimpor ulang'],
            ['label' => 'Import CSV', 'dialog' => 'dlg-impor', 'ket' => 'Masukkan data lama sekaligus'],
            ['label' => 'Unduh Excel', 'href' => route('admin.keuangan.export'), 'ket' => 'Termasuk pendapatan tercatat & selisih'],
        ],
    ])

    @if ($sorot)
        <div class="hero">
            <div class="hero-utama">
                <div class="lbl">Keuntungan {{ $sorot->labelPeriode() }}</div>
                <div class="angka" style="color: {{ $sorot->keuntungan() < 0 ? 'var(--danger)' : 'var(--text)' }}">{{ $rp($sorot->keuntungan()) }}</div>
                <div class="ket">
                    <span class="chip naik">Omset {{ $rp($sorot->pemasukan) }}</span>
                    <span class="chip turun">Pengeluaran {{ $rp($sorot->pengeluaran) }}</span>
                </div>
            </div>
            <div class="card hero-stat">
                <div class="lbl">Bonus karyawan</div>
                <div class="n" style="color: var(--warn)">{{ $rp($sorot->bonusKaryawan()) }}</div>
                <div class="sub">{{ $persen }}% dari keuntungan{{ $sorot->keuntungan() <= 0 ? ' — bulan rugi' : '' }}</div>
            </div>
            <div class="card hero-stat">
                <div class="lbl">Total bonus</div>
                <div class="n">{{ $rp($ringkas['bonus']) }}</div>
                <div class="sub">{{ $ringkas['bulan'] }} bulan tercatat</div>
            </div>
        </div>
    @else
        <div class="ajak">
            <span>Belum ada bulan yang dicatat.</span>
            <button type="button" class="btn btn-accent btn-sm" data-buka="dlg-catat" data-isi="{{ json_encode($isiBaru) }}">Catat bulan pertama</button>
        </div>
    @endif

    <div class="card keu-kartu">
        <div class="keu-kartu-kepala">
            <h2>Per Bulan</h2>
            <span class="ket">klik baris untuk mengubah</span>
        </div>
        @if ($baris->isEmpty())
            <div class="keu-kosong">Belum ada data.</div>
        @else
            <div class="keu-tabel-wrap">
                <table class="keu-tabel">
                    <thead>
                        <tr>
                            <th>Bulan</th><th class="num">Omset</th><th class="num">Pengeluaran</th>
                            <th class="num">Keuntungan</th><th class="num">Bonus {{ $persen }}%</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($baris as $k)
                            @php
                                $isi = ['periode' => $k->kunciPeriode(), 'pemasukan' => $k->pemasukan, 'pengeluaran' => $k->pengeluaran,
                                    'catatan' => (string) $k->catatan, '_kunci' => true, '_judul' => 'Ubah ' . $k->labelPeriode(),
                                    '_hapus' => $bolehHapus ? route('admin.keuangan.destroy', $k) : null,
                                    '_hapusTanya' => 'Hapus catatan ' . $k->labelPeriode() . '?'];
                            @endphp
                            <tr class="bisa-klik" tabindex="0" data-buka="dlg-catat" data-isi="{{ json_encode($isi) }}">
                                <td><b>{{ $k->labelPeriode() }}</b>@if ($k->catatan)<span class="redup">{{ \Illuminate\Support\Str::limit($k->catatan, 60) }}</span>@endif</td>
                                <td class="num">{{ $rp($k->pemasukan) }}</td>
                                <td class="num">{{ $rp($k->pengeluaran) }}</td>
                                <td class="num" style="font-weight:800; color: {{ $k->keuntungan() < 0 ? 'var(--danger)' : 'var(--text)' }}">{{ $rp($k->keuntungan()) }}</td>
                                <td class="num" style="font-weight:800; color: var(--warn)">{{ $rp($k->bonusKaryawan()) }}</td>
                                <td class="pena">@include('partials.icon', ['name' => 'pensil', 'class' => 'ic-xs'])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Grafik pembanding: omset yang dicatat vs pendapatan menurut booking --}}
    <div class="card chart-card">
        <div class="chart-top">
            <div>
                <h2>Omset vs Pendapatan Tercatat</h2>
                <p>Batang ungu = omset yang dicatat; batang pudar = yang seharusnya masuk menurut booking.</p>
            </div>
        </div>
        <div class="legenda">
            <span><i class="masuk"></i> Omset</span>
            <span><i class="tercatat"></i> Pendapatan tercatat</span>
            <span><i class="keluar"></i> Pengeluaran</span>
        </div>
        <div class="plot">
            <div class="bars" id="bars">
                <div class="chart-kosong" id="chart-kosong">Memuat grafik…</div>
            </div>
        </div>
    </div>

    {{-- Jendela catat / ubah bulan --}}
    <dialog class="keu-dlg" id="dlg-catat" data-judul-baru="Catat Omset & Pengeluaran">
        <form method="POST" action="{{ route('admin.keuangan.store') }}">
            @csrf
            <input type="hidden" name="_dialog" value="dlg-catat">
            <div class="dlg-kepala">
                <h2 data-judul>Catat Omset &amp; Pengeluaran</h2>
                <button type="button" class="dlg-x" data-tutup aria-label="Tutup">@include('partials.icon', ['name' => 'x', 'class' => 'ic-sm'])</button>
            </div>
            <div class="dlg-isi">
                <div class="fld">
                    <label for="periode">Bulan</label>
                    <input id="periode" type="month" name="periode" required max="{{ $bulanIni }}" data-kunci value="{{ old('periode', $bulanIni) }}">
                </div>
                <div class="dua">
                    <div class="fld">
                        <label for="pemasukan_tampil">Omset</label>
                        <div class="rp-input"><span class="rp">Rp</span>
                            <input id="pemasukan_tampil" type="text" data-rupiah="pemasukan" inputmode="numeric" autocomplete="off" placeholder="0" required>
                        </div>
                        <input type="hidden" name="pemasukan" id="pemasukan" value="{{ old('pemasukan') }}">
                    </div>
                    <div class="fld">
                        <label for="pengeluaran_tampil">Pengeluaran</label>
                        <div class="rp-input"><span class="rp">Rp</span>
                            <input id="pengeluaran_tampil" type="text" data-rupiah="pengeluaran" inputmode="numeric" autocomplete="off" placeholder="0">
                        </div>
                        <input type="hidden" name="pengeluaran" id="pengeluaran" value="{{ old('pengeluaran') }}">
                    </div>
                </div>
                {{-- Dihitung di peramban saat mengetik; server menghitung ulang saat simpan. --}}
                <div class="hasil" aria-live="polite">
                    <div><div class="lbl">Keuntungan</div><div class="n" id="hasil-untung">Rp 0</div></div>
                    <div><div class="lbl">Bonus karyawan ({{ $persen }}%)</div><div class="n" id="hasil-bonus" style="color: var(--warn)">Rp 0</div></div>
                </div>
                <div class="fld">
                    <label for="catatan">Catatan (opsional)</label>
                    <textarea id="catatan" name="catatan" maxlength="1000" placeholder="Mis. ada rombongan 8 kamar dari agen.">{{ old('catatan') }}</textarea>
                </div>
            </div>
            <div class="dlg-kaki">
                @if ($bolehHapus)
                    <button type="submit" form="hapus-bulan" class="btn btn-danger btn-sm" data-hapus hidden>Hapus</button>
                @endif
                <div class="kanan">
                    <button type="button" class="btn btn-ghost" data-tutup>Batal</button>
                    <button type="submit" class="btn btn-accent">Simpan</button>
                </div>
            </div>
        </form>
        @if ($bolehHapus)
            <form method="POST" id="hapus-bulan" class="dlg-hapus">@csrf @method('DELETE')</form>
        @endif
    </dialog>

    @include('admin.keuangan._impor', [
        'impor'  => route('admin.keuangan.import'),
        'kolom'  => 'bulan, omset, pengeluaran, catatan (opsional)',
        'contoh' => '2026-04,40000000,28000000',
    ])
@endsection

@push('scripts')
<script>
(function () {
    // ---------- Keuntungan & bonus otomatis ----------
    // Aturannya sama dengan Keuangan::bonusDari(): bonus hanya dari keuntungan
    // positif; bulan rugi tidak menghasilkan bonus negatif.
    const PERSEN = @json(\App\Models\Keuangan::PERSEN_BONUS);
    function hitungUntung() {
        const masuk  = parseInt(document.getElementById('pemasukan').value || '0', 10) || 0;
        const keluar = parseInt(document.getElementById('pengeluaran').value || '0', 10) || 0;
        const untung = masuk - keluar;
        const bonus  = untung > 0 ? Math.round(untung * PERSEN / 100) : 0;
        const rp = n => (n < 0 ? '-Rp ' : 'Rp ') + Math.abs(n).toLocaleString('id-ID');
        const el = document.getElementById('hasil-untung');
        el.textContent = rp(untung);
        el.style.color = untung < 0 ? 'var(--danger)' : 'var(--text)';
        document.getElementById('hasil-bonus').textContent = rp(bonus);
    }
    ['pemasukan_tampil', 'pengeluaran_tampil'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', hitungUntung);
    });
    document.getElementById('dlg-catat').addEventListener('keu:isi', hitungUntung);
    hitungUntung();

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
                    ['masuk', b.pemasukan, 'Omset'],
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
