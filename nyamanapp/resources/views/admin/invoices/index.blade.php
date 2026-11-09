@extends('layouts.panel')

@section('title', 'Invoice')

@push('head')
<style>
    .head-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }

    .nav-bulan { padding: 14px 18px; margin-bottom: 22px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .nav-bulan .bln { font-weight: 700; font-size: 1rem; flex: 1 1 auto; text-align: center; }

    /* Ringkasan: yang dibutuhkan admin adalah gambaran, bukan entri satuan. */
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin-bottom: 22px; }
    .stat { border: 1px solid var(--border-2); border-radius: 16px; padding: 18px; background: var(--surface); }
    .stat .l { font-size: .7rem; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
    .stat .n { margin-top: 7px; font-size: 1.5rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    .stat .s { margin-top: 3px; font-size: .8rem; color: var(--muted); font-variant-numeric: tabular-nums; }
    .stat.semua .n { color: var(--accent); }
    .stat.lunas .n { color: var(--ok); }
    .stat.belum .n { color: var(--alert); }

    table.inv-tabel { width: 100%; border-collapse: collapse; font-size: .88rem; }
    .inv-tabel th {
        text-align: left; padding: 12px 14px; font-size: .68rem; letter-spacing: .07em;
        text-transform: uppercase; color: var(--muted); font-weight: 700;
        border-bottom: 1px solid var(--border-2);
    }
    .inv-tabel td { padding: 13px 14px; border-bottom: 1px solid var(--border-2); }
    .inv-tabel tr:last-child td { border-bottom: none; }
    .inv-tabel .ang { text-align: right; font-variant-numeric: tabular-nums; }
    .inv-tabel .nomor { color: var(--accent); font-weight: 700; font-variant-numeric: tabular-nums; }
    .inv-tabel .nama { font-weight: 700; }

    .tag { display: inline-block; padding: 4px 11px; border-radius: 999px; font-size: .7rem; font-weight: 700; }
    .tag.lunas { background: rgba(74,222,128,.12); color: var(--ok); border: 1px solid rgba(74,222,128,.28); }
    .tag.belum { background: var(--alert-soft); color: var(--alert); border: 1px solid var(--alert-line); }

    .inv-empty { text-align: center; color: var(--muted); padding: 40px 20px; }
    .tabel-card { padding: 6px 4px; overflow-x: auto; }
</style>
@endpush

@section('content')
    <div class="page-head head-row">
        <div>
            <h1>Invoice</h1>
            <p>Ringkasan tagihan yang diterbitkan staff dan admin.</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap">
            {{-- Admin & superadmin juga berdiri di meja depan: membuat dan
                 mencetak invoice tidak perlu menunggu staff. Formulir dan
                 aturannya sama persis dengan milik staff. --}}
            <a href="{{ route('admin.invoices.create') }}" class="btn btn-accent">
                @include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm']) Buat Invoice
            </a>
            <a href="{{ route('admin.invoices.export', ['bulan' => $bulan->format('Y-m')]) }}" class="btn btn-ghost">
                @include('partials.icon', ['name' => 'download', 'class' => 'ic-sm']) Unduh Excel
            </a>
            @include('partials.tombol-purge', [
                'action' => route('admin.purge.invoices'),
                'bulan'  => $bulan->format('Y-m'),
                'label'  => 'invoice',
            ])
        </div>
    </div>

    <div class="card nav-bulan">
        <a href="{{ route('admin.invoices.index', ['bulan' => $prevBulan]) }}" class="btn btn-ghost btn-sm">
            @include('partials.icon', ['name' => 'chevron-left', 'class' => 'ic-xs'])
        </a>
        <span class="bln">{{ $bulan->translatedFormat('F Y') }}</span>
        <a href="{{ route('admin.invoices.index', ['bulan' => $nextBulan]) }}" class="btn btn-ghost btn-sm">
            @include('partials.icon', ['name' => 'chevron-right', 'class' => 'ic-xs'])
        </a>
    </div>

    <div class="stats">
        <div class="stat semua">
            <div class="l">Invoice Terbit</div>
            <div class="n">{{ $ringkas['jumlah'] }}</div>
            <div class="s">{{ \App\Models\Invoice::rupiah($ringkas['nilai']) }}</div>
        </div>
        <div class="stat lunas">
            <div class="l">Lunas</div>
            <div class="n">{{ $ringkas['lunas'] }}</div>
            <div class="s">{{ \App\Models\Invoice::rupiah($ringkas['nilaiLunas']) }}</div>
        </div>
        <div class="stat belum">
            <div class="l">Belum Lunas</div>
            <div class="n">{{ $ringkas['belum'] }}</div>
            <div class="s">{{ \App\Models\Invoice::rupiah($ringkas['nilaiBelum']) }}</div>
        </div>
    </div>

    <div class="card tabel-card">
        @if ($invoices->isEmpty())
            <div class="inv-empty">Belum ada invoice pada {{ $bulan->translatedFormat('F Y') }}.</div>
        @else
            <table class="inv-tabel">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Tamu</th>
                        <th>Kamar</th>
                        <th class="ang">Malam</th>
                        <th class="ang">Total</th>
                        <th>Status</th>
                        <th>Dibuat</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $inv)
                        <tr>
                            <td class="nomor">{{ $inv->nomor }}</td>
                            <td class="nama">{{ $inv->nama_tamu }}</td>
                            @php $kmr = $inv->items->where('jenis', 'kamar'); @endphp
                            <td>
                                @if ($kmr->isNotEmpty())
                                    {{ $kmr->pluck('uraian')->implode(' + ') }}
                                @else
                                    {{ $inv->tipe_kamar }}
                                @endif
                            </td>
                            <td class="ang">{{ $kmr->max('malam') ?? $inv->malam }}</td>
                            <td class="ang">{{ \App\Models\Invoice::rupiah($inv->total) }}</td>
                            <td><span class="tag {{ $inv->lunas ? 'lunas' : 'belum' }}">{{ $inv->lunas ? 'Lunas' : 'Belum' }}</span></td>
                            <td>{{ $inv->created_at->translatedFormat('j M, H:i') }}<br>
                                <span style="color: var(--muted); font-size: .78rem">{{ $inv->creator?->name ?? '—' }}</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.invoices.show', $inv) }}" target="_blank" class="btn btn-ghost btn-sm">Lihat / Cetak</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
