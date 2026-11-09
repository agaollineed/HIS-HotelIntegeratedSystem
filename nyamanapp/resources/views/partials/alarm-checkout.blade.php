{{-- Pengingat check-out: muncul di Beranda dan halaman Kamar (semua peran).
     Bahan dari App\Support\AlarmCheckout::kumpulkan(). Ditampilkan di semua
     tanggal, bukan hanya tanggal terpilih, supaya tidak terlewat hanya
     karena yang dilihat sedang bulan lain. --}}
@php
    $alarm ??= ['segera' => collect(), 'lewat' => collect(), 'diperpanjang' => collect(), 'jumlah' => 0];
    $R = fn ($b) => $b->room?->nomor;
    $dipesan = function ($grup) {
        $p = $grup->first(fn ($b) => $b->pemesan_berikut ?? null)?->pemesan_berikut;
        return $p ? 'Kamar ' . $grup->filter(fn ($b) => $b->pemesan_berikut ?? null)->map(fn ($b) => $b->room?->nomor)->implode(', ')
            . ' sudah dipesan ' . $p->nama_tamu . ' (masuk ' . $p->check_in->translatedFormat('j M') . ')' : null;
    };
@endphp

@if ($alarm['jumlah'] > 0)
<div class="alarm-co" id="alarm-co" role="alert">
    <div class="alarm-head">
        @include('partials.icon', ['name' => 'clock', 'class' => 'ic-sm'])
        Pengingat check-out
        <span class="alarm-jml">{{ $alarm['jumlah'] }}</span>
        <button type="button" class="x" id="alarm-tutup" title="Tutup">
            @include('partials.icon', ['name' => 'x', 'class' => 'ic-sm'])
        </button>
    </div>

    <div class="alarm-isi">
        @if ($alarm['segera']->isNotEmpty())
            <div class="alarm-judul">Segera check-out · batas {{ \App\Models\Booking::JAM_CHECKOUT }}</div>
            @foreach ($alarm['segera'] as $grup)
                @php($u = $grup->first())
                <div class="alarm-item {{ $dipesan($grup) ? 'genting' : '' }}">
                    <div class="alarm-room">{{ $grup->map($R)->filter()->implode(', ') }} · {{ $u->nama_tamu }}</div>
                    <div class="alarm-meta">
                        Sisa waktu {{ \App\Models\Booking::labelDurasi((int) now()->diffInMinutes($u->batasCheckout())) }}.
                    </div>
                    @if ($dipesan($grup))
                        <div class="alarm-bentrok">@include('partials.icon', ['name' => 'alert', 'class' => 'ic-xs']) {{ $dipesan($grup) }} — tidak bisa extend, tamu harus keluar tepat waktu.</div>
                    @endif
                    <div class="alarm-aksi">
                        <a href="{{ \App\Support\Panel::rute('rooms.checkout', ['booking' => $u, 'pilih' => $grup->pluck('id')->implode(',')]) }}" class="btn btn-accent btn-sm">Check-out</a>
                    </div>
                </div>
            @endforeach
        @endif

        @if ($alarm['lewat']->isNotEmpty())
            <div class="alarm-judul">Lewat jam check-out</div>
            @foreach ($alarm['lewat'] as $grup)
                @php($u = $grup->first())
                @php($over = $u->overLatePada(now()))
                <div class="alarm-item {{ $dipesan($grup) ? 'genting' : '' }}">
                    <div class="alarm-room">{{ $grup->map($R)->filter()->implode(', ') }} · {{ $u->nama_tamu }}</div>
                    <div class="alarm-meta">
                        Telat {{ \App\Models\Booking::labelDurasi((int) $u->batasCheckout()->diffInMinutes(now())) }}.
                        @unless ($dipesan($grup))
                            Lewat {{ \App\Models\Booking::JAM_LATE_SETENGAH }} otomatis dihitung extend 1 malam.
                        @endunless
                    </div>
                    @if ($dipesan($grup))
                        <div class="alarm-bentrok">
                            @include('partials.icon', ['name' => 'alert', 'class' => 'ic-xs'])
                            {{ $dipesan($grup) }} —
                            {{ $over ? 'tidak bisa diperpanjang otomatis.' : 'tidak bisa extend.' }}
                            Check-out-kan tamu ini atau pindahkan salah satunya.
                        </div>
                    @endif
                    <div class="alarm-aksi">
                        @if ($u->lateDitandai())
                            <span class="alarm-ditandai">@include('partials.icon', ['name' => 'check', 'class' => 'ic-xs']) Ditandai late</span>
                        @else
                            <form method="POST" action="{{ \App\Support\Panel::rute('rooms.late', $u) }}">
                                @csrf
                                <button type="submit" class="btn btn-danger btn-sm">Tandai Late Check-out</button>
                            </form>
                        @endif
                        <a href="{{ \App\Support\Panel::rute('rooms.checkout', ['booking' => $u, 'pilih' => $grup->pluck('id')->implode(',')]) }}" class="btn btn-accent btn-sm">Check-out</a>
                        @if ($dipesan($grup))
                            <a href="{{ \App\Support\Panel::rute('rooms.pindah', $u) }}" class="btn btn-ghost btn-sm">Pindah Kamar</a>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif

        @if ($alarm['diperpanjang']->isNotEmpty())
            <div class="alarm-judul">Extend otomatis hari ini</div>
            @foreach ($alarm['diperpanjang'] as $grup)
                @php($u = $grup->first())
                <div class="alarm-item info">
                    <div class="alarm-room">{{ $grup->map($R)->filter()->implode(', ') }} · {{ $u->nama_tamu }}</div>
                    <div class="alarm-meta">
                        Masih di kamar lewat {{ \App\Models\Booking::JAM_LATE_SETENGAH }} — ditambah 1 malam,
                        check-out baru {{ $u->check_out->translatedFormat('j M') }} pukul {{ \App\Models\Booking::JAM_CHECKOUT }}.
                        Selisihnya ditagih saat check-out.
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>

