@php
    /** @var \App\Models\Booking|null $booking */
    $booking    = $booking ?? null;
    $isEdit     = $booking !== null;
    $groupCount = $groupCount ?? 1;
    $tipeLabels = ['king' => 'King', 'twin' => 'Twin', 'family' => 'Family'];
    $checkInVal = (string) old('check_in', $booking?->check_in?->toDateString() ?? now()->toDateString());
    $checkOutVal = (string) old('check_out', $booking?->check_out?->toDateString()
        ?? \Illuminate\Support\Carbon::parse($checkInVal)->addDay()->toDateString());
    $sumberVal  = (string) old('sumber', $booking->sumber ?? \App\Models\Booking::SUMBER_UMUM);
    $bayarVal   = (string) old('status_bayar', $booking->status_bayar ?? \App\Models\Booking::BAYAR_BELUM);
    $dpVal      = (int) old('dp_nominal', $booking?->dp_nominal ?? 0);
    $adaBuktiBayar = $isEdit && $booking->foto_bayar_awal !== null;
    $occupied   = collect($occupiedIds ?? []);
    // Tanggal boleh dimundurkan: tamu yang datang dini hari memakai kamar
    // untuk malam KEMARIN, dan check-out siang ini.
    $minDate    = now()->subDays(\App\Http\Controllers\Staff\BookingController::MUNDUR_MAKS_HARI)->toDateString();
    $kemarin    = now()->subDay()->toDateString();
    $hariIniStr = now()->toDateString();
    $diskonVal       = (int) old('diskon_nominal', $booking?->diskon_nominal ?? 0);
    $diskonPersenVal = (int) old('diskon_persen', $booking?->diskon_persen ?? 0);
    $diskonModeVal   = (string) old('diskon_mode', $diskonPersenVal > 0 ? 'persen' : 'rupiah');
    $hargaLain  = (int) ($hargaLain ?? 0);

    // Kamar tercentang (tambah) / kamar terpilih (ubah).
    $checkedIds = $isEdit
        ? [(int) old('room_id', $booking->room_id)]
        : array_map('intval', (array) old('room_ids', []));

    try {
        $malamAwal = \Illuminate\Support\Carbon::parse($checkInVal)->startOfDay()
            ->diffInDays(\Illuminate\Support\Carbon::parse($checkOutVal)->startOfDay());
        $malamAwal = max(1, (int) $malamAwal);
    } catch (\Throwable $e) {
        $malamAwal = 1;
    }
@endphp

