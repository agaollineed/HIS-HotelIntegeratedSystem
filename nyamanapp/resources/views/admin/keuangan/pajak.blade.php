@extends('layouts.panel')

@section('title', 'Pajak')

@push('head')
<style>
    /* Status bayar: satu tombol centang per baris. */
    .bayar-tombol {
        display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-family: inherit;
        border-radius: 999px; padding: 6px 13px; font-size: .8rem; font-weight: 700; white-space: nowrap;
        border: 1px solid var(--border-2); background: var(--bg); color: var(--muted);
    }
    .bayar-tombol .kotak-centang { width: 16px; height: 16px; border-radius: 5px; border: 2px solid currentColor; display: inline-grid; place-items: center; }
    .bayar-tombol.lunas { color: var(--ok); border-color: var(--ok-line, rgba(74,222,128,.3)); background: var(--ok-soft, rgba(74,222,128,.12)); }
    .bayar-tombol.lunas .kotak-centang { background: var(--ok); border-color: var(--ok); color: #0a0710; }
    .bayar-tombol.belum { color: var(--alert); border-color: var(--alert-line); background: var(--alert-soft); }
    .bayar-tombol:hover { filter: brightness(1.15); }
    td form { margin: 0; }
</style>
@endpush

@section('content')
    @php
        $rp = fn (int $n) => \App\Support\CsvKeuangan::rupiah($n);
        $bolehHapus = auth()->user()->bolehHapusKeuangan();
        $isiBaru = ['periode' => $bulanIni, 'nominal' => null, 'dibayar' => false, '_judul' => 'Catat Pajak'];
    @endphp

    @include('admin.keuangan._kepala', [
        'sub'     => 'Nominal pajak per bulan dan status bayarnya.',
        'catat'   => ['label' => 'Catat Pajak', 'dialog' => 'dlg-catat', 'isi' => $isiBaru],
        'lainnya' => [
            ['label' => 'Export CSV', 'href' => route('admin.keuangan.pajak.export'), 'ket' => 'Semua bulan, bisa diimpor ulang'],
            ['label' => 'Import CSV', 'dialog' => 'dlg-impor', 'ket' => 'Masukkan data lama sekaligus'],
        ],
    ])

    <div class="hero">
        <div class="hero-utama">
            <div class="lbl">Pajak belum dibayar</div>
            <div class="angka" style="color: {{ $ringkas['belum'] > 0 ? 'var(--alert)' : 'var(--ok)' }}">{{ $rp($ringkas['belum']) }}</div>
            <div class="ket">
                @if ($baris->isEmpty())
                    Belum ada pajak yang dicatat.
                @elseif ($ringkas['nBelum'] > 0)
                    <span class="chip turun">{{ $ringkas['nBelum'] }} bulan belum dibayar</span>
                    {{ $baris->reject->dibayar()->sortBy('periode')->map->labelPeriode()->implode(', ') }}
                @else
                    <span class="chip naik">Semua pajak sudah dibayar</span>
                @endif
            </div>
        </div>
        <div class="card hero-stat">
            <div class="lbl">Sudah dibayar</div>
            <div class="n" style="color: var(--ok)">{{ $rp($ringkas['lunas']) }}</div>
        </div>
        <div class="card hero-stat">
            <div class="lbl">Total pajak</div>
            <div class="n">{{ $rp($ringkas['total']) }}</div>
            <div class="sub">{{ $baris->count() }} bulan tercatat</div>
        </div>
    </div>

    <div class="card keu-kartu">
        <div class="keu-kartu-kepala">
            <h2>Per Bulan</h2>
            <span class="ket">centang untuk menandai dibayar · klik baris untuk mengubah</span>
        </div>
        @if ($baris->isEmpty())
            <div class="keu-kosong">Belum ada pajak yang dicatat.</div>
        @else
            <div class="keu-tabel-wrap">
                <table class="keu-tabel">
                    <thead><tr><th>Bulan</th><th class="num">Nominal</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($baris as $p)
                            @php
                                $isi = ['periode' => $p->kunciPeriode(), 'nominal' => $p->nominal, 'dibayar' => $p->dibayar(),
                                    '_kunci' => true, '_judul' => 'Ubah Pajak ' . $p->labelPeriode(),
                                    '_hapus' => $bolehHapus ? route('admin.keuangan.pajak.destroy', $p) : null,
                                    '_hapusTanya' => 'Hapus pajak ' . $p->labelPeriode() . '?'];
                            @endphp
                            <tr class="bisa-klik" tabindex="0" data-buka="dlg-catat" data-isi="{{ json_encode($isi) }}">
                                <td><b>{{ $p->labelPeriode() }}</b></td>
                                <td class="num"><b>{{ $rp($p->nominal) }}</b></td>
                                <td data-jangan-buka>
                                    <form method="POST" action="{{ route('admin.keuangan.pajak.bayar', $p) }}"
                                          @if ($p->dibayar()) onsubmit="return confirm('Batalkan status dibayar pajak {{ $p->labelPeriode() }}?');" @endif>
                                        @csrf
                                        <button type="submit" class="bayar-tombol {{ $p->dibayar() ? 'lunas' : 'belum' }}"
                                                title="{{ $p->dibayar() ? 'Klik untuk membatalkan' : 'Klik untuk menandai sudah dibayar' }}">
                                            <span class="kotak-centang">@if ($p->dibayar())@include('partials.icon', ['name' => 'check', 'class' => 'ic-xs'])@endif</span>
                                            {{ $p->dibayar() ? 'Dibayar ' . $p->tanggal_bayar->translatedFormat('j M Y') : 'Belum dibayar' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="pena">@include('partials.icon', ['name' => 'pensil', 'class' => 'ic-xs'])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Jendela catat / ubah pajak --}}
    <dialog class="keu-dlg" id="dlg-catat" data-judul-baru="Catat Pajak">
        <form method="POST" action="{{ route('admin.keuangan.pajak.store') }}">
            @csrf
            <input type="hidden" name="_dialog" value="dlg-catat">
            <div class="dlg-kepala">
                <h2 data-judul>Catat Pajak</h2>
                <button type="button" class="dlg-x" data-tutup aria-label="Tutup">@include('partials.icon', ['name' => 'x', 'class' => 'ic-sm'])</button>
            </div>
            <div class="dlg-isi">
                <div class="dua">
                    <div class="fld">
                        <label for="periode">Bulan</label>
                        <input type="month" id="periode" name="periode" required max="{{ $bulanIni }}" data-kunci value="{{ old('periode', $bulanIni) }}">
                    </div>
                    <div class="fld">
                        <label for="nominal_tampil">Nominal</label>
                        <div class="rp-input"><span class="rp">Rp</span>
                            <input type="text" id="nominal_tampil" data-rupiah="nominal" inputmode="numeric" autocomplete="off" placeholder="0" required>
                        </div>
                        <input type="hidden" name="nominal" id="nominal" value="{{ old('nominal') }}">
                    </div>
                </div>
                <label class="centang">
                    <input type="checkbox" name="dibayar" value="1" id="dibayar" @checked(old('dibayar'))> Sudah dibayar
                </label>
                <div class="dlg-catatan" id="pj-tgl"></div>
            </div>
            <div class="dlg-kaki">
                @if ($bolehHapus)
                    <button type="submit" form="hapus-pajak" class="btn btn-danger btn-sm" data-hapus hidden>Hapus</button>
                @endif
                <div class="kanan">
                    <button type="button" class="btn btn-ghost" data-tutup>Batal</button>
                    <button type="submit" class="btn btn-accent">Simpan</button>
                </div>
            </div>
        </form>
        @if ($bolehHapus)
            <form method="POST" id="hapus-pajak" class="dlg-hapus">@csrf @method('DELETE')</form>
        @endif
    </dialog>

    @include('admin.keuangan._impor', [
        'impor'  => route('admin.keuangan.pajak.import'),
        'kolom'  => 'bulan, nominal, dibayar (ya/tidak), tanggal_bayar (opsional)',
        'contoh' => '2026-04,1500000,ya',
    ])
@endsection

@push('scripts')
<script>
(function () {
    const periode = document.getElementById('periode');
    const dibayar = document.getElementById('dibayar');
    const ket = document.getElementById('pj-tgl');
    const MULAI = @json(substr(\App\Models\PajakBulanan::MULAI_TANGGAL_NYATA, 0, 7));
    const HARI  = @json(\App\Models\PajakBulanan::TANGGAL_BAYAR_LAMA);

    // Tanggal bayar yang akan dicatat — aturannya sama dengan server.
    function pratinjau() {
        if (!dibayar.checked || !periode.value) {
            ket.textContent = 'Mulai November 2026 tanggal bayar = hari dicentang; bulan sebelumnya = tanggal ' + HARI + ' bulan berikutnya.';
            return;
        }
        let tgl;
        if (periode.value >= MULAI) {
            tgl = new Date();
        } else {
            const [y, m] = periode.value.split('-').map(Number);
            tgl = new Date(y, m, HARI);   // bulan berikutnya (indeks bulan JS mulai 0)
        }
        ket.textContent = 'Dicatat dibayar ' + tgl.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
            + ' (pajak yang sudah dibayar tetap memakai tanggal lamanya).';
    }
    periode.addEventListener('change', pratinjau);
    dibayar.addEventListener('change', pratinjau);
    document.getElementById('dlg-catat').addEventListener('keu:isi', pratinjau);
    pratinjau();
})();
</script>
@endpush