@once
@push('head')
<style>
    .alarm-co {
        position: fixed; right: 22px; bottom: 22px; z-index: 40; width: min(390px, calc(100vw - 44px));
        max-height: min(72vh, 640px); display: flex; flex-direction: column;
        background: linear-gradient(180deg, var(--surface-2), var(--bg-2));
        border: 1px solid var(--alert-line); border-radius: 16px; padding: 14px 16px 16px;
        box-shadow: 0 30px 70px -28px rgba(0,0,0,.95);
    }
    .alarm-co[hidden] { display: none !important; }
    .alarm-head { display: flex; align-items: center; gap: 9px; color: var(--alert); font-weight: 800; font-size: .9rem; margin-bottom: 8px; }
    .alarm-jml { background: var(--alert-soft); border: 1px solid var(--alert-line); border-radius: 999px; padding: 0 8px; font-size: .74rem; }
    .alarm-head .x { margin-left: auto; background: none; border: none; cursor: pointer; color: var(--muted); display: inline-flex; padding: 2px; }
    .alarm-head .x:hover { color: var(--text); }
    .alarm-isi { overflow-y: auto; margin-right: -6px; padding-right: 6px; }
    .alarm-judul { font-size: .68rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); margin: 12px 0 6px; }
    .alarm-judul:first-child { margin-top: 0; }
    .alarm-item { border: 1px solid var(--border-2); border-radius: 12px; padding: 10px 12px; margin-bottom: 8px; background: rgba(255,255,255,.02); }
    .alarm-item.genting { border-color: var(--alert-line); background: var(--alert-soft); }
    .alarm-item.info { border-color: var(--border); background: var(--accent-soft); }
    .alarm-room { font-weight: 700; font-size: .88rem; font-variant-numeric: tabular-nums; }
    .alarm-meta { font-size: .78rem; color: var(--muted); margin-top: 3px; line-height: 1.45; }
    .alarm-bentrok { font-size: .78rem; color: var(--alert); font-weight: 700; margin-top: 6px; line-height: 1.45; }
    .alarm-bentrok .ic-xs { vertical-align: -2px; }
    .alarm-aksi { display: flex; gap: 8px; margin-top: 9px; flex-wrap: wrap; }
    .alarm-aksi form { margin: 0; }
    .alarm-ditandai { font-size: .74rem; color: var(--alert); font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
    @media (max-width: 560px) { .alarm-co { right: 12px; left: 12px; bottom: 12px; width: auto; } }
</style>
@endpush
@push('scripts')
<script>
    (function () {
        const t = document.getElementById('alarm-co');
        const x = document.getElementById('alarm-tutup');
        if (t && x) x.addEventListener('click', () => { t.hidden = true; });
    })();
</script>
@endpush
@endonce
@endif
