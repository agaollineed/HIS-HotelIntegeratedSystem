@extends('layouts.panel')

@section('title', 'Log Perubahan')

@push('head')
<style>
    .page-head.head-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .page-head.head-row .btn { flex-shrink: 0; }

    .table-card { padding: 6px; overflow: hidden; }
    .tscroll { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; min-width: 720px; }
    thead th {
        text-align: left; font-size: .72rem; letter-spacing: .07em; text-transform: uppercase;
        color: var(--muted); font-weight: 700; padding: 15px 16px; border-bottom: 1px solid var(--border-2);
    }
    tbody td { padding: 14px 16px; border-bottom: 1px solid var(--border-2); font-size: .93rem; vertical-align: middle; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover td { background: var(--hover); }

    .when { line-height: 1.3; white-space: nowrap; }
    .when .abs { font-weight: 500; }
    .when .rel { font-size: .78rem; color: var(--muted); }

    .item-nama { font-weight: 600; }

    /* Badge aksi */
    .tag { display: inline-block; padding: 4px 10px; border-radius: 8px; font-size: .74rem; font-weight: 700; letter-spacing: .02em; white-space: nowrap; }
    .tag-create    { background: var(--accent-soft);  color: var(--accent);  border: 1px solid var(--border); }
    .tag-increment { background: var(--accent-soft);  color: var(--accent);  border: 1px solid var(--border); }
    .tag-update    { background: var(--accent-2-soft);  color: var(--accent-2-600);       border: 1px solid var(--border-violet); }
    .tag-decrement { background: var(--danger-soft);   color: var(--danger); border: 1px solid var(--danger-line); }
    .tag-delete    { background: var(--danger-soft);   color: var(--danger); border: 1px solid var(--danger-line); }

    .detail { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .detail .txt { color: var(--text); }
    .delta { font-variant-numeric: tabular-nums; font-weight: 700; font-size: .82rem; padding: 3px 9px; border-radius: 7px; white-space: nowrap; }
    .delta.up   { background: rgba(22,163,74,.14); color: var(--ok); }
    .delta.down { background: var(--danger-soft); color: var(--danger); }

    .by { line-height: 1.3; }
    .by .nm { font-weight: 500; }
    .badge-role { display: inline-block; margin-top: 2px; padding: 2px 8px; border-radius: 6px; font-size: .7rem; font-weight: 700; text-transform: capitalize; background: var(--surface-2); color: var(--muted); border: 1px solid var(--border-2); }

    .empty { text-align: center; color: var(--muted); padding: 46px 20px; }
    .note { color: var(--muted); font-size: .82rem; margin: 14px 2px 0; }

    /* Toolbar unduh Excel per rentang tanggal */
    .purge-bulan { display: flex; gap: 8px; align-items: center; margin: 14px 0 18px; }
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

    .log-toolbar { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin: 0 0 18px; }
    .log-toolbar .rng { display: flex; flex-direction: column; gap: 6px; font-size: .72rem; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); }
    .log-toolbar input[type="date"] {
        height: 44px; padding: 0 12px; box-sizing: border-box;
        font-family: inherit; font-size: .92rem; color: var(--text);
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; color-scheme: dark;
    }
    .log-toolbar input[type="date"]:focus { outline: none; border-color: var(--accent); }
    .log-toolbar .grow { margin-left: auto; }
</style>
@endpush

@section('content')
    <div class="page-head head-row">
        <div>
            <h1>Log Perubahan</h1>
            <p>Riwayat lengkap setiap perubahan stok: penambahan, pengurangan, ubah harga, item baru, dan penghapusan.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-ghost">@include('partials.icon', ['name' => 'arrow-left', 'class' => 'ic-sm']) Kembali</a>
    </div>

    <form method="GET" action="{{ route('log') }}" class="log-toolbar">
        <label class="rng">Dari tanggal
            <input type="date" name="from" value="{{ $from ?? '' }}">
        </label>
        <label class="rng">Sampai tanggal
            <input type="date" name="to" value="{{ $to ?? '' }}">
        </label>
        <button type="submit" class="btn btn-ghost">Terapkan</button>
        <button type="submit" formaction="{{ route('log.export') }}" class="btn btn-accent grow">@include('partials.icon', ['name' => 'download', 'class' => 'ic-sm']) Unduh Excel</button>
        @if (($from ?? '') !== '' || ($to ?? '') !== '')
            <a href="{{ route('log') }}" class="btn btn-ghost">Reset</a>
        @endif
    </form>

    @if (auth()->user()->bolehHapusDataBulanan())
    <form method="POST" action="{{ route('admin.purge.stocklogs') }}" class="purge-bulan"
          onsubmit="return confirm('Hapus SEMUA log stok pada bulan terpilih?\n\nTindakan ini tidak bisa dibatalkan.\nPastikan sudah Unduh Excel terlebih dahulu.')">
        @csrf
        <input type="month" name="bulan" value="{{ now()->subMonth()->format('Y-m') }}" required>
        <button type="submit" class="btn-purge">
            @include('partials.icon', ['name' => 'x', 'class' => 'ic-sm']) Hapus Log Bulan Ini
        </button>
    </form>
    @endif

    @php
        $labels = [
            'create'    => 'Item Baru',
            'update'    => 'Ubah',
            'increment' => 'Tambah',
            'decrement' => 'Kurang',
            'delete'    => 'Hapus',
        ];
    @endphp

    @if ($logs->isEmpty())
        <div class="card table-card">
            <div class="empty">
                @if (($from ?? '') !== '' || ($to ?? '') !== '')
                    Tidak ada aktivitas pada rentang tanggal tersebut.
                @else
                    Belum ada aktivitas. Setiap perubahan stok akan tercatat di sini.
                @endif
            </div>
        </div>
    @else
        <div class="card table-card">
            <div class="tscroll">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Item</th>
                            <th>Aksi</th>
                            <th>Detail Perubahan</th>
                            <th>Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td>
                                    <div class="when">
                                        <div class="abs">{{ $log->created_at?->translatedFormat('d M Y, H:i') }}</div>
                                        <div class="rel">{{ $log->created_at?->diffForHumans() }}</div>
                                    </div>
                                </td>
                                <td class="item-nama">{{ $log->nama }}</td>
                                <td>
                                    <span class="tag tag-{{ $log->action }}">{{ $labels[$log->action] ?? ucfirst($log->action) }}</span>
                                </td>
                                <td>
                                    <div class="detail">
                                        <span class="txt">{{ $log->keterangan }}</span>
                                        @if (! is_null($log->selisih) && $log->selisih !== 0)
                                            <span class="delta {{ $log->selisih > 0 ? 'up' : 'down' }}">{{ sprintf('%+d', $log->selisih) }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="by">
                                        <div class="nm">{{ $log->by_name }}</div>
                                        <span class="badge-role">{{ $log->by_role }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if ($logs->count() >= 200)
            <p class="note">Menampilkan 200 aktivitas terbaru.</p>
        @endif
    @endif
@endsection
