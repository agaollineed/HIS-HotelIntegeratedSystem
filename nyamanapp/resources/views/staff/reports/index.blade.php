@extends('layouts.panel')

@section('title', 'Laporan')

@push('head')
<style>
    .form-card { padding: 28px; max-width: 640px; margin-bottom: 34px; }
    .fld { margin-bottom: 20px; }
    .fld label { display: block; font-size: .75rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
    .fld input[type=text], .fld textarea {
        width: 100%; font-family: inherit; font-size: 1rem; color: var(--text);
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; padding: 12px 14px;
    }
    .fld textarea { min-height: 130px; resize: vertical; line-height: 1.55; }
    .fld input:focus, .fld textarea:focus { outline: none; border-color: var(--accent); }
    .form-actions { display: flex; justify-content: flex-end; margin-top: 4px; }

    .subhead { margin: 0 0 14px; }
    .subhead h2 { margin: 0 0 4px; font-size: 1.2rem; font-weight: 800; letter-spacing: -.02em; }
    .subhead p { margin: 0; color: var(--muted); font-size: .9rem; }

    .tickets { display: flex; flex-direction: column; gap: 16px; }
    .ticket { padding: 22px 24px; }
    .t-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .t-head h3 { margin: 0; font-size: 1.05rem; font-weight: 800; letter-spacing: -.015em; }
    .t-foto {
        margin-top: 12px; width: 190px; max-width: 100%; border-radius: 12px; cursor: zoom-in; display: block;
        border: 1px solid var(--border-2); box-shadow: 0 14px 30px -18px var(--accent-glow);
    }
    .status { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .status-open    { background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border); }
    .status-selesai { background: rgba(74,222,128,.12);  color: var(--ok);       border: 1px solid rgba(74,222,128,.28); }
    .t-meta { margin: 6px 0 0; font-size: .8rem; color: var(--muted); }
    .t-body { margin: 14px 0 0; font-size: .93rem; line-height: 1.6; color: var(--text); }
    .empty { text-align: center; color: var(--muted); padding: 40px 20px; }
</style>
@endpush

@section('content')
    <div class="page-head">
        <h1>Laporan</h1>
    </div>

    <div class="card form-card">
        <form method="POST" action="{{ route('staff.reports.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="fld">
                <label for="judul">Judul</label>
                <input id="judul" type="text" name="judul" value="{{ old('judul') }}"
                       placeholder="Contoh: AC kamar 207 kurang dingin" required>
            </div>

            <div class="fld">
                <label for="isi">Isi Laporan</label>
                <textarea id="isi" name="isi" placeholder="Jelaskan detail kendala atau kebutuhannya…" required>{{ old('isi') }}</textarea>
            </div>

            <div class="fld">
                <label>Lampiran Foto <span style="text-transform:none; letter-spacing:0">(opsional)</span></label>
                @include('partials.foto-uploader', [
                    'name'  => 'foto',
                    'ikon'  => 'camera',
                    'pesan' => 'Potret kendalanya bila perlu.',
                    'hint'  => 'Opsional. JPG/PNG, otomatis diperkecil.',
                ])
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-accent">Kirim Laporan</button>
            </div>
        </form>
    </div>

    <div class="subhead">
        <h2>Laporan Saya</h2>
        <p>Tiket yang pernah Anda kirim, terbaru dulu.</p>
    </div>

    <div class="tickets">
        @forelse ($reports as $report)
            <div class="card ticket">
                <div class="t-head">
                    <h3>{{ $report->judul }}</h3>
                    <span class="status status-{{ $report->status }}">{{ $report->status === 'open' ? 'Open' : 'Selesai' }}</span>
                </div>
                <div class="t-meta">
                    Dikirim {{ $report->created_at->translatedFormat('d M Y, H:i') }}
                    · {{ $report->created_at->diffForHumans() }}
                </div>
                <p class="t-body">{!! nl2br(e($report->isi)) !!}</p>
                @if ($report->foto)
                    <img src="{{ route('laporan.foto', $report) }}" alt="Lampiran laporan"
                         class="t-foto" data-lightbox="{{ route('laporan.foto', $report) }}">
                @endif
            </div>
        @empty
            <div class="card"><div class="empty">Belum ada laporan. Tiket pertama Anda akan muncul di sini.</div></div>
        @endforelse
    </div>
@endsection
