@extends('layouts.panel')

@section('title', 'Absensi Karyawan')

@push('head')
<style>
    .head-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .nav-tgl { padding: 14px 18px; margin-bottom: 22px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .nav-tgl .tgl { font-weight: 700; font-size: 1rem; flex: 1 1 auto; }
    .nav-tgl input[type=date] {
        font-family: inherit; font-size: .95rem; color: var(--text); color-scheme: dark;
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; padding: 9px 12px;
    }
    .nav-tgl input[type=date]:focus { outline: none; border-color: var(--accent); }

    .ring { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; font-size: .82rem; color: var(--muted); }
    .ring .pill { border: 1px solid var(--border-2); border-radius: 999px; padding: 5px 12px; background: var(--surface); }
    .ring .pill b { font-variant-numeric: tabular-nums; }
    .ring .pill.h b { color: var(--ok); }
    .ring .pill.t b { color: var(--alert); }
    .ring .pill.b b { color: var(--accent-2); }

    .ab-list { display: flex; flex-direction: column; gap: 12px; }
    .ab {
        border: 1px solid var(--border-2); border-radius: 14px; padding: 18px; background: var(--surface);
        display: flex; gap: 18px; flex-wrap: wrap; align-items: flex-start;
    }
    .ab.belum { border-left: 3px solid var(--accent-2); }
    .ab.hadir { border-left: 3px solid var(--ok); }
    .ab.telat { border-left: 3px solid var(--alert); }

    .ab-info { flex: 1 1 200px; min-width: 0; }
    .ab-nama { font-weight: 700; font-size: 1.02rem; }
    .ab-jam { margin-top: 3px; font-size: .84rem; color: var(--muted); font-variant-numeric: tabular-nums; }

    .st-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; font-size: .74rem; font-weight: 700; margin-top: 9px; }
    .st-hadir { background: rgba(74,222,128,.12); color: var(--ok); border: 1px solid rgba(74,222,128,.28); }
    .st-telat { background: var(--alert-soft); color: var(--alert); border: 1px solid var(--alert-line); }
    .st-belum { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-violet); }

    /* Dua bukti berdampingan: selfie kehadiran + kartu kunci di laci. */
    .ab-fotos { display: flex; gap: 12px; flex-wrap: wrap; }
    .ab-foto { text-align: center; }
    .ab-foto .lbl { font-size: .68rem; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
    .ab-foto img {
        width: 150px; height: 112px; object-fit: cover; border-radius: 12px; cursor: zoom-in; display: block;
        border: 1px solid var(--border-2); box-shadow: 0 14px 30px -18px var(--accent-glow);
    }
    .ab-foto .kosong {
        width: 150px; height: 112px; border-radius: 12px; display: grid; place-items: center;
        border: 1px dashed var(--border-2); background: var(--bg); color: var(--muted); font-size: .74rem;
    }

    .ab-empty { text-align: center; color: var(--muted); padding: 40px 20px; }
</style>
@endpush

@section('content')
    <div class="page-head head-row">
        <div>
            <h1>Absensi Karyawan</h1>
            <p>Selfie kehadiran dan foto kartu kunci di laci untuk tiap shift.</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap">
            <a href="{{ route('admin.absensi.export', ['tanggal' => $tanggal->toDateString()]) }}" class="btn btn-ghost">
                @include('partials.icon', ['name' => 'download', 'class' => 'ic-sm']) Unduh Excel
            </a>
            @include('partials.tombol-purge', [
                'action' => route('admin.purge.absensi'),
                'bulan'  => $tanggal->format('Y-m'),
                'label'  => 'absensi & jadwal',
            ])
        </div>
    </div>

    <form method="GET" action="{{ route('admin.absensi.index') }}" class="card nav-tgl">
        <a href="{{ route('admin.absensi.index', ['tanggal' => $prev]) }}" class="btn btn-ghost btn-sm">
            @include('partials.icon', ['name' => 'chevron-left', 'class' => 'ic-xs'])
        </a>
        <span class="tgl">{{ $tanggal->translatedFormat('l, j F Y') }}</span>
        <input type="date" name="tanggal" value="{{ $tanggal->toDateString() }}" onchange="this.form.submit()">
        <a href="{{ route('admin.absensi.index', ['tanggal' => $next]) }}" class="btn btn-ghost btn-sm">
            @include('partials.icon', ['name' => 'chevron-right', 'class' => 'ic-xs'])
        </a>
    </form>

    @php
        $sudah = $shift->filter(fn ($j) => $j->absensi !== null);
        $telat = $sudah->filter(fn ($j) => $j->absensi->telat());
    @endphp

    @if ($shift->isNotEmpty())
        <div class="ring">
            <span class="pill h">Hadir <b>{{ $sudah->count() - $telat->count() }}</b></span>
            <span class="pill t">Telat <b>{{ $telat->count() }}</b></span>
            <span class="pill b">Belum absen <b>{{ $shift->count() - $sudah->count() }}</b></span>
        </div>
    @endif

    <div class="ab-list">
        @forelse ($shift as $j)
            @php $a = $j->absensi; @endphp
            <div class="ab {{ $a === null ? 'belum' : ($a->telat() ? 'telat' : 'hadir') }}">
                <div class="ab-info">
                    <div class="ab-nama">{{ $j->karyawan?->nama ?? '—' }}</div>
                    <div class="ab-jam">
                        Shift {{ $j->labelJam() }}
                        @if ($a) · absen {{ $a->waktu_absen->format('H:i') }} @endif
                    </div>
                    @if ($a === null)
                        <span class="st-badge st-belum">Belum absen</span>
                    @elseif ($a->telat())
                        <span class="st-badge st-telat">{{ $a->labelTelat() }}</span>
                    @else
                        <span class="st-badge st-hadir">
                            @include('partials.icon', ['name' => 'check', 'class' => 'ic-xs']) Tepat waktu
                        </span>
                    @endif
                </div>

                <div class="ab-fotos">
                    <div class="ab-foto">
                        <div class="lbl">Selfie</div>
                        @if ($a && $a->foto)
                            <img src="{{ route('absensi.foto', $a) }}" alt="Selfie {{ $j->karyawan?->nama }}"
                                 data-lightbox="{{ route('absensi.foto', $a) }}">
                        @else
                            <div class="kosong">Belum ada</div>
                        @endif
                    </div>
                    <div class="ab-foto">
                        <div class="lbl">Kartu di laci</div>
                        @if ($a && $a->foto_kartu)
                            <img src="{{ route('absensi.kartu', $a) }}" alt="Kartu kunci {{ $j->karyawan?->nama }}"
                                 data-lightbox="{{ route('absensi.kartu', $a) }}">
                        @else
                            <div class="kosong">Belum ada</div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="card ab-empty">Belum ada jadwal untuk {{ $tanggal->translatedFormat('l, j F Y') }}.</div>
        @endforelse
    </div>
@endsection