@push('head')
<style>
    .form-card { padding: 28px; max-width: 680px; }
    .fld { margin-bottom: 20px; }
    .fld > label { display: block; font-size: .75rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
    .fld input[type=text], .fld input[type=tel], .fld input[type=date], .fld select {
        width: 100%; font-family: inherit; font-size: 1rem; color: var(--text);
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; padding: 12px 14px;
    }
    .fld input:focus, .fld select:focus { outline: none; border-color: var(--accent); }
    .fld input[type=date] { color-scheme: dark; }
    .fld select option:disabled { color: var(--muted); }
    .fld .hint { margin-top: 6px; font-size: .78rem; color: var(--muted); }

    .grid-2 { display: grid; grid-template-columns: 1fr auto; gap: 16px; align-items: end; }

    /* ---------- Daftar checkbox kamar (per tipe) ---------- */
    .rooms-box { border: 1px solid var(--border-2); border-radius: 14px; background: var(--bg); padding: 6px 14px 14px; }
    .rooms-grp { padding-top: 12px; }
    .rooms-grp + .rooms-grp { border-top: 1px dashed var(--border-2); margin-top: 12px; }
    .rooms-grp .ttl {
        display: flex; align-items: center; gap: 8px;
        font-size: .72rem; letter-spacing: .07em; text-transform: uppercase; color: var(--muted);
        font-weight: 700; margin-bottom: 10px;
    }
    .rooms-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: 8px; }
    .rm {
        position: relative; display: flex; align-items: center; gap: 8px;
        border: 1px solid var(--border-2); border-radius: 10px; padding: 10px 11px;
        background: var(--surface); cursor: pointer; transition: border-color .15s, background .15s;
    }
    .rm input { accent-color: var(--accent); width: 16px; height: 16px; cursor: pointer; flex: 0 0 auto; }
    .rm .no { font-variant-numeric: tabular-nums; font-weight: 700; font-size: .9rem; }
    .rm:hover { border-color: var(--accent); background: var(--hover); }
    .rm.checked { border-color: var(--accent); background: var(--accent-soft); }
    .rm.is-occupied { opacity: .5; cursor: not-allowed; background: var(--bg); }
    .rm.is-occupied:hover { border-color: var(--border-2); background: var(--bg); }
    .rm .tag { font-size: .64rem; color: var(--accent-2-600); font-weight: 700; }

    /* ---------- Kotak diskon ---------- */
    .dsk-box {
        margin-top: 0; padding: 16px;
        border: 1px solid var(--border-2); border-radius: 12px; background: var(--surface-2);
    }
    .dsk-mode { display: inline-flex; border: 1px solid var(--border-2); border-radius: 999px; overflow: hidden; margin-bottom: 12px; }
    .dsk-mode label {
        position: relative; cursor: pointer; font-size: .76rem; font-weight: 700;
        padding: 7px 16px; color: var(--muted); background: var(--surface); white-space: nowrap;
        transition: background .15s, color .15s;
    }
    .dsk-mode label + label { border-left: 1px solid var(--border-2); }
    .dsk-mode input { position: absolute; opacity: 0; width: 0; height: 0; }
    .dsk-mode label:hover { background: var(--hover); color: var(--text); }
    .dsk-mode label.aktif { background: var(--accent-soft); color: var(--accent); }
    .dsk-baris[hidden] { display: none; }
    .dsk-input { display: flex; align-items: center; max-width: 280px; }
    .dsk-input .sat {
        font-weight: 700; font-size: .92rem; color: var(--muted);
        border: 1px solid var(--border-2); padding: 12px; background: var(--bg);
    }
    .dsk-input .sat.kiri  { border-right: none; border-radius: 10px 0 0 10px; }
    .dsk-input .sat.kanan { border-left: none;  border-radius: 0 10px 10px 0; }
    .dsk-input input {
        flex: 1 1 auto; min-width: 0; font-family: inherit; font-size: 1rem; font-weight: 700;
        color: var(--text); font-variant-numeric: tabular-nums;
        background: var(--bg); border: 1px solid var(--border-2); padding: 12px 14px;
    }
    .dsk-input input.kanan-bulat { border-radius: 0 10px 10px 0; }
    .dsk-input input.kiri-bulat  { border-radius: 10px 0 0 10px; }
    .dsk-input input:focus { outline: none; border-color: var(--accent); }
    .dsk-cepat { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; }
    .dsk-cepat button {
        font-family: inherit; font-size: .72rem; font-weight: 700; cursor: pointer;
        background: var(--surface); color: var(--muted); border: 1px solid var(--border-2);
        border-radius: 999px; padding: 5px 11px; white-space: nowrap;
    }
    .dsk-cepat button:hover { background: var(--hover); color: var(--text); }
    .dsk-hitung {
        display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
        margin-top: 12px; font-size: .85rem; color: var(--muted);
    }
    .dsk-pill {
        border: 1px solid var(--border-2); border-radius: 999px; padding: 5px 12px;
        background: var(--surface); font-variant-numeric: tabular-nums;
    }
    .dsk-pill b { color: var(--text); font-weight: 700; }
    .dsk-pill.hemat { border-color: var(--ok-line); background: var(--ok-soft); }
    .dsk-pill.hemat b { color: var(--ok); }
    .dsk-galat { margin-top: 10px; font-size: .82rem; color: var(--danger); }
    .dsk-galat[hidden] { display: none; }

    /* Pintasan tanggal mundur */
    .tgl-pintas { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin: -6px 0 18px; }
    .pintas-btn {
        display: inline-flex; align-items: center; gap: 7px;
        font-family: inherit; font-size: .8rem; font-weight: 700; cursor: pointer;
        background: var(--surface); color: var(--muted); border: 1px solid var(--border-2);
        border-radius: 999px; padding: 7px 14px;
    }
    .pintas-btn:hover { background: var(--hover); color: var(--text); }
    .pintas-btn.aktif { background: var(--accent-soft); color: var(--accent); border-color: var(--border); }
    .pintas-nb { font-size: .8rem; color: var(--muted); }
    .pintas-nb[hidden] { display: none; }

    .rooms-sum { margin-top: 12px; font-size: .82rem; color: var(--muted); }
    .rooms-sum b { color: var(--accent); font-variant-numeric: tabular-nums; }

    .co-line {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        margin: -6px 0 20px; font-size: .88rem; color: var(--muted);
    }
    .co-line b { color: var(--accent); font-weight: 700; }

    /* Pilihan sarapan */
    .bf-grup { display: flex; gap: 10px; flex-wrap: wrap; }
    .bf-opsi {
        flex: 1 1 180px; display: flex; align-items: center; gap: 11px; cursor: pointer;
        border: 1px solid var(--border-2); border-radius: 12px; padding: 13px 15px;
        background: var(--surface); transition: border-color .15s, background .15s;
    }
    .bf-opsi:hover { border-color: var(--accent); background: var(--hover); }
    .bf-opsi.aktif { border-color: var(--accent); background: var(--accent-soft); }
    .bf-opsi input { width: 17px; height: 17px; accent-color: var(--accent); cursor: pointer; flex: 0 0 auto; }
    .bf-opsi .t { font-weight: 700; font-size: .92rem; }
    .bf-opsi .d { display: block; font-size: .75rem; color: var(--muted); font-weight: 500; margin-top: 2px; }

    /* ---------- Uang muka (DP) ---------- */
    .dp-box {
        margin-top: 14px; padding: 16px;
        border: 1px solid var(--border-2); border-radius: 12px; background: var(--surface-2);
    }
    .dp-box[hidden] { display: none; }
    .dp-box > label {
        display: block; font-size: .74rem; letter-spacing: .06em; text-transform: uppercase;
        color: var(--muted); margin-bottom: 8px; font-weight: 700;
    }
    .dp-hitung {
        display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
        margin-top: 12px; font-size: .84rem; color: var(--muted);
    }
    .dp-hitung .pil {
        border: 1px solid var(--border-2); border-radius: 999px; padding: 5px 12px;
        background: var(--surface); font-variant-numeric: tabular-nums;
    }
    .dp-hitung .pil b { color: var(--text); font-weight: 700; }
    .dp-hitung .pil.sisa { border-color: var(--warn-line); background: var(--warn-soft); }
    .dp-hitung .pil.sisa b { color: var(--warn); }
    .dp-galat { margin-top: 10px; font-size: .82rem; color: var(--danger); }
    .dp-galat[hidden] { display: none; }

    /* ---------- Bukti pembayaran saat pesanan dibuat ---------- */
    .bukti-box { margin-top: 16px; }
    .bukti-box[hidden] { display: none; }
    .bukti-ket {
        display: flex; gap: 10px; align-items: flex-start; margin-bottom: 12px;
        background: var(--warn-soft); border: 1px solid var(--warn-line);
        border-radius: 12px; padding: 12px 14px; font-size: .85rem; color: var(--warn); line-height: 1.55;
    }
    .bukti-ket .ic-lg { flex: 0 0 auto; margin-top: 1px; }
    .bukti-ada {
        display: flex; gap: 8px; align-items: center; margin-bottom: 12px;
        font-size: .84rem; color: var(--ok);
    }

    .note-ktp {
        display: flex; gap: 10px; align-items: flex-start; color: var(--muted);
        background: var(--accent-2-soft); border: 1px solid var(--border-orange);
        border-radius: 12px; padding: 12px 14px; font-size: .85rem; color: var(--muted);
    }
    .note-grup {
        display: flex; gap: 10px; align-items: flex-start; color: var(--muted);
        background: var(--hover); border: 1px solid var(--border);
        border-radius: 12px; padding: 12px 14px; font-size: .85rem; color: var(--muted); margin-bottom: 20px;
    }

    .form-actions { display: flex; gap: 12px; margin-top: 26px; padding-top: 22px; border-top: 1px solid var(--border-2); }

    @media (max-width: 560px) {
        .grid-2 { grid-template-columns: 1fr; }
    }
