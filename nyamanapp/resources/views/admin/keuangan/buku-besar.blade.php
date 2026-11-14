@extends('layouts.panel')

@section('title', 'Buku Besar')

@php
    $rp  = fn (int $n) => \App\Support\CsvKeuangan::rupiah($n);
    // Angka di tabel tanpa "Rp" supaya muat, sama seperti di Excel.
    $ang = fn (int $n) => $n === 0 ? '' : ($n < 0 ? '-' : '') . number_format(abs($n), 0, ',', '.');
    $tot = fn (int $n) => ($n < 0 ? '-' : '') . number_format(abs($n), 0, ',', '.');
    $T = $laporan['total'];
@endphp

@push('head')
<style>
    .bb-bulan { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
    .bb-bulan .nav { display: flex; align-items: center; gap: 8px; }
    .bb-bulan .nav h2 { margin: 0; font-size: 1.1rem; font-weight: 800; min-width: 150px; text-align: center; }
    .bb-bulan .nav a, .bb-bulan .nav span.mati {
        width: 34px; height: 34px; border-radius: 999px; display: inline-grid; place-items: center;
        border: 1px solid var(--border-2); background: var(--surface); color: var(--text);
    }
    .bb-bulan .nav span.mati { opacity: .3; }
    .bb-ringkas { display: flex; gap: 8px; flex-wrap: wrap; }
    .bb-sepi-tombol { display: inline-flex; align-items: center; gap: 8px; font-size: .8rem; color: var(--muted); cursor: pointer; margin-top: 10px; }
    .bb-sepi-tombol input { accent-color: var(--accent); }
    .bb.tanpa-sepi tr.sepi { display: none; }

    /* Tabel ala Excel */
    .bb-card { padding: 18px 20px; margin-bottom: 22px; }
    .bb-scroll { margin: 0 -8px; }
    .bb-scroll { overflow-x: auto; }
    .bb { width: 100%; border-collapse: collapse; min-width: 1080px; font-variant-numeric: tabular-nums; font-size: .84rem; }
    .bb th, .bb td { padding: 8px 10px; border-bottom: 1px solid var(--border-2); white-space: nowrap; }
    .bb thead th { font-size: .68rem; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); font-weight: 800; text-align: right; }
    .bb thead tr:first-child th { text-align: center; border-bottom: none; padding-bottom: 2px; }
    .bb thead th.kiri, .bb td.kiri { text-align: left; }
    .bb td { text-align: right; }
    .bb .grup-masuk { color: var(--ok); }
    .bb .grup-keluar { color: var(--danger); }
    .bb .grup-saldo { color: var(--accent); }
    .bb .pisah { border-left: 1px solid var(--border); }
    .bb td.tot { font-weight: 800; }
    .bb td.masuk { color: var(--ok); }
    .bb td.keluar { color: var(--danger); }
    .bb td.nol { color: var(--muted); font-weight: 500; }
    /* Kolom tanggal tetap terlihat saat tabel digeser ke kanan. */
    .bb th:first-child, .bb td:first-child { position: sticky; left: 0; z-index: 1; background: var(--surface); box-shadow: 1px 0 0 var(--border), 8px 0 12px -10px rgba(0,0,0,.8); }
    .bb tr.awal td:first-child, .bb tfoot td:first-child { background: var(--surface-2); }
    .bb td.saldo { font-weight: 700; }
    .bb td.saldo.total { color: var(--accent); font-weight: 800; }
    .bb td.minus { color: var(--danger); }
    .bb tbody tr.bisa-klik { cursor: pointer; }
    .bb tbody tr:hover td { background: var(--hover); }
    .bb tr.sepi td:not(.kiri):not(.saldo) { color: var(--faint, var(--muted)); }
    .bb tr.awal td, .bb tfoot td { background: rgba(255,255,255,.025); font-weight: 800; }
    .bb tr.hari-ini td.kiri { color: var(--accent); }
    .bb .ket { max-width: 260px; overflow: hidden; text-overflow: ellipsis; color: var(--muted); font-size: .8rem; text-align: left; }
    .bb-kosong { text-align: center; color: var(--muted); padding: 36px 20px; }
