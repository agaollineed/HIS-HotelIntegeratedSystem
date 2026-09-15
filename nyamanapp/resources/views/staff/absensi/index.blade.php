@extends('layouts.panel')

@section('title', 'Absensi')

@push('head')
<style>
    .head-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }

    .tgl-card { padding: 16px 20px; margin-bottom: 22px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .tgl-card .lbl { font-size: .72rem; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
    .tgl-card input[type=date] {
        font-family: inherit; font-size: 1rem; color: var(--text); color-scheme: dark;
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; padding: 10px 12px;
    }
    .tgl-card input[type=date]:focus { outline: none; border-color: var(--accent); }

    .abs-list { display: flex; flex-direction: column; gap: 12px; }
    .abs {
        display: flex; align-items: center; gap: 16px; flex-wrap: wrap; justify-content: space-between;
        border: 1px solid var(--border-2); border-radius: 14px; padding: 16px; background: var(--surface);
    }
    .abs.belum  { border-left: 3px solid var(--accent-2); }
    .abs.hadir  { border-left: 3px solid var(--ok); }
    .abs.telat  { border-left: 3px solid var(--alert); }

    .abs-main { display: flex; align-items: center; gap: 14px; flex: 1 1 260px; min-width: 0; }
    /* Dua bukti berdampingan: selfie kehadiran + kartu kunci di laci. */
    .abs-fotos { display: flex; gap: 6px; flex: 0 0 auto; }
    .abs-foto.kartu, .abs-kosong.kartu { border-color: var(--border-violet); }
    .abs-foto {
        width: 52px; height: 52px; border-radius: 12px; object-fit: cover; cursor: zoom-in;
        border: 1px solid var(--border-2); flex: 0 0 auto;
    }
    .abs-kosong {
        width: 52px; height: 52px; border-radius: 12px; flex: 0 0 auto;
        display: grid; place-items: center; color: var(--muted);
        border: 1px dashed var(--border-2); background: var(--bg);
    }
    .abs-nama { font-weight: 700; font-size: 1rem; }
    .abs-jam { margin-top: 2px; font-size: .84rem; color: var(--muted); font-variant-numeric: tabular-nums; }

    .abs-side { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .st-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 13px; border-radius: 999px; font-size: .76rem; font-weight: 700; }
    .st-hadir { background: rgba(74,222,128,.12); color: var(--ok); border: 1px solid rgba(74,222,128,.28); }
    .st-telat { background: var(--alert-soft); color: var(--alert); border: 1px solid var(--alert-line); }
    .st-belum { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-violet); }

    .abs-empty { text-align: center; color: var(--muted); padding: 40px 20px; }
    .abs-empty a { font-weight: 700; }

    .ring { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; font-size: .82rem; color: var(--muted); }
    .ring .pill { border: 1px solid var(--border-2); border-radius: 999px; padding: 5px 12px; background: var(--surface); }
    .ring .pill b { font-variant-numeric: tabular-nums; }
    .ring .pill.h b { color: var(--ok); }
    .ring .pill.t b { color: var(--alert); }
    .ring .pill.b b { color: var(--accent-2); }
</style>
@endpush

@section('content')
    <div class="page-head head-row">
        <div>
            <h1>Absensi</h1>
            <p>Butuh dua foto: selfie dan kartu kunci di laci.</p>
        </div>
        <a href="{{ route('staff.jadwal.index') }}" class="btn btn-ghost">
            @include('partials.icon', ['name' => 'calendar', 'class' => 'ic-sm']) Atur Jadwal
        </a>
    </div>

    <form method="GET" action="{{ route('staff.absensi.index') }}" class="card tgl-card">
        <div>
            <div class="lbl">Tanggal</div>
            <input type="date" name="tanggal" value="{{ $tanggal->toDateString() }}" onchange="this.form.submit()">
        </div>
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

    <div class="abs-list">
        @forelse ($shift as $j)
            @php $a = $j->absensi; @endphp
            <div class="abs {{ $a === null ? 'belum' : ($a->telat() ? 'telat' : 'hadir') }}">
                <div class="abs-main">
                    <div class="abs-fotos">
                        @if ($a && $a->foto)
                            <img src="{{ route('absensi.foto', $a) }}" alt="Selfie absen {{ $j->karyawan?->nama }}"
                                 class="abs-foto" title="Selfie kehadiran" data-lightbox="{{ route('absensi.foto', $a) }}">
                        @else
                            <span class="abs-kosong" title="Selfie kehadiran">@include('partials.icon', ['name' => 'camera', 'class' => 'ic-sm'])</span>
                        @endif

                        @if ($a && $a->foto_kartu)
                            <img src="{{ route('absensi.kartu', $a) }}" alt="Foto kartu kunci {{ $j->karyawan?->nama }}"
                                 class="abs-foto kartu" title="Kartu kunci di laci" data-lightbox="{{ route('absensi.kartu', $a) }}">
                        @else
                            <span class="abs-kosong kartu" title="Kartu kunci di laci">@include('partials.icon', ['name' => 'key-card', 'class' => 'ic-sm'])</span>
                        @endif
                    </div>
                    <div>
                        <div class="abs-nama">{{ $j->karyawan?->nama ?? '—' }}</div>
                        <div class="abs-jam">
                            {{ $j->labelJam() }}
                            @if ($a) · absen {{ $a->waktu_absen->format('H:i') }} @endif
                        </div>
                    </div>
                </div>

                <div class="abs-side">
                    @if ($a === null)
                        <span class="st-badge st-belum">Belum absen</span>
                        <a href="{{ route('staff.absensi.create', $j) }}" class="btn btn-orange btn-sm">
                            @include('partials.icon', ['name' => 'camera', 'class' => 'ic-xs']) Absen Sekarang
                        </a>
                    @elseif ($a->telat())
                        <span class="st-badge st-telat">{{ $a->labelTelat() }}</span>
                    @else
                        <span class="st-badge st-hadir">
                            @include('partials.icon', ['name' => 'check', 'class' => 'ic-xs']) Tepat waktu
                        </span>
                    @endif
                </div>
            </div>
        @empty
            <div class="card abs-empty">
                Belum ada jadwal untuk {{ $tanggal->translatedFormat('l, j F Y') }}.<br>
                <a href="{{ route('staff.jadwal.index', ['tanggal' => $tanggal->toDateString()]) }}">Susun jadwalnya dulu</a>.
            </div>
        @endforelse
    </div>
@endsection
