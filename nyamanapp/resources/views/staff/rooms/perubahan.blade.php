@extends('layouts.panel')

@section('title', 'Ajukan Perubahan Booking')

@push('head')
<style>
    /* Mengikuti pola halaman check-in & formulir booking: kartu berpadding,
       ringkasan grid, dan pilihan bergaya .bf-opsi. */
    .pb-card { padding: 28px; max-width: 720px; }

    .pb-summary {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px;
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 12px;
        padding: 16px; margin-bottom: 20px;
    }
    .pb-summary .lbl { font-size: .7rem; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); margin-bottom: 4px; }
    .pb-summary .val { font-weight: 700; }
    .pb-summary .val.mono { font-variant-numeric: tabular-nums; }

    .note-grup {
        display: flex; gap: 10px; align-items: flex-start;
        background: var(--hover); border: 1px solid var(--border);
        border-radius: 12px; padding: 12px 14px; font-size: .85rem; color: var(--muted);
        margin-bottom: 24px; line-height: 1.6;
    }
    .note-grup b { color: var(--text); }

    /* ---------- Kolom isian (sama dengan formulir booking) ---------- */
    .fld { margin-bottom: 20px; }
    .fld > label { display: block; font-size: .75rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
    .fld input[type=text], .fld input[type=tel], .fld input[type=date],
    .fld input[type=number], .fld select, .fld textarea {
        width: 100%; font-family: inherit; font-size: 1rem; color: var(--text);
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; padding: 12px 14px;
    }
    .fld textarea { resize: vertical; min-height: 88px; line-height: 1.6; }
    .fld input:focus, .fld select:focus, .fld textarea:focus { outline: none; border-color: var(--accent); }
    .fld input[type=date] { color-scheme: dark; }
    .fld select option:disabled { color: var(--muted); }
    .fld .hint { margin-top: 6px; font-size: .78rem; color: var(--muted); }

    /* ---------- Pilihan jenis: pola .bf-opsi ---------- */
    .bf-grup { display: flex; gap: 10px; flex-wrap: wrap; }
    .bf-opsi {
        flex: 1 1 200px; display: flex; align-items: center; gap: 11px; cursor: pointer;
        border: 1px solid var(--border-2); border-radius: 12px; padding: 13px 15px;
        background: var(--surface); transition: border-color .15s, background .15s;
    }
    .bf-opsi:hover { border-color: var(--accent); background: var(--hover); }
    .bf-opsi.aktif { border-color: var(--accent); background: var(--accent-soft); }
    .bf-opsi input { width: 17px; height: 17px; accent-color: var(--accent); cursor: pointer; flex: 0 0 auto; }
    .bf-opsi .t { font-weight: 700; font-size: .92rem; }
    .bf-opsi .d { display: block; font-size: .75rem; color: var(--muted); font-weight: 500; margin-top: 2px; }

    .blok { margin-top: 22px; }
    .blok[hidden] { display: none; }

    /* ---------- Riwayat pengajuan ---------- */
    .riwayat { margin-top: 28px; padding-top: 22px; border-top: 1px solid var(--border-2); }
    .riwayat h3 {
        font-size: .72rem; letter-spacing: .09em; text-transform: uppercase;
        color: var(--muted); margin: 0 0 12px; font-weight: 700;
    }
    .rw {
        display: flex; gap: 12px; align-items: flex-start; justify-content: space-between;
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 12px;
        padding: 12px 14px; margin-bottom: 8px; font-size: .85rem;
    }
    .rw .apa { font-weight: 700; }
    .rw .kapan { color: var(--muted); font-size: .78rem; margin-top: 3px; line-height: 1.5; }
    .st-badge {
        display: inline-flex; align-items: center; border-radius: 999px; padding: 4px 11px;
        font-size: .7rem; font-weight: 700; white-space: nowrap;
    }
    .st-badge.menunggu  { background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border); }
    .st-badge.disetujui { background: rgba(74,222,128,.10); color: var(--ok); border: 1px solid rgba(74,222,128,.28); }
    .st-badge.ditolak   { background: var(--alert-soft); color: var(--alert); border: 1px solid var(--alert-line); }

    .form-actions { display: flex; gap: 12px; margin-top: 26px; padding-top: 22px; border-top: 1px solid var(--border-2); }
</style>
@endpush

@section('content')
<div class="page-head">
    <h1>Ajukan Perubahan</h1>
    <p>Booking belum berubah sampai admin menyetujui.</p>
</div>

