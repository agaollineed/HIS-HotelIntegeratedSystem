@extends('layouts.panel')

@section('title', 'Riwayat Tamu')

@push('head')
<style>
    .cari-bar { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; padding: 4px 14px; margin-bottom: 18px; }
    .cari-bar .ic-sm { color: var(--muted); flex: 0 0 auto; }
    .cari-bar input {
        flex: 1 1 200px; font-family: inherit; font-size: .93rem; color: var(--text);
        background: transparent; border: none; outline: none; padding: 10px 0;
    }
    .cari-bar input::placeholder { color: var(--muted); }

    .tm-list { display: flex; flex-direction: column; gap: 10px; }
    .tm {
        display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
        border: 1px solid var(--border-2); border-radius: 14px; background: var(--surface);
        padding: 16px; text-decoration: none; color: var(--text); transition: border-color .15s, background .15s;
    }
    .tm:hover { border-color: var(--accent); background: var(--hover); }
    .tm.langganan { border-left: 3px solid var(--accent); }

    .tm-info { flex: 1 1 220px; min-width: 0; }
    .tm-nama { font-weight: 700; font-size: 1rem; display: flex; gap: 9px; align-items: center; flex-wrap: wrap; }
    .tm-tel { font-size: .82rem; color: var(--muted); margin-top: 2px; font-variant-numeric: tabular-nums; }

    .badge-ulang {
        display: inline-block; padding: 3px 11px; border-radius: 999px; font-size: .68rem; font-weight: 700;
        background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border);
    }

    .tm-stats { display: flex; gap: 22px; flex-wrap: wrap; }
    .tm-stat { text-align: center; }
    .tm-stat .n { font-weight: 800; font-size: 1.08rem; font-variant-numeric: tabular-nums; color: var(--accent); }
    .tm-stat .l { font-size: .68rem; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); margin-top: 2px; }
    .tm-terakhir { font-size: .8rem; color: var(--muted); font-variant-numeric: tabular-nums; }

    .tm-empty { text-align: center; color: var(--muted); padding: 40px 20px; }
</style>
@endpush

@section('content')
    <div class="page-head">
        <h1>Riwayat Tamu</h1>
        <p>Profil dikelompokkan berdasarkan <strong>nama</strong> (ejaan, gelar, dan huruf besar-kecil disamakan);
        satu pesanan banyak kamar dihitung satu kunjungan.</p>
    </div>

    <form method="GET" action="{{ route('admin.tamu.index') }}" class="card cari-bar">
        @include('partials.icon', ['name' => 'search', 'class' => 'ic-sm'])
        <input type="search" name="cari" value="{{ $cari }}" autocomplete="off"
               placeholder="Cari nama atau nomor telepon…">
        <button type="submit" class="btn btn-accent btn-sm">Cari</button>
        @if ($cari !== '')
            <a href="{{ route('admin.tamu.index') }}" class="btn btn-ghost btn-sm">Reset</a>
        @endif
    </form>

    <div class="tm-list">
        @forelse ($profil as $p)
            <a href="{{ route('admin.tamu.show', ['nama' => $p->nama]) }}"
               class="tm {{ $p->kunjungan > 1 ? 'langganan' : '' }}">
                <div class="tm-info">
                    <div class="tm-nama">
                        {{ $p->nama }}
                        @if ($p->kunjungan > 1)
                            <span class="badge-ulang">{{ $p->kunjungan }} kali menginap</span>
                        @endif
                    </div>
                    <div class="tm-tel">{{ $p->telepon }}</div>
                </div>
                <div class="tm-stats">
                    <div class="tm-stat"><div class="n">{{ $p->kunjungan }}</div><div class="l">Kunjungan</div></div>
                    <div class="tm-stat"><div class="n">{{ $p->kamar }}</div><div class="l">Kamar</div></div>
                    <div class="tm-stat"><div class="n">{{ $p->malam }}</div><div class="l">Malam</div></div>
                </div>
                <div class="tm-terakhir">
                    Terakhir: {{ \Illuminate\Support\Carbon::parse($p->terakhir)->translatedFormat('j M Y') }}
                </div>
            </a>
        @empty
            <div class="card tm-empty">
                @if ($cari !== '')
                    Tidak ada tamu yang cocok dengan &ldquo;{{ $cari }}&rdquo;.
                @else
                    Belum ada riwayat tamu — profil terbentuk otomatis begitu ada booking.
                @endif
            </div>
        @endforelse
    </div>
@endsection