</style>
@endpush

@if ($isEdit && $groupCount > 1)
    <div class="note-grup">
        @include('partials.icon', ['name' => 'users', 'class' => 'ic-lg'])
        <span>Booking ini bagian dari pesanan <b>{{ $groupCount }} kamar</b>. Perubahan nama tamu, telepon,
        dan tanggal otomatis berlaku untuk <b>semua kamar</b> dalam pesanan tersebut.</span>
    </div>
@endif

<div class="fld">
    <label for="nama_tamu">Nama Tamu</label>
    <input id="nama_tamu" type="text" name="nama_tamu"
           value="{{ old('nama_tamu', $booking->nama_tamu ?? '') }}"
           placeholder="Contoh: Rizky Pratama" required>
</div>

<div class="grid-2">
    <div class="fld">
        <label for="check_in">Tanggal Check-in</label>
        <input id="check_in" type="date" name="check_in" value="{{ $checkInVal }}"
               @unless ($isEdit) min="{{ $minDate }}" @endunless required>
    </div>
    <div class="fld">
        <label for="check_out">Tanggal Check-out</label>
        <input id="check_out" type="date" name="check_out" value="{{ $checkOutVal }}" required>
    </div>
</div>

@unless ($isEdit)
    {{-- Tamu tanpa pesanan yang datang lewat tengah malam: malam yang dipakai
         adalah malam KEMARIN, sehingga check-out-nya siang ini. Satu tombol,
         karena ini dikerjakan jam satu pagi oleh orang yang mengantuk. --}}
    <div class="tgl-pintas">
        <button type="button" class="pintas-btn" id="btn-dini-hari"
                data-masuk="{{ $kemarin }}" data-keluar="{{ $hariIniStr }}">
            Tamu datang dini hari (malam kemarin)
        </button>
        <span class="pintas-nb" id="pintas-nb" hidden>Check-in disetel {{ \Illuminate\Support\Carbon::parse($kemarin)->translatedFormat('j M') }}, check-out siang ini.</span>
    </div>
@endunless

<div class="co-line">Lama menginap: <b id="malam-preview">{{ $malamAwal }}</b> malam</div>