<div class="card pb-card">
    <div class="pb-summary">
        <div>
            <div class="lbl">Kamar</div>
            <div class="val mono">{{ $booking->room?->nomor ?? '—' }}</div>
        </div>
        <div>
            <div class="lbl">Tamu</div>
            <div class="val">{{ $booking->nama_tamu }}</div>
        </div>
        <div>
            <div class="lbl">Menginap</div>
            <div class="val mono">
                {{ $booking->check_in->translatedFormat('j M') }} –
                {{ $booking->check_out->translatedFormat('j M Y') }}
                ({{ $booking->durasi }} mlm)
            </div>
        </div>
    </div>

    <div class="note-grup">
        @include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-sm'])
        <span>
            Satu pengajuan = satu jenis perubahan, diputus admin satu per satu.
            <b>Pindah kamar</b> tidak perlu diajukan —
            <a href="{{ route('staff.rooms.pindah', $booking) }}">kerjakan langsung di sini</a>.
        </span>
    </div>

    <form method="POST" action="{{ route('staff.rooms.perubahan.store', $booking) }}">
        @csrf

        <div class="fld">
            <label>Jenis Perubahan</label>
            <div class="bf-grup" id="jenis-grid">
                @foreach ($jenisList as $nilai => $label)
                    <label class="bf-opsi {{ old('jenis') === $nilai ? 'aktif' : '' }}">
                        <input type="radio" name="jenis" value="{{ $nilai }}" @checked(old('jenis') === $nilai)>
                        <span class="t">
                            {{ $label }}
                            <span class="d">
                                @switch($nilai)
                                    @case('pindah_kamar') Kamar lain, tanggal tetap @break
                                    @case('perpanjang') Tambah / kurangi malam @break
                                    @case('ubah_tanggal') Geser tanggal masuk @break
                                    @default Perbaiki data tamu
                                @endswitch
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Hanya blok milik jenis terpilih yang terlihat dan terkirim. --}}
        @if (isset($jenisList[\App\Models\BookingRequest::PINDAH_KAMAR]))
        <div class="blok" id="blok-pindah_kamar" hidden>
            <div class="fld">
                <label for="pindah_mulai">Pindah Mulai Malam</label>
                <select id="pindah_mulai" name="pindah_mulai">
                    @foreach ($tanggalPindah ?? [] as $i => $t)
                        @php $sisaMalam = (int) $t->diffInDays($booking->check_out->copy()->startOfDay()); @endphp
                        <option value="{{ $t->toDateString() }}" data-malam="{{ $sisaMalam }}"
                                @selected(old('pindah_mulai') === $t->toDateString())>
                            {{ $t->translatedFormat('l, j M Y') }} —
                            {{ $i === 0 ? 'seluruh masa menginap' : 'malam ke-' . ($i + 1) . ', ' . $sisaMalam . ' malam di kamar baru' }}
                        </option>
                    @endforeach
                </select>
                <div class="hint">
                    Contoh: tamu memesan 2 malam di kamar ini tapi ingin pindah mulai malam kedua — pilih tanggal malam kedua.
                    Malam sebelumnya tetap di kamar {{ $booking->room?->nomor ?? '—' }} dengan tarifnya sendiri,
                    dan tagihan pesanan menyesuaikan tarif kamar baru.
                </div>
            </div>

            <div class="fld">
                <label for="room_id">Kamar Tujuan</label>
                <select id="room_id" name="room_id">
                    <option value="">— pilih kamar —</option>
                    @foreach ($roomsByTipe as $tipe => $rooms)
                        <optgroup label="{{ ucfirst($tipe) }}">
                            @foreach ($rooms as $room)
                                @php
                                    $sekarang = $room->id === $booking->room_id;
                                    $nama     = array_values((array) (($penghuni ?? collect())->get($room->id, [])));
                                    $buntu    = count($nama) > 1;
                                @endphp
                                <option value="{{ $room->id }}"
                                        data-nomor="{{ $room->nomor }} ({{ $room->labelTipe() }})"
                                        @selected((int) old('room_id') === $room->id)
                                        @disabled($sekarang || $buntu)>
                                    {{ $room->nomor }} ({{ $room->labelTipe() }})
                                    {{ $sekarang ? '— kamar sekarang' : (count($nama) === 1 ? '— terisi: ' . $nama[0] . ' (akan ditukar)' : ($buntu ? '— terisi ' . count($nama) . ' tamu (tidak bisa ditukar)' : '')) }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <div class="hint" id="hint-pindah">
                    Pindah seluruh masa menginap: kamar yang terisi satu tamu tetap bisa dipilih, penghuninya otomatis
                    ditukar ke kamar {{ $booking->room?->nomor ?? '—' }}. Pindah mulai malam tertentu: kamar tujuan harus kosong.
                    Ketersediaan diperiksa ulang saat admin menyetujui.
                </div>
            </div>
        </div>

        @endif

        <div class="blok" id="blok-perpanjang" hidden>
            <div class="fld">
                <label for="durasi">Lama Menginap Baru (malam)</label>
                <input id="durasi" type="number" name="durasi" min="1" max="30"
                       value="{{ old('durasi', $booking->durasi) }}">
                <div class="hint">Sekarang {{ $booking->durasi }} malam, keluar {{ $booking->check_out->translatedFormat('j M Y') }}.</div>
            </div>
        </div>

        <div class="blok" id="blok-ubah_tanggal" hidden>
            <div class="fld">
                <label for="check_in">Tanggal Check-in Baru</label>
                <input id="check_in" type="date" name="check_in"
                       value="{{ old('check_in', $booking->check_in->toDateString()) }}">
                <div class="hint">Lama menginap tetap {{ $booking->durasi }} malam; tanggal keluar ikut bergeser.</div>
            </div>
        </div>

        <div class="blok" id="blok-ubah_tamu" hidden>
            <div class="fld">
                <label for="nama_tamu">Nama Tamu</label>
                <input id="nama_tamu" type="text" name="nama_tamu" maxlength="120"
                       value="{{ old('nama_tamu', $booking->nama_tamu) }}">
            </div>
            <div class="fld">
                <label for="telepon">Telepon</label>
                <input id="telepon" type="text" name="telepon" maxlength="30"
                       value="{{ old('telepon', $booking->telepon) }}">
            </div>
        </div>

        <div class="fld" style="margin-top: 20px">
            <label for="alasan">Alasan Perubahan</label>
            <textarea id="alasan" name="alasan" rows="3" maxlength="500"
                      placeholder="Mis. tamu minta pindah karena AC kamar 103 kurang dingin">{{ old('alasan') }}</textarea>
            <div class="hint">Wajib diisi — inilah yang dibaca admin saat memutuskan.</div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-accent">
                @include('partials.icon', ['name' => 'check', 'class' => 'ic-sm']) Kirim Pengajuan
            </button>
            <a href="{{ route('staff.rooms.index', ['tanggal' => $booking->check_in->toDateString()]) }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>

    @if ($riwayat->isNotEmpty())
        <div class="riwayat">
            <h3>Pengajuan Sebelumnya</h3>
            @foreach ($riwayat as $r)
                <div class="rw">
                    <div>
                        <div class="apa">{{ $r->labelJenis() }}</div>
                        <div class="kapan">
                            {{ $r->created_at->translatedFormat('j M Y, H:i') }}
                            oleh {{ $r->pengaju?->name ?? '—' }}
                            @if ($r->catatan_admin)
                                — “{{ $r->catatan_admin }}”
                            @endif
                        </div>
                    </div>
                    <span class="st-badge {{ $r->status }}">{{ $r->labelStatus() }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
// Ketersediaan kamar tujuan mengikuti tanggal mulai pindah.
(function () {
    const mulai = document.getElementById('pindah_mulai');
    const kamar = document.getElementById('room_id');
    if (!mulai || !kamar) return;

    const AVAIL_URL  = @json(route('staff.rooms.available'));
    const BOOKING_ID = @json($booking->id);
    const ROOM_ID    = @json($booking->room_id);
    const AWAL       = @json($booking->check_in->toDateString());

    function muat() {
        const op = mulai.selectedOptions[0];
        if (!op) return;
        const url = new URL(AVAIL_URL, window.location.origin);
        url.searchParams.set('check_in', mulai.value);
        url.searchParams.set('durasi', op.dataset.malam || 1);
        url.searchParams.set('ignore', BOOKING_ID);

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(function (data) {
                const terisi   = new Set((data.occupied || []).map(Number));
                const penghuni = data.penghuni || {};
                const penuh    = mulai.value === AWAL;

                kamar.querySelectorAll('option').forEach(function (o) {
                    if (!o.value || parseInt(o.value, 10) === ROOM_ID) return;
                    const id   = parseInt(o.value, 10);
                    const nama = penghuni[id] || [];
                    const isi  = terisi.has(id);
                    const tukar = isi && penuh && nama.length === 1;
                    o.disabled = isi && !tukar;
                    o.textContent = o.dataset.nomor + (
                        !isi ? '' :
                        tukar ? ' — terisi: ' + nama[0] + ' (akan ditukar)' :
                        ' — terisi' + (nama.length ? ': ' + nama.join(', ') : '')
                    );
                });
                const dipilih = kamar.selectedOptions[0];
                if (dipilih && dipilih.value && dipilih.disabled) kamar.value = '';
            })
            .catch(function () {});
    }

    mulai.addEventListener('change', muat);
    muat();
})();
</script>
<script>
    (function () {
        const grid  = document.getElementById('jenis-grid');
        const blok  = {};

        @foreach ($jenisList as $nilai => $label)
            blok['{{ $nilai }}'] = document.getElementById('blok-{{ $nilai }}');
        @endforeach

        function terapkan() {
            const dipilih = grid.querySelector('input[name="jenis"]:checked');
            const nilai   = dipilih ? dipilih.value : null;

            grid.querySelectorAll('.bf-opsi').forEach(function (lb) {
                lb.classList.toggle('aktif', lb.querySelector('input').checked);
            });

            // Kolom di blok yang tersembunyi dinonaktifkan supaya TIDAK ikut
            // terkirim — satu pengajuan hanya boleh membawa satu perubahan,
            // dan itu harus benar sampai ke isi permintaan HTTP-nya.
            Object.keys(blok).forEach(function (k) {
                const b = blok[k];
                if (!b) return;

                const aktif = k === nilai;
                b.hidden = !aktif;
                b.querySelectorAll('input, select, textarea').forEach(function (el) {
                    el.disabled = !aktif;
                });
            });
        }

        grid.addEventListener('change', terapkan);
        terapkan();
    })();
</script>
@endpush
