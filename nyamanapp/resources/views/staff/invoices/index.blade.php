@extends('layouts.panel')

@section('title', 'Invoice')

@push('head')
<style>
    .head-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }

    .cari-bar {
        display: flex; align-items: center; gap: 9px; flex-wrap: wrap;
        padding: 4px 14px; margin-bottom: 18px;
    }
    .cari-bar .ic-sm { color: var(--muted); flex: 0 0 auto; }
    .cari-bar input {
        flex: 1 1 200px; font-family: inherit; font-size: .93rem; color: var(--text);
        background: transparent; border: none; outline: none; padding: 10px 0;
    }
    .cari-bar input::placeholder { color: var(--muted); }

    .inv-list { display: flex; flex-direction: column; gap: 10px; }
    .inv {
        display: flex; align-items: center; gap: 16px; flex-wrap: wrap; justify-content: space-between;
        border: 1px solid var(--border-2); border-radius: 14px; padding: 16px; background: var(--surface);
    }
    .inv.lunas { border-left: 3px solid var(--ok); }
    .inv.belum { border-left: 3px solid var(--alert); }

    .inv-main { flex: 1 1 260px; min-width: 0; }
    .inv-nomor { font-size: .76rem; color: var(--accent); font-weight: 700; font-variant-numeric: tabular-nums; }
    .inv-nama { font-weight: 700; font-size: 1rem; margin-top: 2px; }
    .inv-meta { margin-top: 3px; font-size: .82rem; color: var(--muted); font-variant-numeric: tabular-nums; }

    .inv-side { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .inv-total { font-size: 1.05rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    .tag { display: inline-block; padding: 5px 12px; border-radius: 999px; font-size: .72rem; font-weight: 700; }
    .tag.lunas { background: rgba(74,222,128,.12); color: var(--ok); border: 1px solid rgba(74,222,128,.28); }
    .tag.belum { background: var(--alert-soft); color: var(--alert); border: 1px solid var(--alert-line); }

    .inv-empty { text-align: center; color: var(--muted); padding: 40px 20px; }
</style>
@endpush

@section('content')
    <div class="page-head head-row">
        <div><h1>Invoice</h1></div>
        <a href="{{ route('staff.invoices.create') }}" class="btn btn-accent">
            @include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm']) Buat Invoice
        </a>
    </div>

    <form method="GET" action="{{ route('staff.invoices.index') }}" class="card cari-bar">
        @include('partials.icon', ['name' => 'search', 'class' => 'ic-sm'])
        <input type="search" name="cari" value="{{ $cari }}" autocomplete="off"
               placeholder="Cari nama tamu atau nomor invoice…">
        <button type="submit" class="btn btn-accent btn-sm">Cari</button>
        @if ($cari !== '')
            <a href="{{ route('staff.invoices.index') }}" class="btn btn-ghost btn-sm">Reset</a>
        @endif
    </form>

    <div class="inv-list">
        @forelse ($invoices as $inv)
            <div class="inv {{ $inv->lunas ? 'lunas' : 'belum' }}">
                <div class="inv-main">
                    <div class="inv-nomor">{{ $inv->nomor }}</div>
                    <div class="inv-nama">{{ $inv->nama_tamu }}</div>
                    <div class="inv-meta">
                        @php
                            $kamar = $inv->items->where('jenis', 'kamar');
                            $biaya = $inv->items->where('jenis', 'biaya');
                        @endphp
                        @if ($kamar->isNotEmpty())
                            {{ $kamar->pluck('uraian')->implode(' + ') }}
                            · {{ $kamar->max('malam') }} malam
                        @else
                            {{ $inv->tipe_kamar }} · {{ $inv->malam }} malam
                        @endif
                        @if ($biaya->isNotEmpty())
                            · +{{ $biaya->count() }} biaya
                        @endif
                        · {{ $inv->created_at->translatedFormat('j M Y, H:i') }}
                    </div>
                </div>
                <div class="inv-side">
                    <span class="tag {{ $inv->lunas ? 'lunas' : 'belum' }}">{{ $inv->lunas ? 'Lunas' : 'Belum Lunas' }}</span>
                    <span class="inv-total">{{ \App\Models\Invoice::rupiah($inv->total) }}</span>
                    <a href="{{ route('staff.invoices.show', $inv) }}" target="_blank" class="btn btn-ghost btn-sm">
                        @include('partials.icon', ['name' => 'download', 'class' => 'ic-xs']) Cetak
                    </a>
                </div>
            </div>
        @empty
            <div class="card inv-empty">
                @if ($cari !== '')
                    Tidak ada invoice yang cocok dengan &ldquo;{{ $cari }}&rdquo;.
                @else
                    Belum ada invoice. Tekan <strong>Buat Invoice</strong> untuk membuat yang pertama.
                @endif
            </div>
        @endforelse
    </div>
@endsection
