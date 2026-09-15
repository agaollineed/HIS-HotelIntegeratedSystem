@extends('layouts.panel')

@section('title', 'Laporan')

@push('head')
<style>
    .head-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .purge-bulan { display: inline-flex; gap: 8px; align-items: center; }
    .purge-bulan input[type=month] {
        font-family: inherit; font-size: .88rem; color: var(--text); color-scheme: dark;
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; padding: 9px 12px;
    }
    .btn-purge {
        display: inline-flex; align-items: center; gap: 8px; cursor: pointer;
        font-family: inherit; font-weight: 700; font-size: .88rem;
        background: transparent; color: var(--danger);
        border: 1px solid var(--danger-line); border-radius: 12px; padding: 11px 18px;
    }
    .btn-purge:hover { background: var(--danger-soft); }
    .tabs { display: flex; gap: 8px; flex-wrap: wrap; margin: 0 0 20px; }
    .tab {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 9px 16px; border-radius: 999px; text-decoration: none;
        font-weight: 700; font-size: .88rem; color: var(--muted);
        border: 1px solid var(--border-2); transition: .2s;
    }
    .tab:hover { color: var(--accent); border-color: var(--accent); }
    .tab.active { background: var(--grad); color: #ffffff; border-color: var(--accent); }
    .tab .n {
        font-variant-numeric: tabular-nums; font-size: .72rem;
        padding: 2px 8px; border-radius: 999px;
        background: var(--accent-soft); color: var(--accent);
    }
    .tab.active .n { background: rgba(255,255,255,.22); color: #ffffff; }

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
    .t-meta b { color: var(--text); font-weight: 600; }
    .t-body { margin: 14px 0 0; font-size: .93rem; line-height: 1.6; color: var(--text); }
    .t-actions { display: flex; justify-content: flex-end; margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border-2); }
    .empty { text-align: center; color: var(--muted); padding: 46px 20px; }
</style>
@endpush

@section('content')
    <div class="page-head head-row">
        <div>
            <h1>Laporan</h1>
            <p>Tiket dari seluruh staff, terbaru dulu.</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center">
            <a href="{{ route('admin.reports.export', $tab === 'semua' ? [] : ['status' => $tab]) }}" class="btn btn-ghost">
                @include('partials.icon', ['name' => 'download', 'class' => 'ic-sm']) Unduh Excel
            </a>
            <form method="POST" action="{{ route('admin.purge.reports') }}" class="purge-bulan"
                  onsubmit="return confirm('Hapus SEMUA tiket laporan pada bulan terpilih?\n\nLampiran fotonya ikut terhapus dan tidak bisa dikembalikan.\nPastikan sudah Unduh Excel terlebih dahulu.')">
                @csrf
                <input type="month" name="bulan" value="{{ now()->subMonth()->format('Y-m') }}" required>
                <button type="submit" class="btn-purge">
                    @include('partials.icon', ['name' => 'x', 'class' => 'ic-sm']) Hapus Bulan Ini
                </button>
            </form>
        </div>
    </div>

    <div class="tabs">
        @foreach (['semua' => 'Semua', 'open' => 'Open', 'selesai' => 'Selesai'] as $key => $label)
            <a href="{{ route('admin.reports.index', $key === 'semua' ? [] : ['status' => $key]) }}"
               class="tab {{ $tab === $key ? 'active' : '' }}">
                {{ $label }} <span class="n">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    <div class="tickets">
        @forelse ($reports as $report)
            <div class="card ticket">
                <div class="t-head">
                    <h3>{{ $report->judul }}</h3>
                    <span class="status status-{{ $report->status }}">{{ $report->status === 'open' ? 'Open' : 'Selesai' }}</span>
                </div>
                <div class="t-meta">
                    Dari <b>{{ $report->user?->name ?? '—' }}</b>
                    · {{ $report->created_at->translatedFormat('d M Y, H:i') }}
                    · {{ $report->created_at->diffForHumans() }}
                </div>
                <p class="t-body">{!! nl2br(e($report->isi)) !!}</p>
                @if ($report->foto)
                    <img src="{{ route('laporan.foto', $report) }}" alt="Lampiran laporan"
                         class="t-foto" data-lightbox="{{ route('laporan.foto', $report) }}">
                @endif
                <div class="t-actions">
                    <form method="POST" action="{{ route('admin.reports.status', $report) }}">
                        @csrf
                        @method('PATCH')
                        @if ($report->status === 'open')
                            <button type="submit" class="btn btn-accent btn-sm">@include('partials.icon', ['name' => 'check', 'class' => 'ic-xs']) Tandai Selesai</button>
                        @else
                            <button type="submit" class="btn btn-ghost btn-sm">@include('partials.icon', ['name' => 'undo', 'class' => 'ic-xs']) Buka Kembali</button>
                        @endif
                    </form>
                </div>
            </div>
        @empty
            <div class="card"><div class="empty">
                @if ($tab === 'semua')
                    Belum ada laporan dari staff.
                @else
                    Tidak ada laporan dengan status "{{ $tab === 'open' ? 'Open' : 'Selesai' }}".
                @endif
            </div></div>
        @endforelse
    </div>
@endsection
