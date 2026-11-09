@extends('layouts.panel')

@section('title', 'Tamu Check-in')

@push('head')
<style>
    .ci-card { padding: 28px; max-width: 640px; }

    .ci-summary {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px;
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 12px;
        padding: 16px; margin-bottom: 24px;
    }
    .ci-summary .lbl { font-size: .7rem; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); margin-bottom: 4px; }
    .ci-summary .val { font-weight: 700; }
    .ci-summary .val.mono { font-variant-numeric: tabular-nums; }

    .ci-status {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: .78rem; font-weight: 700; border-radius: 999px; padding: 5px 12px;
    }
    .ci-status.done    { background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border); }
    .ci-status.pending { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-orange); }

    .ci-jam {
        display: flex; gap: 12px; align-items: flex-start; margin-bottom: 22px;
        border-radius: 14px; padding: 16px 18px; font-size: .9rem; line-height: 1.55;
    }
    .ci-jam .ic-lg { flex: 0 0 auto; margin-top: 1px; }
    .ci-jam b { display: block; font-size: .98rem; margin-bottom: 4px; }
    .ci-jam.awal   { background: var(--accent-2-soft); border: 1px solid var(--border-violet); color: var(--accent-2); }
    .ci-jam.normal { background: rgba(74,222,128,.10); border: 1px solid rgba(74,222,128,.28); color: var(--ok); }

    .form-actions { display: flex; gap: 12px; margin-top: 26px; padding-top: 22px; border-top: 1px solid var(--border-2); }
</style>
@endpush

