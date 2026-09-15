@extends('layouts.panel')

@section('title', 'Detail Tamu')

@push('head')
<style>
    .kembali { display: inline-flex; align-items: center; gap: 7px; color: var(--muted); text-decoration: none; font-size: .85rem; font-weight: 600; margin-bottom: 16px; }
    .kembali:hover { color: var(--text); }

    .profil-card { padding: 24px; margin-bottom: 22px; display: flex; align-items: center; gap: 22px; flex-wrap: wrap; }
    .profil-nama { flex: 1 1 220px; }
    .profil-nama h2 { margin: 0; font-size: 1.3rem; }
    .profil-nama .tel { color: var(--muted); font-variant-numeric: tabular-nums; margin-top: 4px; }
    .profil-stats { display: flex; gap: 26px; flex-wrap: wrap; }
    .ps { text-align: center; }
    .ps .n { font-weight: 800; font-size: 1.45rem; color: var(--accent); font-variant-numeric: tabular-nums; }
    .ps .l { font-size: .68rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-top: 3px; }

    .stay-list { display: flex; flex-direction: column; gap: 12px; }
    .stay { border: 1px solid var(--border-2); border-radius: 14px; background: var(--surface); padding: 18px; }
    .stay-atas { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 10px; }
    .stay-tgl { font-weight: 700; font-variant-numeric: tabular-nums; flex: 1 1 auto; }
    .stay-tgl span { color: var(--muted); font-weight: 500; }

    .st-badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: .7rem; font-weight: 700; }
    .st-done  { background: rgba(74,222,128,.12); color: var(--ok); border: 1px solid rgba(74,222,128,.28); }
    .st-out   { background: var(--hover); color: var(--muted); border: 1px solid var(--border-2); }
    .st-pending { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-orange); }
    .st-telat { background: var(--alert-soft); color: var(--alert); border: 1px solid var(--alert-line); }
    .st-awal  { background: var(--alert-soft); color: var(--alert); border: 1px solid var(--alert-line); }

    .stay-meta { font-size: .84rem; color: var(--muted); display: flex; gap: 8px 18px; flex-wrap: wrap; }
    .stay-meta b { color: var(--text); font-variant-numeric: tabular-nums; }
    .stay-rooms { display: flex; gap: 6px; flex-wrap: wrap; margin: 10px 0; }
    .stay-room {
        border: 1px solid var(--border); background: var(--accent-soft); color: var(--accent);
        border-radius: 8px; padding: 4px 10px; font-weight: 700; font-size: .8rem; font-variant-numeric: tabular-nums;
    }
    .stay-kaki { margin-top: 8px; }
</style>
@endpush

@section('content')
    <a href="{{ route('admin.tamu.index') }}" class="kembali">
        @include('partials.icon', ['name' => 'chevron-left', 'class' => 'ic-xs']) Semua Tamu
    </a>

    <div class="card profil-card">
        <div class="profil-nama">
            <h2>{{ $nama }}</h2>
            <div class="tel">
                @if ($telepon->isEmpty())
                    —
                @else
                    {{ $telepon->implode(' · ') }}
                    @if ($telepon->count() > 1)
                        <span style="color: var(--muted); font-size: .78rem; display: block; margin-top: 3px;">
                            {{ $telepon->count() }} nomor berbeda pernah dipakai atas nama ini
                        </span>
                    @endif
                @endif
            </div>
        </div>
        <div class="profil-stats">
            <div class="ps"><div class="n">{{ $ringkas['kunjungan'] }}</div><div class="l">Kunjungan</div></div>
            <div class="ps"><div class="n">{{ $ringkas['kamar'] }}</div><div class="l">Kamar</div></div>
            <div class="ps"><div class="n">{{ $ringkas['malam'] }}</div><div class="l">Malam</div></div>
        </div>
    </div>

    <div class="stay-list">
        @foreach ($stays as $grup)
            @php $u = $grup->first(); @endphp
            <div class="stay">
                <div class="stay-atas">
                    <div class="stay-tgl">
                        {{ $u->check_in->translatedFormat('j M Y') }}
                        <span>&rarr;</span>
                        {{ $u->check_out->translatedFormat('j M Y') }}
                        <span>· {{ $u->durasi }} malam</span>
                    </div>
                    @if ($u->isCheckedOut())
                        <span class="st-badge {{ $u->checkoutTelat() ? 'st-telat' : ($u->checkoutAwal() ? 'st-awal' : 'st-out') }}">{{ $u->labelStatusCheckout() }}</span>
                    @elseif ($u->isCheckedIn())
                        <span class="st-badge st-done">Sedang Menginap</span>
                    @elseif ($u->check_in->isFuture())
                        <span class="st-badge st-pending">Akan Datang</span>
                    @else
                        <span class="st-badge st-pending">Tidak Check-in</span>
                    @endif
                </div>

                <div class="stay-rooms">
                    @foreach ($grup as $b)
                        <span class="stay-room">{{ $b->room?->nomor ?? '—' }}</span>
                    @endforeach
                </div>

                <div class="stay-meta">
                    <span>Nama saat itu: <b>{{ $u->nama_tamu }}</b></span>
                    <span>Asal: <b>{{ $u->labelSumber() }}</b></span>
                    <span>Bayar: <b>{{ $u->labelBayar() }}</b></span>
                    <span>Sarapan: <b>{{ $u->breakfast ? 'Ya' : 'Tidak' }}</b></span>
                    @if ($u->creator)
                        <span>Diinput: <b>{{ $u->creator->name }}</b></span>
                    @endif
                </div>

                <div class="stay-kaki">
                    <a href="{{ route('admin.rooms.index', ['tanggal' => $u->check_in->toDateString(), 'bulan' => $u->check_in->format('Y-m')]) }}"
                       class="btn btn-ghost btn-sm">Lihat di Kalender</a>
                </div>
            </div>
        @endforeach
    </div>
@endsection