<div class="fld">
    <label>{{ $isEdit ? 'Kamar' : 'Pilih Kamar (boleh lebih dari satu)' }}</label>

    @if ($isEdit)
        <select id="room_id" name="room_id" class="room-input" required>
            <option value="">— Pilih kamar —</option>
            @foreach ($tipeLabels as $tipe => $label)
                @php $roomsTipe = $roomsByTipe->get($tipe, collect()); @endphp
                @if ($roomsTipe->isNotEmpty())
                    <optgroup label="{{ $label }}">
                        @foreach ($roomsTipe as $room)
                            @php $isOcc = $occupied->contains($room->id); @endphp
                            <option value="{{ $room->id }}"
                                    data-nomor="{{ $room->nomor }} ({{ $room->labelTipe() }})"
                                    data-harga="{{ $room->hargaPerMalam(true) }}"
                                    data-harga-tanpa-bf="{{ $room->hargaPerMalam(false) }}"
                                    data-bf-wajib="{{ $room->bolehTanpaBreakfast() ? '0' : '1' }}"
                                    @selected(in_array($room->id, $checkedIds, true))
                                    @disabled($isOcc)>
                                {{ $room->nomor }} ({{ $room->labelTipe() }}){{ $isOcc ? ' — terisi' : '' }}
                            </option>
                        @endforeach
                    </optgroup>
                @endif
            @endforeach
        </select>
    @else
        <div class="rooms-box" id="rooms-box">
            @foreach ($tipeLabels as $tipe => $label)
                @php $roomsTipe = $roomsByTipe->get($tipe, collect()); @endphp
                @if ($roomsTipe->isNotEmpty())
                    <div class="rooms-grp">
                        <div class="ttl">{{ $label }} <span>({{ $roomsTipe->count() }} kamar)</span></div>
                        <div class="rooms-grid">
                            @foreach ($roomsTipe as $room)
                                @php
                                    $isOcc = $occupied->contains($room->id);
                                    $isChk = in_array($room->id, $checkedIds, true) && ! $isOcc;
                                @endphp
                                <label class="rm {{ $isOcc ? 'is-occupied' : '' }} {{ $isChk ? 'checked' : '' }}"
                                       data-room-id="{{ $room->id }}">
                                    <input type="checkbox" name="room_ids[]" value="{{ $room->id }}"
                                           class="room-cb" data-harga="{{ $room->hargaPerMalam(true) }}"
                                           data-harga-tanpa-bf="{{ $room->hargaPerMalam(false) }}"
                                                  data-bf-wajib="{{ $room->bolehTanpaBreakfast() ? '0' : '1' }}"
                                           @checked($isChk) @disabled($isOcc)>
                                    <span class="no">{{ $room->nomor }}</span>
                                    <span class="tag js-tag">{{ $isOcc ? 'terisi' : '' }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            <div class="rooms-sum">
                Terpilih: <b id="rooms-count">{{ count($checkedIds) }}</b> kamar ·
                total tagihan <b id="rooms-total">Rp 0</b>
            </div>
        </div>
        <div class="hint">Hanya kamar kosong pada rentang tanggal terpilih yang bisa dicentang.</div>
    @endif
</div>

<div class="fld">
    <label>Sarapan</label>
    @php $bfNilai = (int) old('breakfast', (int) ($booking->breakfast ?? 0)); @endphp
    <div class="bf-grup" id="bf-grup">
        <label class="bf-opsi {{ $bfNilai === 1 ? 'aktif' : '' }}">
            <input type="radio" name="breakfast" value="1" @checked($bfNilai === 1)>
            <span class="t">Dengan Breakfast<span class="d">Tamu mendapat sarapan tiap pagi</span></span>
        </label>
        <label class="bf-opsi {{ $bfNilai === 0 ? 'aktif' : '' }}">
            <input type="radio" name="breakfast" value="0" @checked($bfNilai === 0)>
            <span class="t">Tanpa Breakfast<span class="d">Kamar saja</span></span>
        </label>
    </div>
    @unless ($isEdit)
        <div class="hint">Berlaku untuk semua kamar dalam pesanan ini.</div>
    @endunless
    <div class="hint" id="bf-wajib-note" hidden>Kamar Family selalu dijual dengan sarapan — pilihan tanpa sarapan dikunci.</div>
</div>

<div class="fld">
    <label>Asal Pesanan</label>
    <div class="bf-grup" id="sumber-grup">
        <label class="bf-opsi {{ $sumberVal === 'umum' ? 'aktif' : '' }}">
            <input type="radio" name="sumber" value="umum" @checked($sumberVal === 'umum')>
            <span class="t">Umum<span class="d">Walk-in / telepon / WhatsApp</span></span>
        </label>
        <label class="bf-opsi {{ $sumberVal === 'tiket' ? 'aktif' : '' }}">
            <input type="radio" name="sumber" value="tiket" @checked($sumberVal === 'tiket')>
            <span class="t">Tiket.com<span class="d">Sudah terbayar di muka</span></span>
        </label>
    </div>
</div>

{{-- Diskon berlaku untuk satu pesanan dan memotong tagihan kamar. --}}
<div class="fld">
    <label for="diskon_tampil">Diskon</label>
    <div class="dsk-box">
        <div class="dsk-mode" id="dsk-mode">
            <label class="{{ $diskonModeVal === 'rupiah' ? 'aktif' : '' }}">
                <input type="radio" name="diskon_mode" value="rupiah" @checked($diskonModeVal === 'rupiah')> Rupiah
            </label>
            <label class="{{ $diskonModeVal === 'persen' ? 'aktif' : '' }}">
                <input type="radio" name="diskon_mode" value="persen" @checked($diskonModeVal === 'persen')> Persen
            </label>
        </div>

        {{-- Dua kolom isian, hanya satu yang terlihat. Server menghitung ulang
             nominal dari persen, jadi angka tersimpan selalu sama dengan yang
             tampil di layar. --}}
        <div class="dsk-baris" id="baris-rupiah" @if ($diskonModeVal !== 'rupiah') hidden @endif>
            <div class="dsk-input">
                <span class="sat kiri">Rp</span>
                <input id="diskon_tampil" class="kanan-bulat" type="text" inputmode="numeric" autocomplete="off"
                       value="{{ $diskonVal > 0 ? number_format($diskonVal, 0, ',', '.') : '' }}" placeholder="0">
            </div>
        </div>

        <div class="dsk-baris" id="baris-persen" @if ($diskonModeVal !== 'persen') hidden @endif>
            <div class="dsk-input">
                <input id="diskon_persen_tampil" class="kiri-bulat" type="text" inputmode="numeric" autocomplete="off"
                       value="{{ $diskonPersenVal > 0 ? $diskonPersenVal : '' }}" placeholder="0" maxlength="2">
                <span class="sat kanan">%</span>
            </div>
        </div>

        <input type="hidden" name="diskon_nominal" id="diskon_nominal" value="{{ $diskonVal }}">
        <input type="hidden" name="diskon_persen" id="diskon_persen" value="{{ $diskonPersenVal }}">

        <div class="dsk-cepat">
            <button type="button" data-persen="5">5%</button>
            <button type="button" data-persen="10">10%</button>
            <button type="button" data-persen="15">15%</button>
            <button type="button" data-persen="20">20%</button>
            <button type="button" data-persen="0">Hapus</button>
        </div>

        <div class="dsk-hitung">
            <span class="dsk-pill">Total kamar <b id="dk-kotor">Rp 0</b></span>
            <span class="dsk-pill hemat">Potongan <b id="dk-potong">Rp 0</b></span>
            <span class="dsk-pill">Tagihan <b id="dk-bersih">Rp 0</b></span>
        </div>
        <div class="dsk-galat" id="dk-galat" hidden></div>
        <div class="hint" id="diskon-terkunci" hidden>Pesanan Tiket.com tidak bisa diberi diskon.</div>
    </div>
</div>

<div class="fld">
    <label>Status Pembayaran</label>
    <div class="bf-grup" id="bayar-grup">
        <label class="bf-opsi {{ $bayarVal === 'lunas' ? 'aktif' : '' }}">
            <input type="radio" name="status_bayar" value="lunas" @checked($bayarVal === 'lunas')>
            <span class="t">Lunas<span class="d">Tidak perlu bukti saat check-in</span></span>
        </label>
        <label class="bf-opsi {{ $bayarVal === 'dp' ? 'aktif' : '' }}">
            <input type="radio" name="status_bayar" value="dp" @checked($bayarVal === 'dp')>
            <span class="t">DP<span class="d">Sebagian dibayar di muka</span></span>
        </label>
        <label class="bf-opsi {{ $bayarVal === 'belum' ? 'aktif' : '' }}">
            <input type="radio" name="status_bayar" value="belum" @checked($bayarVal === 'belum')>
            <span class="t">Belum Lunas<span class="d">Bukti pelunasan wajib saat check-in</span></span>
        </label>
    </div>
    <div class="hint" id="bayar-terkunci" hidden>Pesanan Tiket.com otomatis lunas.</div>

    {{-- Nominal uang muka. Yang dikirim ke server adalah angka murni pada
         input tersembunyi; kolom yang terlihat hanya versi berformat titik
         ribuan supaya nominal besar tidak salah ketik. --}}
    <div class="dp-box" id="dp-box" @if ($bayarVal !== 'dp') hidden @endif>
        <label for="dp_tampil">Uang muka yang sudah dibayar</label>
        <div class="dsk-input">
            <span class="sat kiri">Rp</span>
            <input id="dp_tampil" class="kanan-bulat" type="text" inputmode="numeric" autocomplete="off"
                   value="{{ $dpVal > 0 ? number_format($dpVal, 0, ',', '.') : '' }}" placeholder="0">
        </div>
        <input type="hidden" name="dp_nominal" id="dp_nominal" value="{{ $dpVal }}">

        <div class="dp-hitung">
            <span class="pil">Tagihan setelah diskon <b id="dp-total">Rp 0</b></span>
            <span class="pil">Sudah dibayar <b id="dp-bayar">Rp 0</b></span>
            <span class="pil sisa">Sisa ditagih saat check-in <b id="dp-sisa">Rp 0</b></span>
        </div>
        <div class="dp-galat" id="dp-galat" hidden></div>
    </div>

    {{-- ---------- Bukti pembayaran saat pesanan dibuat ----------
         Muncul begitu status "Lunas" atau "DP" dipilih. Keduanya adalah
         pernyataan bahwa uang SUDAH diterima, dan pernyataan itu tidak boleh
         berdiri tanpa sandaran: tanpa foto, pesanan bisa ditandai lunas tanpa
         uang pernah masuk, atau nominal DP ditulis lebih kecil dari yang
         diterima — dan selisihnya tidak muncul di mana-mana.

         Pesanan Tiket.com tidak diminta apa-apa: uangnya masuk lewat mitra,
         tidak ada transaksi di meja depan yang bisa difoto. --}}
    <div class="bukti-box" id="bukti-box" hidden>
        <div class="bukti-ket">
            @include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-lg'])
            <span id="bukti-pesan">Unggah foto bukti pembayaran yang diterima.</span>
        </div>

        @if ($adaBuktiBayar)
            <div class="bukti-ada">
                @include('partials.icon', ['name' => 'check', 'class' => 'ic-xs'])
                Bukti pembayaran sudah tersimpan. Unggah lagi hanya bila ingin menggantinya.
            </div>
        @endif

        @include('partials.foto-uploader', [
            'name'    => 'foto_bayar_awal',
            'fotoUrl' => $adaBuktiBayar ? route('bayar.awal', $booking) : null,
            'ikon'    => 'clipboard',
            'judul'   => 'Bukti pembayaran',
            'pesan'   => 'Belum ada bukti pembayaran.',
            'hint'    => $adaBuktiBayar ? 'Opsional — hanya bila ingin mengganti.' : 'Wajib.',
        ])
    </div>
</div>

<div class="fld">
    <label for="telepon">Nomor Telepon</label>
    <input id="telepon" type="tel" name="telepon"
           value="{{ old('telepon', $booking->telepon ?? '') }}"
           placeholder="08xxxxxxxxxx" required>
</div>

<div class="note-ktp">
    @include('partials.icon', ['name' => 'id-card', 'class' => 'ic-lg'])
    <span>Foto KTP <b>tidak diisi di sini</b>. Saat tamu tiba, tekan tombol
    <b>“Tamu Check-in”</b> pada daftar booking — satu kali check-in berlaku untuk semua kamar dalam pesanan ini.</span>
</div>

@push('scripts')
<script>
    (function () {
        const checkIn   = document.getElementById('check_in');
        const checkOut  = document.getElementById('check_out');
        const malamEl   = document.getElementById('malam-preview');
        const roomsBox  = document.getElementById('rooms-box');   // mode tambah
        const roomSel   = document.getElementById('room_id');     // mode ubah
        const countEl   = document.getElementById('rooms-count');

        const AVAIL_URL = @json(route(auth()->user()?->role === 'admin' ? 'admin.rooms.available' : 'staff.rooms.available'));
        const IGNORE_ID = @json($booking?->id);

        let lastOccupied = new Set(@json($occupied->values()).map(Number));

        // Durasi tidak lagi diketik: dihitung dari jarak dua tanggal.
        function nilaiDurasi() {
            const a = new Date(checkIn.value + 'T00:00:00');
            const b = new Date(checkOut.value + 'T00:00:00');
            if (isNaN(a.getTime()) || isNaN(b.getTime())) return 1;
            return Math.max(1, Math.round((b - a) / 86400000));
        }

        // Check-out minimal sehari setelah check-in; kalau tanggalnya jadi
        // tidak masuk akal setelah check-in diubah, geser otomatis.
        function rapikanTanggal() {
            const a = new Date(checkIn.value + 'T00:00:00');
            if (isNaN(a.getTime())) return;

            const minOut = new Date(a);
            minOut.setDate(minOut.getDate() + 1);
            const minStr = minOut.toISOString().slice(0, 10);
            checkOut.min = minStr;

            if (!checkOut.value || checkOut.value <= checkIn.value) {
                checkOut.value = minStr;
            }
        }

        function updateCheckout() {
            rapikanTanggal();
            if (malamEl) malamEl.textContent = nilaiDurasi();
        }

        // ---------- Terapkan ketersediaan ke UI ----------
        function syncRooms() {
            if (roomsBox) {
                let n = 0;
                roomsBox.querySelectorAll('.rm').forEach(function (lbl) {
                    const cb = lbl.querySelector('.room-cb');
                    const terisi = lastOccupied.has(parseInt(cb.value, 10));
                    cb.disabled = terisi;
                    if (terisi) cb.checked = false;
                    lbl.classList.toggle('is-occupied', terisi);
                    lbl.classList.toggle('checked', cb.checked);
                    lbl.querySelector('.js-tag').textContent = terisi ? 'terisi' : '';
                    if (cb.checked) n++;
                });
                if (countEl) countEl.textContent = n;
            }

            if (roomSel) {
                roomSel.querySelectorAll('option').forEach(function (op) {
                    if (!op.value) return;
                    const terisi = lastOccupied.has(parseInt(op.value, 10));
                    op.disabled = terisi;
                    op.textContent = op.dataset.nomor + (terisi ? ' — terisi' : '');
                });
                if (roomSel.value && lastOccupied.has(parseInt(roomSel.value, 10))) {
                    roomSel.value = '';
                }
            }
        }

        // ---------- Ketersediaan kamar (live) ----------
        let availTimer = null;
        function refreshAvailability() {
            updateCheckout();
            const ci = checkIn.value;
            if (!ci) return;

            clearTimeout(availTimer);
            availTimer = setTimeout(function () {
                const url = new URL(AVAIL_URL, window.location.origin);
                url.searchParams.set('check_in', ci);
                url.searchParams.set('durasi', nilaiDurasi());
                if (IGNORE_ID) url.searchParams.set('ignore', IGNORE_ID);

                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.ok ? r.json() : Promise.reject())
                    .then(data => {
                        lastOccupied = new Set((data.occupied || []).map(Number));
                        syncRooms();
                    })
                    .catch(() => {}); // validasi server tetap penjaga terakhir
            }, 250);
        }

        // Kamar bisa ikut ter-uncheck saat ketersediaan berubah, jadi aturan
        // sarapan dihitung ulang setiap kali daftar kamar disinkronkan.
        const syncRoomsAsli = syncRooms;
        syncRooms = function () {
            syncRoomsAsli();
            terapkanAturanBf();
        };

        checkIn.addEventListener('change', refreshAvailability);
        checkOut.addEventListener('change', refreshAvailability);

        // ---------- Centang kamar ----------
        if (roomsBox) {
            roomsBox.addEventListener('change', function (e) {
                if (e.target.matches('.room-cb')) syncRooms();
            });
        }

        // Sorot opsi terpilih pada semua grup pilihan (sarapan, sumber, bayar).
        ['bf-grup', 'sumber-grup', 'bayar-grup'].forEach(function (id) {
            const grup = document.getElementById(id);
            if (!grup) return;
            grup.addEventListener('change', function () {
                grup.querySelectorAll('.bf-opsi').forEach(function (l) {
                    l.classList.toggle('aktif', l.querySelector('input').checked);
                });
            });
        });

        // ---------- Kamar family wajib sarapan ----------
        // Tarif tanpa sarapan hanya ada untuk King & Twin, jadi begitu kamar
        // family ikut terpilih pilihannya dikunci ke "Dengan Breakfast".
        const bfGrup = document.getElementById('bf-grup');
        const bfNote = document.getElementById('bf-wajib-note');

        function adaKamarWajibBf() {
            if (roomsBox) {
                return Array.from(roomsBox.querySelectorAll('.room-cb'))
                    .some(cb => cb.checked && cb.dataset.bfWajib === '1');
            }
            if (roomSel) {
                const op = roomSel.selectedOptions[0];
                return !!op && op.dataset.bfWajib === '1';
            }
            return false;
        }

        function terapkanAturanBf() {
            if (!bfGrup) return;
            const wajib = adaKamarWajibBf();

            bfGrup.querySelectorAll('input').forEach(function (inp) {
                if (wajib) {
                    inp.checked  = inp.value === '1';
                    inp.disabled = inp.value === '0';
                } else {
                    inp.disabled = false;
                }
            });
            bfGrup.querySelectorAll('.bf-opsi').forEach(function (l) {
                const inp = l.querySelector('input');
                l.classList.toggle('aktif', inp.checked);
                l.style.opacity = inp.disabled ? '.45' : '';
                l.style.cursor  = inp.disabled ? 'not-allowed' : 'pointer';
            });
            if (bfNote) bfNote.hidden = !wajib;
        }

        if (roomsBox) roomsBox.addEventListener('change', terapkanAturanBf);
        if (roomSel)  roomSel.addEventListener('change', terapkanAturanBf);
        terapkanAturanBf();

        // ---------- Tiket.com selalu lunas ----------
        // Pilihan pembayaran dikunci supaya pesanan yang jelas sudah terbayar
        // tidak bisa ditandai "belum lunas" — celah untuk menagih ulang tamu.
        const sumberGrup = document.getElementById('sumber-grup');
        const bayarGrup  = document.getElementById('bayar-grup');
        const bayarNote  = document.getElementById('bayar-terkunci');

        function terapkanSumber() {
            if (!sumberGrup || !bayarGrup) return;
            const tiket = sumberGrup.querySelector('input[value="tiket"]').checked;

            bayarGrup.querySelectorAll('input').forEach(function (inp) {
                inp.disabled = tiket;
                if (tiket) inp.checked = inp.value === 'lunas';
            });
            bayarGrup.querySelectorAll('.bf-opsi').forEach(function (l) {
                l.classList.toggle('aktif', l.querySelector('input').checked);
                l.style.opacity = tiket && !l.querySelector('input').checked ? '.45' : '';
                l.style.cursor  = tiket ? 'not-allowed' : 'pointer';
            });
            if (bayarNote) bayarNote.hidden = !tiket;
        }

        if (sumberGrup) sumberGrup.addEventListener('change', terapkanSumber);
        terapkanSumber();

        // ---------- Diskon ----------
        // Tagihan dihitung ulang setiap kali kamar, tanggal, satuan, atau
        // nominal berubah. Server tetap menghitung sendiri saat menyimpan; ini
        // supaya staff melihat angka yang akan ditagih SEBELUM menekan simpan.
        const HARGA_LAIN   = @json($hargaLain);
        const diskonTampil = document.getElementById('diskon_tampil');
        const diskonInput  = document.getElementById('diskon_nominal');
        const dskModeBox   = document.getElementById('dsk-mode');
        const dskPersenIn  = document.getElementById('diskon_persen');
        const dskPersenView = document.getElementById('diskon_persen_tampil');
        const barisRupiah  = document.getElementById('baris-rupiah');
        const barisPersen  = document.getElementById('baris-persen');
        const dskCepat     = document.querySelector('.dsk-cepat');
        const dkKotor      = document.getElementById('dk-kotor');
        const dkPotong     = document.getElementById('dk-potong');
        const dkBersih     = document.getElementById('dk-bersih');
        const dkGalat      = document.getElementById('dk-galat');
        const dskKunci     = document.getElementById('diskon-terkunci');
        const roomsTotalEl = document.getElementById('rooms-total');

        function rupiah(n) { return 'Rp ' + (n || 0).toLocaleString('id-ID'); }

        function hanyaAngka(teks) {
            const bersih = String(teks).replace(/\D/g, '');
            return bersih === '' ? 0 : parseInt(bersih, 10);
        }

        /**
         * Sarapan yang sedang dipilih.
         *
         * Tanpa ini, angka di layar selalu memakai tarif DENGAN sarapan —
         * memilih "Tanpa Breakfast" tidak mengubah apa pun yang terlihat,
         * padahal server menyimpan tarif 250rb. Staff jadi menyebut angka
         * yang salah ke tamu, dan selisihnya baru ketahuan di invoice.
         */
        function pakaiBreakfast() {
            const r = document.querySelector('input[name="breakfast"]:checked');
            return !r || r.value === '1';
        }

        /** Tarif per malam sebuah elemen kamar, sesuai pilihan sarapan. */
        function tarifKamar(el) {
            const penuh = parseInt(el.dataset.harga || '0', 10);

            // Kamar family tidak punya tarif tanpa sarapan — tarifnya tetap
            // penuh apa pun yang dipilih, sama seperti aturan di server.
            if (pakaiBreakfast() || el.dataset.bfWajib === '1') return penuh;

            const tanpa = parseInt(el.dataset.hargaTanpaBf || '0', 10);
            return tanpa > 0 ? tanpa : penuh;
        }

        /** Tarif per malam seluruh kamar dalam pesanan ini. */
        function hargaPerMalam() {
            let total = 0;

            if (roomsBox) {
                roomsBox.querySelectorAll('.room-cb').forEach(function (cb) {
                    if (cb.checked) total += tarifKamar(cb);
                });
            }

            if (roomSel) {
                const op = roomSel.selectedOptions[0];
                if (op && op.value) total += tarifKamar(op);
                total += HARGA_LAIN;   // kamar lain segrup, tidak tampil di form ini
            }

            return total;
        }

        function modeDiskon() {
            const p = document.querySelector('#dsk-mode input[name="diskon_mode"]:checked');
            return p ? p.value : 'rupiah';
        }

        /** Sama persis dengan Booking::nominalDariPersen() di server. */
        function nominalDariPersen(total, persen) {
            if (persen <= 0 || total <= 0) return 0;
            return Math.round(total * Math.min(100, persen) / 100 / 1000) * 1000;
        }

        function dariTiketSekarang() {
            const r = sumberGrup ? sumberGrup.querySelector('input[value="tiket"]') : null;
            return !!(r && r.checked);
        }

        function hitungDiskon() {
            const total = hargaPerMalam() * nilaiDurasi();
            const tiket = dariTiketSekarang();

            if (roomsTotalEl) roomsTotalEl.textContent = rupiah(total);

            if (dskKunci) dskKunci.hidden = !tiket;
            if (dskModeBox) dskModeBox.hidden = tiket;
            if (tiket) {
                if (diskonInput)   diskonInput.value = '0';
                if (dskPersenIn)   dskPersenIn.value = '0';
                if (diskonTampil)  diskonTampil.value = '';
                if (dskPersenView) dskPersenView.value = '';
            }

            const persenMode = modeDiskon() === 'persen';
            if (barisRupiah) barisRupiah.hidden = tiket || persenMode;
            if (barisPersen) barisPersen.hidden = tiket || !persenMode;

            let diskon = 0;
            if (!tiket) {
                if (persenMode) {
                    diskon = nominalDariPersen(total, Math.min(99, hanyaAngka(dskPersenIn.value)));
                    diskonInput.value = diskon;
                } else {
                    diskon = hanyaAngka(diskonInput.value);
                }
            }

            const dipakai = Math.min(diskon, Math.max(0, total));

            if (dkKotor)  dkKotor.textContent  = rupiah(total);
            if (dkPotong) dkPotong.textContent = rupiah(dipakai);
            if (dkBersih) dkBersih.textContent = rupiah(Math.max(0, total - dipakai));

            if (dkGalat) {
                const lebih = !tiket && total > 0 && diskon >= total;
                dkGalat.textContent = lebih
                    ? 'Diskon sama atau lebih besar dari total kamar. Turunkan nominalnya.'
                    : '';
                dkGalat.hidden = !lebih;
            }

            hitungDp(tiket ? total : Math.max(0, total - dipakai));
        }

        // ---------- Uang muka (DP) & bukti pembayaran ----------
        // bayarGrup sudah dideklarasikan di blok "Tiket.com selalu lunas" di
        // atas — dipakai ulang, bukan dideklarasikan lagi.
        const dpBox      = document.getElementById('dp-box');
        const dpTampil   = document.getElementById('dp_tampil');
        const dpNominal  = document.getElementById('dp_nominal');
        const dpTotalEl  = document.getElementById('dp-total');
        const dpBayarEl  = document.getElementById('dp-bayar');
        const dpSisaEl   = document.getElementById('dp-sisa');
        const dpGalatEl  = document.getElementById('dp-galat');

        const buktiBox   = document.getElementById('bukti-box');
        const buktiPesan = document.getElementById('bukti-pesan');
        const buktiInput = buktiBox ? buktiBox.querySelector('input[type="file"]') : null;

        function statusBayarTerpilih() {
            const dipilih = document.querySelector('#bayar-grup input[name="status_bayar"]:checked');
            return dipilih ? dipilih.value : 'belum';
        }

        /**
         * $tagihan = total kamar SETELAH diskon — itulah angka yang benar-benar
         * harus dibayar tamu, dan DP diuji terhadapnya, bukan terhadap total
         * kotor.
         */
        function hitungDp(tagihan) {
            const status = statusBayarTerpilih();
            const tiket  = dariTiketSekarang();

            if (dpBox) dpBox.hidden = status !== 'dp' || tiket;

            // Nominal hanya bermakna pada status DP. Kalau tidak, dinolkan
            // supaya pesanan lunas tidak membawa angka sisa yang tertinggal.
            if (dpNominal && (status !== 'dp' || tiket)) dpNominal.value = '0';

            const dp   = dpNominal ? hanyaAngka(dpNominal.value) : 0;
            const sisa = Math.max(0, tagihan - Math.min(dp, tagihan));

            if (dpTotalEl) dpTotalEl.textContent = rupiah(tagihan);
            if (dpBayarEl) dpBayarEl.textContent = rupiah(Math.min(dp, tagihan));
            if (dpSisaEl)  dpSisaEl.textContent  = rupiah(sisa);

            if (dpGalatEl) {
                let pesan = '';
                if (status === 'dp' && !tiket) {
                    if (dp <= 0) {
                        pesan = 'Isi nominal uang muka yang sudah dibayar tamu.';
                    } else if (tagihan > 0 && dp >= tagihan) {
                        pesan = 'Uang muka sama atau lebih besar dari tagihan — pilih "Lunas" bila memang sudah dibayar penuh.';
                    }
                }
                dpGalatEl.textContent = pesan;
                dpGalatEl.hidden = pesan === '';
            }

            terapkanBukti(status, tiket, tagihan, dp);
        }

        function terapkanBukti(status, tiket, tagihan, dp) {
            if (!buktiBox) return;

            const perlu = !tiket && (status === 'lunas' || status === 'dp');
            buktiBox.hidden = !perlu;

            // Berkas yang terlanjur dipilih ikut dibuang saat kotaknya
            // disembunyikan, supaya pesanan "Belum Lunas" tidak pernah terkirim
            // membawa bukti bayar. Server juga menolaknya; ini supaya yang
            // terlihat di layar sama dengan yang tersimpan.
            if (!perlu && buktiInput) buktiInput.value = '';

            if (perlu && buktiPesan) {
                buktiPesan.textContent = status === 'lunas'
                    ? 'Pesanan ditandai LUNAS — unggah bukti pembayaran ' + rupiah(tagihan) + ' yang sudah diterima.'
                    : 'Pesanan ditandai DP — unggah bukti pembayaran uang muka ' + rupiah(dp) + ' yang sudah diterima.';
            }
        }

        if (dpTampil) {
            dpTampil.addEventListener('input', function () {
                const angka = hanyaAngka(dpTampil.value);
                dpNominal.value = angka;
                dpTampil.value  = angka > 0 ? angka.toLocaleString('id-ID') : '';
                hitungDiskon();
            });
        }

        if (bayarGrup) bayarGrup.addEventListener('change', hitungDiskon);

        if (diskonTampil) {
            diskonTampil.addEventListener('input', function () {
                const angka = hanyaAngka(diskonTampil.value);
                diskonInput.value = angka;
                diskonTampil.value = angka > 0 ? angka.toLocaleString('id-ID') : '';
                hitungDiskon();
            });
        }

        if (dskPersenView) {
            dskPersenView.addEventListener('input', function () {
                const angka = Math.min(99, hanyaAngka(dskPersenView.value));
                dskPersenIn.value = angka;
                dskPersenView.value = angka > 0 ? angka : '';
                hitungDiskon();
            });
        }

        if (dskModeBox) {
            dskModeBox.addEventListener('change', function () {
                const persen = modeDiskon() === 'persen';

                dskModeBox.querySelectorAll('label').forEach(function (lb) {
                    const rd = lb.querySelector('input');
                    lb.classList.toggle('aktif', rd && rd.checked);
                });

                // Berpindah satuan mengosongkan satuan yang ditinggalkan,
                // supaya tidak ada dua angka yang saling bertentangan.
                if (persen) {
                    if (diskonTampil) diskonTampil.value = '';
                } else {
                    if (dskPersenIn)   dskPersenIn.value = '0';
                    if (dskPersenView) dskPersenView.value = '';
                }

                hitungDiskon();
            });
        }

        if (dskCepat) {
            dskCepat.addEventListener('click', function (e) {
                const tombol = e.target.closest('button[data-persen]');
                if (!tombol) return;

                const persen = parseInt(tombol.dataset.persen, 10);
                const rd = document.querySelector('#dsk-mode input[value="'
                    + (persen > 0 ? 'persen' : 'rupiah') + '"]');
                if (rd) rd.checked = true;

                if (dskPersenIn)   dskPersenIn.value = persen;
                if (dskPersenView) dskPersenView.value = persen > 0 ? persen : '';
                if (persen === 0) {
                    if (diskonInput)  diskonInput.value = 0;
                    if (diskonTampil) diskonTampil.value = '';
                }

                if (dskModeBox) dskModeBox.dispatchEvent(new Event('change'));
                hitungDiskon();
            });
        }

        // Mengubah pilihan sarapan mengubah TARIF, jadi angka di layar harus
        // ikut dihitung ulang — inilah yang dulu tidak terjadi.
        if (bfGrup)     bfGrup.addEventListener('change', hitungDiskon);
        if (roomsBox)   roomsBox.addEventListener('change', hitungDiskon);
        if (roomSel)    roomSel.addEventListener('change', hitungDiskon);
        if (sumberGrup) sumberGrup.addEventListener('change', hitungDiskon);
        checkIn.addEventListener('change', hitungDiskon);
        checkOut.addEventListener('change', hitungDiskon);

        // Ketersediaan kamar yang berubah bisa melepas centang, jadi angka
        // tagihan ikut dihitung ulang setiap kali daftar kamar disinkronkan.
        const syncRoomsSebelumDiskon = syncRooms;
        syncRooms = function () {
            syncRoomsSebelumDiskon();
            hitungDiskon();
        };

        // ---------- Pintasan tamu dini hari ----------
        const btnDini  = document.getElementById('btn-dini-hari');
        const pintasNb = document.getElementById('pintas-nb');

        if (btnDini) {
            btnDini.addEventListener('click', function () {
                checkIn.value  = btnDini.dataset.masuk;
                checkOut.value = btnDini.dataset.keluar;

                // Mengubah tanggal lewat skrip tidak memicu 'change' sendiri,
                // padahal ketersediaan kamar dan hitungan malam bergantung padanya.
                checkIn.dispatchEvent(new Event('change'));
                checkOut.dispatchEvent(new Event('change'));
            });

            [checkIn, checkOut].forEach(function (el) {
                el.addEventListener('change', function () {
                    const cocok = checkIn.value === btnDini.dataset.masuk
                        && checkOut.value === btnDini.dataset.keluar;
                    btnDini.classList.toggle('aktif', cocok);
                    if (pintasNb) pintasNb.hidden = !cocok;
                });
            });
        }

        updateCheckout();
        syncRooms();
        hitungDiskon();
    })();
</script>
@endpush