@section('content')
    @php $pindahDari = $pindahDari ?? null; @endphp
    <div class="page-head">
        <h1>{{ $pindahDari ? 'Check-in Pindahan' : 'Tamu Check-in' }}</h1>
        <p>Kamar <strong>{{ $groupRooms->implode(', ') }}</strong> — {{ $booking->nama_tamu }}</p>
    </div>

    <div class="card ci-card">
        <div class="ci-summary">
            <div>
                <div class="lbl">Kamar</div>
                <div class="val mono">{{ $groupRooms->implode(', ') }}</div>
                @if ($groupRooms->count() > 1)
                    <div class="lbl" style="margin-top:4px">{{ $groupRooms->count() }} kamar · satu pesanan</div>
                @endif
            </div>
            <div>
                <div class="lbl">Tamu</div>
                <div class="val">{{ $booking->nama_tamu }}</div>
            </div>
            <div>
                <div class="lbl">Menginap</div>
                <div class="val">{{ $booking->check_in->translatedFormat('j M') }} – {{ $booking->check_out->translatedFormat('j M Y') }} ({{ $booking->durasi }} mlm)</div>
            </div>
            <div>
                <div class="lbl">Sarapan</div>
                <div class="val">{{ $booking->labelBreakfast() }}</div>
            </div>
            <div>
                <div class="lbl">Asal Pesanan</div>
                <div class="val">{{ $booking->labelSumber() }}</div>
            </div>
            <div>
                <div class="lbl">Pembayaran</div>
                <div class="val" style="color: {{ $booking->lunas() ? 'var(--ok)' : 'var(--alert)' }}">
                    {{ $booking->labelBayar() }}
                    @if (($sisaBayar ?? 0) > 0)
                        · sisa {{ \App\Models\Booking::rupiah($sisaBayar) }}
                    @endif
                </div>
            </div>
            <div>
                <div class="lbl">Status</div>
                <div>
                    @if ($booking->isCheckedIn())
                        <span class="ci-status done">@include('partials.icon', ['name' => 'check', 'class' => 'ic-xs']) Sudah Check-in {{ $booking->checked_in_at->translatedFormat('j M, H:i') }}</span>
                    @else
                        <span class="ci-status pending">Belum Check-in</span>
                    @endif
                </div>
            </div>
        </div>

        @if ($pindahDari && ! $booking->isCheckedIn())
            <div class="ci-jam awal">
                @include('partials.icon', ['name' => 'log-out', 'class' => 'ic-lg'])
                <span>
                    <b>Tamu pindah dari kamar {{ $pindahDari->room?->nomor ?? '—' }}</b>
                    Kamar {{ $pindahDari->room?->nomor ?? '—' }} otomatis ditutup dengan status "Pindah kamar"
                    saat check-in ini disimpan. Pindah kamar bukan kedatangan baru, jadi tidak ada biaya early check-in.
                    @if ($butuhBayar)
                        Masih ada sisa tagihan {{ \App\Models\Booking::rupiah($sisaBayar) }} — bukti pelunasannya wajib diunggah.
                    @endif
                </span>
            </div>
        @endif

        @unless ($booking->isCheckedIn() || $pindahDari)
            @if ($menitAwal > 0)
                @php
                    // Biaya early check-in untuk kamar yang dipilih; ikut berubah
                    // saat centangnya diubah.
                    $biayaAwal = (int) $calon->whereIn('id', $terpilih)->sum(fn ($m) => $m->biayaEarlyCheckin(now()));
                @endphp
                <div class="ci-jam awal">
                    @include('partials.icon', ['name' => 'clock', 'class' => 'ic-lg'])
                    <span>
                        <b>Early check-in<span id="ci-biaya">{{ $biayaAwal > 0 ? ' — biaya ' . \App\Models\Booking::rupiah($biayaAwal) : '' }}</span></b>
                        Sekarang {{ now()->format('H:i') }}, jam masuk {{ \App\Models\Booking::JAM_CHECKIN }}.
                        @if ($biayaAwal > 0)
                            Masuk sebelum {{ \App\Models\Booking::JAM_EARLY_BERBAYAR }} dikenai tambahan 1 malam penuh per kamar.
                            Bukti pembayarannya wajib diunggah.
                        @endif
                    </span>
                </div>
            @else
                <div class="ci-jam normal">
                    @include('partials.icon', ['name' => 'check', 'class' => 'ic-lg'])
                    <span>
                        <b>Check-in normal</b>
                        Masa sewa berakhir {{ $booking->batasCheckout()->translatedFormat('j M, H:i') }}.
                    </span>
                </div>
            @endif
        @endunless

        <form method="POST" action="{{ \App\Support\Panel::rute('rooms.checkin.store', $booking) }}" enctype="multipart/form-data">
            @csrf
            @include('partials.pilih-kamar', [
                'baris'    => $calon->map(fn ($m) => [
                    'id'    => $m->id,
                    'nomor' => $m->room?->nomor ?? '—',
                    'info'  => $m->check_in->translatedFormat('j M') . ' – ' . $m->check_out->translatedFormat('j M Y') . ' · ' . $m->durasi . ' malam',
                    'label' => 'Belum check-in',
                    'data'  => ['biaya' => $m->biayaEarlyCheckin(now())],
                ])->all(),
                'terpilih' => $terpilih,
                'judul'    => 'Pilih kamar yang tamunya sudah datang',
            ])

            @if ($butuhBayar)
                @include('partials.foto-uploader', [
                    'name'    => 'foto_pelunasan',
                    'fotoUrl' => $booking->foto_pelunasan ? route('bayar.show', $booking) : null,
                    'ikon'    => 'clipboard',
                    'judul'   => '1 — Bukti pelunasan',
                    'pesan'   => $pindahDari
                        ? 'Bukti pelunasan sisa tagihan ' . \App\Models\Booking::rupiah($sisaBayar) . '.'
                        : ($menitAwal > 0 && $booking->lunas()
                            ? 'Bukti pembayaran biaya early check-in.'
                            : 'Bukti pelunasan sisa tagihan.'),
                    'hint'    => 'Wajib.',
                ])
            @endif

            @include('partials.foto-uploader', [
                'name'    => 'foto_ktp',
                'fotoUrl' => $booking->foto_ktp ? route('ktp.show', $booking) : null,
                'ikon'    => 'id-card',
                'judul'   => $butuhBayar ? '2 — Foto KTP' : 'Foto KTP',
                'pesan'   => 'Belum ada foto KTP.',
                'hint'    => 'Wajib.',
            ])

            <div class="form-actions">
                <a href="{{ \App\Support\Panel::rute('rooms.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" id="btn-checkin" class="btn {{ $booking->isCheckedIn() ? 'btn-accent' : 'btn-orange' }}"
                        data-label="{{ ($booking->isCheckedIn() || $pindahDari) ? '' : 'Tamu Check-in' }}">
                    {{ $booking->isCheckedIn()
                        ? 'Simpan Foto Baru'
                        : ($pindahDari
                            ? 'Pindahkan Tamu ke Kamar ' . $groupRooms->implode(', ')
                            : 'Tamu Check-in' . (count($terpilih) > 1 ? ' (' . count($terpilih) . ' kamar)' : '')) }}
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    // Tombol check-in baru bisa ditekan setelah semua berkas wajib terisi.
    // Validasi server tetap penjaga terakhir; ini hanya supaya staff tidak
    // menekan tombol lalu ditolak.
    const btn = document.getElementById('btn-checkin');
    if (!btn) return;

    const wajib = Array.from(document.querySelectorAll('[data-uploader]'))
        .map(k => ({
            input: k.querySelector('[data-role="main"]'),
            sudahAda: k.classList.contains('has-file'),
        }))
        .filter(x => x.input);

    const kamar = Array.from(document.querySelectorAll('.js-kamar'));
    const biaya = document.getElementById('ci-biaya');
    const terpilih = () => kamar.filter(k => k.type === 'hidden' || k.checked);

    function periksa() {
        const pilih = terpilih();
        const lengkap = pilih.length > 0 && wajib.every(x => x.sudahAda || (x.input.files && x.input.files.length > 0));

        if (btn.dataset.label) btn.textContent = btn.dataset.label + (pilih.length > 1 ? ' (' + pilih.length + ' kamar)' : '');
        if (biaya) {
            const n = pilih.reduce((s, k) => s + (parseInt(k.dataset.biaya, 10) || 0), 0);
            biaya.textContent = n > 0 ? ' — biaya Rp ' + n.toLocaleString('id-ID') : '';
        }

        btn.disabled = !lengkap;
        btn.style.opacity = lengkap ? '' : '.45';
        btn.style.cursor  = lengkap ? '' : 'not-allowed';
        btn.title = lengkap ? '' : (pilih.length ? 'Lengkapi foto yang diminta terlebih dahulu' : 'Pilih minimal satu kamar');
    }

    wajib.forEach(x => x.input.addEventListener('change', periksa));
    kamar.forEach(k => k.addEventListener('change', periksa));
    periksa();
})();
</script>
@endpush
