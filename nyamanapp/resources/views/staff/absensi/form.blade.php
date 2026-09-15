@extends('layouts.panel')

@section('title', 'Ambil Foto Absen')

@push('head')
<style>
    .ab-card { padding: 28px; max-width: 640px; }

    .ab-summary {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px;
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 12px;
        padding: 16px; margin-bottom: 24px;
    }
    .ab-summary .lbl { font-size: .7rem; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); margin-bottom: 4px; }
    .ab-summary .val { font-weight: 700; }
    .ab-summary .val.mono { font-variant-numeric: tabular-nums; }

    .peringatan {
        display: flex; gap: 10px; align-items: flex-start; margin-bottom: 20px;
        border-radius: 12px; padding: 13px 15px; font-size: .88rem;
    }
    .peringatan.telat { background: var(--alert-soft); border: 1px solid var(--alert-line); color: var(--alert); }
    .peringatan.aman  { background: rgba(74,222,128,.10); border: 1px solid rgba(74,222,128,.28); color: var(--ok); }

    .form-actions { display: flex; gap: 12px; margin-top: 26px; padding-top: 22px; border-top: 1px solid var(--border-2); }
</style>
@endpush

@section('content')
    <div class="page-head">
        <h1>Absen — {{ $jadwal->karyawan?->nama ?? '—' }}</h1>
        <p>Dua foto wajib: selfie dan kartu kunci di laci.</p>
    </div>

    <div class="card ab-card">
        <div class="ab-summary">
            <div>
                <div class="lbl">Karyawan</div>
                <div class="val">{{ $jadwal->karyawan?->nama ?? '—' }}</div>
            </div>
            <div>
                <div class="lbl">Tanggal</div>
                <div class="val">{{ $jadwal->tanggal->translatedFormat('l, j F Y') }}</div>
            </div>
            <div>
                <div class="lbl">Jam Shift</div>
                <div class="val mono">{{ $jadwal->labelJam() }}{{ $jadwal->lintasHari() ? ' (+1 hari)' : '' }}</div>
            </div>
            <div>
                <div class="lbl">Sekarang</div>
                <div class="val mono">{{ now()->format('H:i') }}</div>
            </div>
        </div>

        @if ($telat > 0)
            <div class="peringatan telat">
                @include('partials.icon', ['name' => 'alert', 'class' => 'ic-sm'])
                <span>Tercatat <b>telat {{ $telat }} menit</b></span>
            </div>
        @else
            <div class="peringatan aman">
                @include('partials.icon', ['name' => 'check', 'class' => 'ic-sm'])
                <span>Tercatat <b>tepat waktu</b></span>
            </div>
        @endif

        <form method="POST" action="{{ route('staff.absensi.store', $jadwal) }}" enctype="multipart/form-data">
            @csrf

            @include('partials.foto-uploader', [
                'name'   => 'foto',
                'ikon'   => 'camera',
                'judul'  => '1 — Selfie kehadiran',
                'pesan'  => 'Ambil selfie sebagai bukti kehadiran.',
                'hint'   => 'Wajib.',
                'kamera' => 'user',
            ])

            @include('partials.foto-uploader', [
                'name'   => 'foto_kartu',
                'ikon'   => 'key-card',
                'judul'  => '2 — Kartu kunci di laci',
                'pesan'  => 'Potret seluruh kartu yang ada di laci.',
                'hint'   => 'Wajib. Susun berjajar supaya nomornya terbaca.',
            ])

            <div class="form-actions">
                <a href="{{ route('staff.absensi.index', ['tanggal' => $jadwal->tanggal->toDateString()]) }}"
                   class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-accent">
                    @include('partials.icon', ['name' => 'check', 'class' => 'ic-sm']) Simpan Absen
                </button>
            </div>
        </form>
    </div>
@endsection
