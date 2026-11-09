@extends('layouts.panel')

@section('title', 'Pindah Kamar')

@php
    /** @var \App\Models\Booking $booking */
    $tiket     = $booking->dariTiket();
    $bayarVal  = old('bayar', 'nanti');
    $lama      = old('pindah', []);
    $tipeLabel = ['king' => 'King', 'twin' => 'Twin', 'family' => 'Family'];
@endphp

@push('head')
<style>
    .form-card { padding: 26px; max-width: 860px; }
    .tipe-badge {
        display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: .68rem; font-weight: 700;
        background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border);
    }
    .tipe-badge.tipe-family { background: var(--accent-2-soft); color: var(--accent-2-600); border-color: var(--border-orange); }
    .pk-ringkas {
        display: flex; flex-wrap: wrap; gap: 8px 18px; align-items: baseline;
        padding: 14px 16px; margin-bottom: 18px; font-size: .9rem;
        border: 1px solid var(--border-2); border-radius: 14px; background: var(--bg);
    }
    .pk-ringkas b { font-weight: 800; }
    .pk-ringkas span { color: var(--muted); }

    .kmr-pindah {
        border: 1px solid var(--border-2); border-radius: 16px; padding: 14px 16px; margin-bottom: 12px;
        background: var(--surface); transition: border-color .15s, background .15s;
    }
    .kmr-pindah.aktif { border-color: var(--accent); background: linear-gradient(160deg, rgba(139,92,246,.12), rgba(139,92,246,.02)); }
    .kmr-pindah-head { display: flex; align-items: center; gap: 12px; cursor: pointer; flex-wrap: wrap; }
    .kmr-pindah-head input { width: 19px; height: 19px; accent-color: var(--accent); cursor: pointer; }
    .kmr-pindah-head .no { font-size: 1.1rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    .kmr-pindah-head .info { color: var(--muted); font-size: .84rem; }
    .kmr-pindah-isi { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 14px; }
    .kmr-pindah-isi[hidden] { display: none; }
    .kmr-pindah-isi label { display: block; font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
    .kmr-pindah-isi select {
        width: 100%; font-family: inherit; font-size: .95rem; color: var(--text);
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; padding: 11px 12px;
    }
    .kmr-pindah-isi select:focus { outline: none; border-color: var(--accent); }
    .kmr-pindah-isi select option:disabled { color: var(--muted); }
    .kmr-pindah-isi .penuh { grid-column: 1 / -1; }
    .kmr-pindah .hint { margin-top: 6px; font-size: .78rem; color: var(--muted); line-height: 1.5; }
    .kmr-pindah .hint.tukar { color: var(--accent-2); }
    @media (max-width: 640px) { .kmr-pindah-isi { grid-template-columns: 1fr; } }

    .bf-grup { display: flex; gap: 8px; flex-wrap: wrap; }
    .bf-grup label {
        flex: 1 1 140px; display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;
        border: 1px solid var(--border-2); border-radius: 10px; padding: 10px 12px; background: var(--bg);
        font-size: .85rem; font-weight: 700; letter-spacing: 0; text-transform: none; color: var(--text);
    }
    .bf-grup input { accent-color: var(--accent); }

    .pk-hitung {
        display: grid; gap: 8px; padding: 16px; margin: 18px 0;
        border: 1px solid var(--border-violet); border-radius: 14px; background: var(--accent-2-soft);
        font-variant-numeric: tabular-nums;
    }
    .pk-hitung[hidden] { display: none; }
    .pk-hitung .baris { display: flex; justify-content: space-between; gap: 12px; font-size: .9rem; }
    .pk-hitung .baris span:first-child { color: var(--muted); }
    .pk-hitung .baris.tebal { font-weight: 800; border-top: 1px dashed var(--border-violet); padding-top: 8px; }
    .pk-hitung .naik { color: var(--alert); }
    .pk-hitung .turun { color: var(--ok); }
    .pk-catatan { font-size: .82rem; line-height: 1.55; color: var(--accent-2); }

    .bayar-box[hidden] { display: none; }
    .bayar-opsi { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
    .bayar-opsi label {
        flex: 1 1 220px; display: flex; gap: 10px; align-items: center; cursor: pointer;
        border: 1px solid var(--border-2); border-radius: 12px; padding: 12px 14px; background: var(--surface);
    }
    .bayar-opsi label.aktif { border-color: var(--accent); background: var(--accent-soft); }
    .bayar-opsi input { accent-color: var(--accent); }
    .bayar-opsi .t { font-weight: 700; font-size: .9rem; }
    .bayar-opsi .d { display: block; font-size: .75rem; color: var(--muted); font-weight: 500; margin-top: 2px; }

    .form-actions { display: flex; gap: 12px; margin-top: 22px; padding-top: 20px; border-top: 1px solid var(--border-2); flex-wrap: wrap; }
</style>
@endpush

@section('content')
    <div class="page-head">
        <h1>Pindah Kamar</h1>
        <p>{{ $booking->nama_tamu }} · centang kamar yang dipindah — boleh satu, boleh semua.</p>
    </div>

    <div class="card form-card">
        <div class="pk-ringkas">
            <div><span>Pesanan</span> <b>{{ $baris->count() }} kamar aktif</b></div>
            <div><span>Pembayaran</span> <b>{{ $booking->ringkasanBayar($totalPesanan) }}</b></div>
            @if ($tiket)
                <div><span>Asal</span> <b>Tiket.com</b></div>
            @endif
        </div>

        <form method="POST" action="{{ \App\Support\Panel::rute('rooms.pindah.store', $booking) }}" enctype="multipart/form-data" id="form-pindah">
            @csrf

            @foreach ($baris as $row)
                @php
                    $b      = $row->booking;
                    $id     = $b->id;
                    $isian  = $lama[$id] ?? [];
                    $dipilih = $lama !== [] ? (($isian['aktif'] ?? null) === '1') : $id === $booking->id;
                    $mulai  = $isian['mulai'] ?? $row->bawaan;
                    $bf     = (string) ($isian['breakfast'] ?? ($b->breakfast ? '1' : '0'));
                @endphp
                <div class="kmr-pindah {{ $dipilih ? 'aktif' : '' }}"
                     data-id="{{ $id }}" data-room="{{ $b->room_id }}" data-tarif="{{ $b->hargaMalam() }}"
                     data-awal="{{ $b->check_in->toDateString() }}" data-akhir="{{ $b->check_out->toDateString() }}">
                    <label class="kmr-pindah-head">
                        <input type="checkbox" name="pindah[{{ $id }}][aktif]" value="1" class="js-aktif" @checked($dipilih)>
                        <span class="no">{{ $b->room?->nomor }}</span>
                        <span class="tipe-badge tipe-{{ $b->room?->tipe }}">{{ $b->room?->labelTipe() }}</span>
                        <span class="info">
                            {{ $b->check_in->translatedFormat('j M') }} – {{ $b->check_out->translatedFormat('j M Y') }}
                            · {{ $b->durasi }} malam · {{ \App\Models\Booking::rupiah($b->hargaMalam()) }}/malam
                            · {{ $b->isCheckedIn() ? 'sudah check-in' : 'belum check-in' }}
                        </span>
                    </label>

                    <div class="kmr-pindah-isi" @unless ($dipilih) hidden @endunless>
                        <div>
                            <label>Pindah mulai malam</label>
                            <select name="pindah[{{ $id }}][mulai]" class="js-mulai">
                                @foreach ($row->tanggal as $i => $t)
                                    @php $sisaMalam = (int) $t->diffInDays($b->check_out->copy()->startOfDay()); @endphp
                                    <option value="{{ $t->toDateString() }}" data-malam="{{ $sisaMalam }}" @selected($mulai === $t->toDateString())>
                                        {{ $t->translatedFormat('D, j M') }} — {{ $i === 0 ? 'seluruh masa menginap' : 'malam ke-' . ($i + 1) . ', ' . $sisaMalam . ' malam' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label>Kamar tujuan</label>
                            <select name="pindah[{{ $id }}][room_id]" class="js-kamar">
                                <option value="">— Pilih kamar —</option>
                                @foreach ($tipeLabel as $tipe => $label)
                                    @php $daftar = $roomsByTipe->get($tipe, collect()); @endphp
                                    @if ($daftar->isNotEmpty())
                                        <optgroup label="{{ $label }}">
                                            @foreach ($daftar as $room)
                                                <option value="{{ $room->id }}"
                                                        data-nomor="{{ $room->nomor }} ({{ $room->labelTipe() }})"
                                                        data-harga="{{ $room->hargaPerMalam(true) }}"
                                                        data-harga-tanpa-bf="{{ $room->hargaPerMalam(false) }}"
                                                        data-bf-wajib="{{ $room->bolehTanpaBreakfast() ? '0' : '1' }}"
                                                        @selected((int) ($isian['room_id'] ?? 0) === $room->id)
                                                        @disabled($room->id === $b->room_id)>
                                                    {{ $room->nomor }} ({{ $room->labelTipe() }}){{ $room->id === $b->room_id ? ' — kamar sekarang' : '' }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="penuh">
                            <label>Sarapan di kamar baru</label>
                            <div class="bf-grup js-bf">
                                <label><input type="radio" name="pindah[{{ $id }}][breakfast]" value="1" @checked($bf === '1')> Dengan Breakfast</label>
                                <label><input type="radio" name="pindah[{{ $id }}][breakfast]" value="0" @checked($bf === '0')> Tanpa Breakfast</label>
                            </div>
                            <div class="hint js-hint">Ketersediaan dihitung untuk malam-malam yang dipindah.</div>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="pk-hitung" id="pk-hitung" hidden>
                <div class="baris"><span>Kamar yang dipindah</span><b id="h-jumlah">—</b></div>
                <div class="baris"><span>Perubahan total kamar</span><b id="h-selisih">—</b></div>
                <div class="baris"><span>Tagihan pesanan</span><b id="h-tagihan">—</b></div>
                <div class="baris"><span>Sudah dibayar</span><b id="h-dibayar">—</b></div>
                <div class="baris tebal"><span>Sisa setelah pindah</span><b id="h-sisa">—</b></div>
                <div class="pk-catatan" id="h-catatan"></div>
            </div>

            {{-- Muncul hanya bila pindahnya membuat tamu harus membayar lebih. --}}
            <div class="bayar-box" id="bayar-box" hidden>
                <div class="bayar-opsi" id="bayar-grup">
                    <label class="{{ $bayarVal === 'nanti' && ! $tiket ? 'aktif' : '' }}">
                        <input type="radio" name="bayar" value="nanti" @checked($bayarVal === 'nanti' && ! $tiket) @disabled($tiket)>
                        <span class="t">Ditagih nanti<span class="d">Sisa dilunasi saat check-in atau check-out</span></span>
                    </label>
                    <label class="{{ $bayarVal === 'sekarang' || $tiket ? 'aktif' : '' }}">
                        <input type="radio" name="bayar" value="sekarang" @checked($bayarVal === 'sekarang' || $tiket)>
                        <span class="t">Dibayar sekarang<span class="d">Pesanan jadi lunas; bukti bayar wajib</span></span>
                    </label>
                </div>
                <div id="bukti-box" hidden>
                    @include('partials.foto-uploader', [
                        'name'    => 'foto_bayar_tambahan',
                        'fotoUrl' => null,
                        'ikon'    => 'clipboard',
                        'judul'   => 'Bukti pembayaran selisih',
                        'pesan'   => 'Belum ada bukti pembayaran.',
                        'hint'    => 'Wajib bila dibayar sekarang.',
                    ])
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ \App\Support\Panel::rute('rooms.index', ['tanggal' => $booking->check_in->toDateString(), 'bulan' => $booking->check_in->format('Y-m')]) }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-accent" id="btn-pindah">Pindahkan</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const AVAIL_URL = @json(\App\Support\Panel::rute('rooms.available'));
    const TOTAL     = @json($totalPesanan);
    const DISKON    = @json($booking->diskonPesanan());
    const PERSEN    = @json($booking->diskonPersen());
    const TAGIHAN   = @json($potret['tagihan']);
    const DIBAYAR   = @json($potret['dibayar']);
    const TIKET     = @json($tiket);

    const hitung    = document.getElementById('pk-hitung');
    const bayarBox  = document.getElementById('bayar-box');
    const bayarGrup = document.getElementById('bayar-grup');
    const buktiBox  = document.getElementById('bukti-box');
    const buktiInput = buktiBox.querySelector('input[type="file"]');
    const tombol    = document.getElementById('btn-pindah');

    const rupiah = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
    const tanda  = n => (n > 0 ? '+' : n < 0 ? '−' : '') + rupiah(Math.abs(n));
    const nominalPersen = (total, persen) => (persen <= 0 || total <= 0) ? 0 : Math.round(total * Math.min(100, persen) / 100 / 1000) * 1000;

    const baris = Array.from(document.querySelectorAll('.kmr-pindah')).map(function (el) {
        return {
            el,
            id: parseInt(el.dataset.id, 10),
            room: parseInt(el.dataset.room, 10),
            tarif: parseInt(el.dataset.tarif, 10),
            awal: el.dataset.awal,
            aktif: el.querySelector('.js-aktif'),
            isi: el.querySelector('.kmr-pindah-isi'),
            mulai: el.querySelector('.js-mulai'),
            kamar: el.querySelector('.js-kamar'),
            bf: el.querySelector('.js-bf'),
            hint: el.querySelector('.js-hint'),
            terisi: new Set(), penghuni: {},
        };
    });
    const kamarPesanan = new Set(baris.map(r => r.room));

    function malam(r) { const op = r.mulai.selectedOptions[0]; return op ? parseInt(op.dataset.malam, 10) : 1; }
    function seluruhnya(r) { return r.mulai.value === r.awal; }

    function bfDipilih(r) {
        const op = r.kamar.selectedOptions[0];
        const wajib = !!op && op.dataset.bfWajib === '1';
        r.bf.querySelectorAll('input').forEach(function (inp) {
            if (wajib) { inp.checked = inp.value === '1'; inp.disabled = inp.value === '0'; } else { inp.disabled = false; }
        });
        const pilih = r.bf.querySelector('input:checked');
        return wajib || (pilih ? pilih.value === '1' : true);
    }

    function tarifBaru(r) {
        const op = r.kamar.selectedOptions[0];
        if (!op || !op.value) return null;
        return parseInt(bfDipilih(r) ? op.dataset.harga : op.dataset.hargaTanpaBf, 10);
    }

    function syncKamar(r) {
        r.kamar.querySelectorAll('option').forEach(function (op) {
            if (!op.value || parseInt(op.value, 10) === r.room) return;
            const id = parseInt(op.value, 10);
            const nama = r.penghuni[id] || [];
            const isi = r.terisi.has(id);
            // Seluruh masa menginap: satu penghuni masih bisa ditukar.
            // Dipecah per hari: kamar tujuan harus kosong.
            const bisaTukar = isi && seluruhnya(r) && nama.length === 1;
            op.disabled = isi && !bisaTukar;
            op.textContent = op.dataset.nomor + (
                !isi ? '' : bisaTukar ? ' — terisi: ' + nama[0] + ' (akan ditukar)' : ' — terisi' + (nama.length ? ': ' + nama.join(', ') : '')
            );
        });
        const dipilih = r.kamar.selectedOptions[0];
        if (dipilih && dipilih.value && dipilih.disabled) r.kamar.value = '';

        const op = r.kamar.selectedOptions[0];
        const tujuan = op && op.value ? parseInt(op.value, 10) : NaN;
        const tukar = op && op.textContent.indexOf('akan ditukar') !== -1;
        r.hint.classList.toggle('tukar', tukar);
        r.hint.textContent = tukar
            ? 'Penghuni kamar ' + (op.dataset.nomor || '').split(' ')[0] + ' otomatis dipindah ke kamar ' + r.el.querySelector('.no').textContent + '.'
            : kamarPesanan.has(tujuan)
                ? 'Kamar ini milik pesanan yang sama — keduanya akan bertukar.'
                : 'Ketersediaan dihitung untuk malam-malam yang dipindah.';
    }

    function muat(r) {
        const url = new URL(AVAIL_URL, window.location.origin);
        url.searchParams.set('check_in', r.mulai.value);
        url.searchParams.set('durasi', malam(r));
        url.searchParams.set('ignore', r.id);
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(res => res.ok ? res.json() : Promise.reject())
            .then(function (data) {
                r.terisi = new Set((data.occupied || []).map(Number));
                r.penghuni = data.penghuni || {};
                syncKamar(r);
                hitungUang();
            })
            .catch(function () {});
    }

    function hitungUang() {
        const aktif = baris.filter(r => r.aktif.checked);
        tombol.textContent = aktif.length > 1 ? 'Pindahkan ' + aktif.length + ' Kamar' : 'Pindahkan';

        const lengkap = aktif.filter(r => tarifBaru(r) !== null);
        if (lengkap.length === 0) { hitung.hidden = true; bayarBox.hidden = true; return; }

        const selisih = lengkap.reduce((s, r) => s + (tarifBaru(r) - r.tarif) * malam(r), 0);
        const totalBaru = TOTAL + selisih;
        const diskonBaru = PERSEN > 0 ? nominalPersen(totalBaru, PERSEN) : DISKON;
        const tagihanBaru = Math.max(0, totalBaru - diskonBaru);
        const sisa = Math.max(0, tagihanBaru - DIBAYAR);

        document.getElementById('h-jumlah').textContent = lengkap.map(r => r.el.querySelector('.no').textContent).join(', ');
        const elS = document.getElementById('h-selisih');
        elS.textContent = tanda(selisih);
        elS.className = selisih > 0 ? 'naik' : (selisih < 0 ? 'turun' : '');
        document.getElementById('h-tagihan').textContent = rupiah(TAGIHAN) + ' → ' + rupiah(tagihanBaru);
        document.getElementById('h-dibayar').textContent = rupiah(DIBAYAR);
        document.getElementById('h-sisa').textContent = rupiah(sisa);

        let catatan = '';
        if (selisih > 0 && sisa > 0) catatan = 'Tamu harus membayar lebih. Pilih apakah selisihnya dibayar sekarang atau ditagih nanti.';
        else if (DIBAYAR > tagihanBaru) catatan = 'Tagihan baru lebih kecil dari yang sudah dibayar: tamu kelebihan bayar ' + rupiah(DIBAYAR - tagihanBaru) + '.';
        else if (selisih === 0) catatan = 'Tarifnya sama — tidak ada perubahan tagihan.';
        document.getElementById('h-catatan').textContent = catatan;
        hitung.hidden = false;

        const perluBayar = selisih > 0 && sisa > 0;
        bayarBox.hidden = !perluBayar;
        const sekarang = TIKET || (bayarGrup.querySelector('input:checked') || {}).value === 'sekarang';
        buktiBox.hidden = !(perluBayar && sekarang);
        if (buktiBox.hidden && buktiInput) buktiInput.value = '';
        bayarGrup.querySelectorAll('label').forEach(l => l.classList.toggle('aktif', l.querySelector('input').checked));
    }

    baris.forEach(function (r) {
        r.aktif.addEventListener('change', function () {
            r.isi.hidden = !r.aktif.checked;
            r.el.classList.toggle('aktif', r.aktif.checked);
            hitungUang();
        });
        r.mulai.addEventListener('change', () => muat(r));
        r.kamar.addEventListener('change', () => { syncKamar(r); hitungUang(); });
        r.bf.addEventListener('change', hitungUang);
        muat(r);
    });
    bayarGrup.addEventListener('change', hitungUang);
    hitungUang();
})();
</script>
@endpush