</style>
@endpush

@section('content')
    @php
        $bolehHapus = auth()->user()->bolehHapusKeuangan();
        $isiBaru = ['tanggal' => $hariIni, 'masuk_bni' => null, 'masuk_bri' => null, 'keluar_bni' => null, 'keluar_bri' => null, 'keterangan' => '', '_judul' => 'Catat Hari'];
        $masukBulan = $T['masuk_bni'] + $T['masuk_bri'];
        $keluarBulan = $T['keluar_bni'] + $T['keluar_bri'];
    @endphp

    @include('admin.keuangan._kepala', [
        'sub'     => 'Arus kas BNI & BRI per hari; saldo akhir dihitung otomatis.',
        'catat'   => ['label' => 'Catat Hari', 'dialog' => 'dlg-catat', 'isi' => $isiBaru],
        'lainnya' => [
            ['label' => 'Export CSV', 'href' => route('admin.keuangan.buku-besar.export'), 'ket' => 'Susunan kolom sama dengan Excel'],
            ['label' => 'Import CSV', 'dialog' => 'dlg-impor', 'ket' => 'Langsung dari lembar Excel "SALDO BANK"'],
            ['label' => 'Ubah saldo awal', 'dialog' => 'dlg-awal', 'ket' => $awal->tanggal->translatedFormat('j M Y') . ': ' . $rp($awal->total())],
        ],
    ])

    <div class="hero">
        <div class="hero-utama">
            <div class="lbl">Total saldo BNI + BRI</div>
            <div class="angka">{{ $rp($terkini['total']) }}</div>
            <div class="ket">{{ $terkini['tanggal'] ? 'catatan terakhir ' . $terkini['tanggal']->translatedFormat('l, j F Y') : 'belum ada catatan' }}</div>
        </div>
        <div class="card hero-stat">
            <div class="lbl">BNI</div>
            <div class="n">{{ $rp($terkini['bni']) }}</div>
        </div>
        <div class="card hero-stat">
            <div class="lbl">BRI</div>
            <div class="n">{{ $rp($terkini['bri']) }}</div>
        </div>
    </div>

    <div class="card bb-card">
        <div class="bb-bulan">
            <div class="nav">
                @if ($sebelum)
                    <a href="{{ route('admin.keuangan.buku-besar', ['bulan' => $sebelum]) }}" title="Bulan sebelumnya">@include('partials.icon', ['name' => 'chevron-left', 'class' => 'ic-sm'])</a>
                @else
                    <span class="mati">@include('partials.icon', ['name' => 'chevron-left', 'class' => 'ic-sm'])</span>
                @endif
                <h2>{{ $bulan->translatedFormat('F Y') }}</h2>
                @if ($sesudah)
                    <a href="{{ route('admin.keuangan.buku-besar', ['bulan' => $sesudah]) }}" title="Bulan berikutnya">@include('partials.icon', ['name' => 'chevron-right', 'class' => 'ic-sm'])</a>
                @else
                    <span class="mati">@include('partials.icon', ['name' => 'chevron-right', 'class' => 'ic-sm'])</span>
                @endif
            </div>
            <div class="bb-ringkas">
                <span class="chip naik">Masuk {{ $rp($masukBulan) }}</span>
                <span class="chip turun">Keluar {{ $rp($keluarBulan) }}</span>
                <span class="chip tetap">Saldo akhir {{ $rp($laporan['akhir']['bni'] + $laporan['akhir']['bri']) }}</span>
            </div>
        </div>

        <div class="bb-scroll">
            <table class="bb" id="bb-tabel">
                <thead>
                    <tr>
                        <th></th>
                        <th colspan="3" class="pisah grup-masuk">Uang Masuk</th>
                        <th colspan="3" class="pisah grup-keluar">Uang Keluar</th>
                        <th colspan="3" class="pisah grup-saldo">Saldo Akhir</th>
                        <th class="pisah"></th>
                    </tr>
                    <tr>
                        <th class="kiri">Tgl</th>
                        <th class="pisah">BNI</th><th>BRI</th><th>Total</th>
                        <th class="pisah">BNI</th><th>BRI</th><th>Total</th>
                        <th class="pisah">BNI</th><th>BRI</th><th>Total</th>
                        <th class="pisah kiri">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="awal">
                        <td class="kiri">Saldo awal</td>
                        <td class="pisah"></td><td></td><td></td>
                        <td class="pisah"></td><td></td><td></td>
                        <td class="pisah saldo">{{ $tot($laporan['awal']['bni']) }}</td>
                        <td class="saldo">{{ $tot($laporan['awal']['bri']) }}</td>
                        <td class="saldo total">{{ $tot($laporan['awal']['bni'] + $laporan['awal']['bri']) }}</td>
                        <td class="pisah"></td>
                    </tr>
                    @forelse ($laporan['baris'] as $r)
                        @php
                            $mt = $r['masuk_bni'] + $r['masuk_bri'];
                            $kt = $r['keluar_bni'] + $r['keluar_bri'];
                            $st = $r['saldo_bni'] + $r['saldo_bri'];
                            $isi = ['tanggal' => $r['tanggal']->toDateString(),
                                'masuk_bni' => $r['masuk_bni'] ?: null, 'masuk_bri' => $r['masuk_bri'] ?: null,
                                'keluar_bni' => $r['keluar_bni'] ?: null, 'keluar_bri' => $r['keluar_bri'] ?: null,
                                'keterangan' => (string) $r['keterangan'], '_kunci' => true,
                                '_judul' => ($r['entri'] ? 'Ubah ' : 'Isi ') . $r['tanggal']->translatedFormat('l, j F Y'),
                                '_hapus' => ($r['entri'] && $bolehHapus) ? route('admin.keuangan.buku-besar.destroy', $r['entri']) : null,
                                '_hapusTanya' => 'Hapus catatan ' . $r['tanggal']->translatedFormat('j F Y') . '?'];
                        @endphp
                        <tr class="bisa-klik {{ $r['entri'] ? '' : 'sepi' }} {{ $r['tanggal']->isToday() ? 'hari-ini' : '' }}" tabindex="0" data-buka="dlg-catat" data-isi="{{ json_encode($isi) }}">
                            <td class="kiri">{{ $r['tanggal']->translatedFormat('D, j M') }}</td>
                            <td class="pisah masuk">{{ $ang($r['masuk_bni']) }}</td>
                            <td class="masuk">{{ $ang($r['masuk_bri']) }}</td>
                            <td class="masuk tot {{ $mt === 0 ? 'nol' : '' }}">{{ $tot($mt) }}</td>
                            <td class="pisah keluar">{{ $ang($r['keluar_bni']) }}</td>
                            <td class="keluar">{{ $ang($r['keluar_bri']) }}</td>
                            <td class="keluar tot {{ $kt === 0 ? 'nol' : '' }}">{{ $tot($kt) }}</td>
                            <td class="pisah saldo {{ $r['saldo_bni'] < 0 ? 'minus' : '' }}">{{ $tot($r['saldo_bni']) }}</td>
                            <td class="saldo {{ $r['saldo_bri'] < 0 ? 'minus' : '' }}">{{ $tot($r['saldo_bri']) }}</td>
                            <td class="saldo total">{{ $tot($st) }}</td>
                            <td class="pisah ket" title="{{ $r['keterangan'] }}">{{ $r['keterangan'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="bb-kosong">Belum ada hari di bulan ini.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td class="kiri">Total {{ $bulan->translatedFormat('M Y') }}</td>
                        <td class="pisah masuk">{{ $tot($T['masuk_bni']) }}</td>
                        <td class="masuk">{{ $tot($T['masuk_bri']) }}</td>
                        <td class="masuk">{{ $tot($masukBulan) }}</td>
                        <td class="pisah keluar">{{ $tot($T['keluar_bni']) }}</td>
                        <td class="keluar">{{ $tot($T['keluar_bri']) }}</td>
                        <td class="keluar">{{ $tot($keluarBulan) }}</td>
                        <td class="pisah saldo">{{ $tot($laporan['akhir']['bni']) }}</td>
                        <td class="saldo">{{ $tot($laporan['akhir']['bri']) }}</td>
                        <td class="saldo total">{{ $tot($laporan['akhir']['bni'] + $laporan['akhir']['bri']) }}</td>
                        <td class="pisah"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <label class="bb-sepi-tombol">
            <input type="checkbox" id="bb-sepi"> Sembunyikan hari tanpa transaksi
        </label>
    </div>

    {{-- Jendela catat / ubah satu hari --}}
    <dialog class="keu-dlg" id="dlg-catat" data-judul-baru="Catat Hari">
        <form method="POST" action="{{ route('admin.keuangan.buku-besar.store') }}">
            @csrf
            <input type="hidden" name="_dialog" value="dlg-catat">
            <div class="dlg-kepala">
                <h2 data-judul>Catat Hari</h2>
                <button type="button" class="dlg-x" data-tutup aria-label="Tutup">@include('partials.icon', ['name' => 'x', 'class' => 'ic-sm'])</button>
            </div>
            <div class="dlg-isi">
                <div class="fld">
                    <label for="tanggal">Tanggal</label>
                    <input type="date" id="tanggal" name="tanggal" required max="{{ $hariIni }}" data-kunci
                           min="{{ $awal->tanggal->copy()->addDay()->toDateString() }}" value="{{ old('tanggal', $hariIni) }}">
                </div>
                @foreach ([['masuk', 'Uang masuk'], ['keluar', 'Uang keluar']] as [$arah, $judulArah])
                    <div class="dua">
                        @foreach (['bni' => 'BNI', 'bri' => 'BRI'] as $bank => $labelBank)
                            @php $nama = $arah . '_' . $bank; @endphp
                            <div class="fld">
                                <label for="{{ $nama }}_tampil">{{ $judulArah }} <span class="bank">{{ $labelBank }}</span></label>
                                <div class="rp-input"><span class="rp">Rp</span>
                                    <input type="text" id="{{ $nama }}_tampil" data-rupiah="{{ $nama }}" inputmode="numeric" autocomplete="off" placeholder="0">
                                </div>
                                <input type="hidden" name="{{ $nama }}" id="{{ $nama }}" value="{{ old($nama) }}">
                            </div>
                        @endforeach
                    </div>
                @endforeach
                <div class="hasil" aria-live="polite">
                    <div><div class="lbl">Total masuk</div><div class="n naik" id="bb-masuk">Rp 0</div></div>
                    <div><div class="lbl">Total keluar</div><div class="n turun" id="bb-keluar">Rp 0</div></div>
                </div>
                <div class="fld">
                    <label for="keterangan">Keterangan (opsional)</label>
                    <input type="text" id="keterangan" name="keterangan" maxlength="500" value="{{ old('keterangan') }}" placeholder="Mis. Uang Wijaya, salah transfer, dikembalikan">
                </div>
                <div class="dlg-catatan">Mengisi hari yang sudah ada mengganti angka hari itu. Kosongkan semua untuk menghapus hari itu.</div>
            </div>
            <div class="dlg-kaki">
                @if ($bolehHapus)
                    <button type="submit" form="hapus-hari" class="btn btn-danger btn-sm" data-hapus hidden>Hapus</button>
                @endif
                <div class="kanan">
                    <button type="button" class="btn btn-ghost" data-tutup>Batal</button>
                    <button type="submit" class="btn btn-accent">Simpan</button>
                </div>
            </div>
        </form>
        @if ($bolehHapus)
            <form method="POST" id="hapus-hari" class="dlg-hapus">@csrf @method('DELETE')</form>
        @endif
    </dialog>

    {{-- Jendela saldo awal --}}
    <dialog class="keu-dlg" id="dlg-awal" data-judul-baru="Saldo Awal">
        <form method="POST" action="{{ route('admin.keuangan.buku-besar.awal') }}">
            @csrf
            <input type="hidden" name="_dialog" value="dlg-awal">
            <div class="dlg-kepala">
                <h2 data-judul>Saldo Awal</h2>
                <button type="button" class="dlg-x" data-tutup aria-label="Tutup">@include('partials.icon', ['name' => 'x', 'class' => 'ic-sm'])</button>
            </div>
            <div class="dlg-isi">
                <div class="fld">
                    <label for="awal_tanggal">Tanggal saldo awal</label>
                    <input type="date" id="awal_tanggal" name="tanggal" required value="{{ $awal->tanggal->toDateString() }}">
                </div>
                <div class="dua">
                    @foreach (['bni' => 'BNI', 'bri' => 'BRI'] as $bank => $labelBank)
                        <div class="fld">
                            <label for="awal_{{ $bank }}_tampil">Saldo <span class="bank">{{ $labelBank }}</span></label>
                            <div class="rp-input"><span class="rp">Rp</span>
                                <input type="text" id="awal_{{ $bank }}_tampil" data-rupiah="awal_{{ $bank }}" inputmode="numeric" autocomplete="off">
                            </div>
                            <input type="hidden" name="{{ $bank }}" id="awal_{{ $bank }}" value="{{ $awal->{$bank} }}">
                        </div>
                    @endforeach
                </div>
                <div class="dlg-catatan">Mengubah saldo awal ikut mengubah saldo akhir semua hari.</div>
            </div>
            <div class="dlg-kaki">
                <div class="kanan">
                    <button type="button" class="btn btn-ghost" data-tutup>Batal</button>
                    <button type="submit" class="btn btn-accent">Simpan</button>
                </div>
            </div>
        </form>
    </dialog>

    @include('admin.keuangan._impor', [
        'impor'  => route('admin.keuangan.buku-besar.import'),
        'kolom'  => 'lembar Excel "SALDO BANK" apa adanya: TGL | UANG MASUK BNI, BRI, TOTAL | UANG KELUAR … | SALDO AKHIR … | KETERANGAN',
        'contoh' => '01-Apr-26,,,0,,8.536.532,,8.536.532,,42.242.518,10.000,42.252.518',
    ])
@endsection

@push('scripts')
<script>
(function () {
    const rp = n => (n < 0 ? '-Rp ' : 'Rp ') + Math.abs(n).toLocaleString('id-ID');
    const nilai = id => parseInt(document.getElementById(id).value || '0', 10) || 0;

    // Datang dari tombol "Catat Transaksi" di halaman Saldo: langsung buka
    // jendela catat hari ini, lalu buang ?catat supaya muat ulang tidak
    // membukanya lagi.
    const qs = new URLSearchParams(location.search);
    if (qs.has('catat')) {
        qs.delete('catat');
        history.replaceState(null, '', location.pathname + (qs.toString() ? '?' + qs : ''));
        window.addEventListener('load', function () {
            if (!document.querySelector('dialog[open]')) {
                const tombol = document.querySelector('.keu-aksi > [data-buka="dlg-catat"]');
                if (tombol) tombol.click();
            }
        });
    }

    function hitung() {
        document.getElementById('bb-masuk').textContent = rp(nilai('masuk_bni') + nilai('masuk_bri'));
        document.getElementById('bb-keluar').textContent = rp(nilai('keluar_bni') + nilai('keluar_bri'));
    }
    document.querySelectorAll('#dlg-catat [data-rupiah]').forEach(el => el.addEventListener('input', hitung));
    document.getElementById('dlg-catat').addEventListener('keu:isi', hitung);
    hitung();

    // Sembunyikan hari tanpa transaksi — pilihan diingat di peramban ini.
    const sepi = document.getElementById('bb-sepi');
    const tabel = document.getElementById('bb-tabel');
    try { sepi.checked = localStorage.getItem('bb-sepi') === '1'; } catch (e) {}
    const terapkan = () => tabel.classList.toggle('tanpa-sepi', sepi.checked);
    sepi.addEventListener('change', function () {
        try { localStorage.setItem('bb-sepi', sepi.checked ? '1' : '0'); } catch (e) {}
        terapkan();
    });
    terapkan();
})();
</script>
@endpush
