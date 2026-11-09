@extends('layouts.panel')

@section('title', 'Tamu Check-out')

@push('head')
<style>
    .co-card { padding: 28px; max-width: 640px; }
    .co-status[hidden], #bayar-box[hidden] { display: none !important; }
    .co-lunasi { display: flex; align-items: center; gap: 9px; margin-top: 10px; font-weight: 700; color: var(--text); cursor: pointer; }
    .co-lunasi input { width: 18px; height: 18px; accent-color: var(--accent); }
    .co-total { font-size: .86rem; color: var(--muted); margin-bottom: 10px; }
    .co-total b { color: var(--text); font-variant-numeric: tabular-nums; }

    .co-summary {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px;
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 12px;
        padding: 16px; margin-bottom: 22px;
    }
    .co-summary .lbl { font-size: .7rem; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); margin-bottom: 4px; }
    .co-summary .val { font-weight: 700; }
    .co-summary .val.mono { font-variant-numeric: tabular-nums; }

    .co-status {
        display: flex; gap: 12px; align-items: flex-start; margin-bottom: 22px;
        border-radius: 14px; padding: 16px 18px; font-size: .9rem; line-height: 1.55;
    }
    .co-status .ic-lg { flex: 0 0 auto; margin-top: 1px; }
    .co-status b { display: block; font-size: .98rem; margin-bottom: 4px; }
    .co-status.awal  { background: var(--alert-soft); border: 1px solid var(--alert-line); color: var(--alert); }
    .co-status.telat { background: var(--accent-2-soft); border: 1px solid var(--border-violet); color: var(--accent-2); }
    .co-status.tepat { background: rgba(74,222,128,.10); border: 1px solid rgba(74,222,128,.28); color: var(--ok); }

    /* Pernyataan kondisi kamar: sengaja besar dan tidak tercentang otomatis,
       supaya menjadi keputusan sadar, bukan langkah yang terlewat. */
    .kondisi {
        margin-top: 22px; border: 1px solid var(--border-2); border-radius: 14px;
        background: var(--bg); padding: 18px;
    }
    .kondisi-judul {
        font-size: .75rem; letter-spacing: .06em; text-transform: uppercase;
        color: var(--muted); font-weight: 700; margin-bottom: 12px;
    }
    .kondisi-opsi {
        display: flex; gap: 12px; align-items: flex-start; cursor: pointer;
        border: 1px solid var(--border-2); border-radius: 12px; padding: 13px 15px;
        background: var(--surface); transition: border-color .15s, background .15s;
    }
    .kondisi-opsi + .kondisi-opsi { margin-top: 10px; }
    .kondisi-opsi:hover { border-color: var(--accent); background: var(--hover); }
    .kondisi-opsi.aktif { border-color: var(--accent); background: var(--accent-soft); }
    .kondisi-opsi input { width: 19px; height: 19px; accent-color: var(--accent); cursor: pointer; flex: 0 0 auto; margin-top: 1px; }
    .kondisi-opsi .t { font-weight: 700; font-size: .95rem; }
    .kondisi-opsi .d { display: block; font-size: .8rem; color: var(--muted); font-weight: 500; margin-top: 3px; }

    .rusak-box { margin-top: 18px; padding-top: 18px; border-top: 1px dashed var(--alert-line); }
    .rusak-box[hidden] { display: none !important; }
    .rusak-head {
        display: flex; gap: 9px; align-items: center; margin-bottom: 14px;
        font-size: .84rem; font-weight: 700; color: var(--alert);
    }

    .co-field { margin-top: 18px; }
    .co-field label { display: block; font-size: .8rem; font-weight: 700; color: var(--muted); margin-bottom: 8px; }
    .co-field input[type=text], .co-field textarea {
        width: 100%; font-family: inherit; font-size: .95rem; color: var(--text);
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 12px; padding: 13px 15px;
    }
    .co-field textarea { min-height: 96px; resize: vertical; line-height: 1.55; }
    .co-field input:focus, .co-field textarea:focus { outline: none; border-color: var(--accent); }

    .form-actions { display: flex; gap: 12px; margin-top: 26px; padding-top: 22px; border-top: 1px solid var(--border-2); }
</style>
@endpush

@section('content')
    <div class="page-head">
        <h1>Tamu Check-out</h1>
        <p>Kamar <strong>{{ $groupRooms->implode(', ') }}</strong> — {{ $booking->nama_tamu }}</p>
    </div>

    <div class="card co-card">
        <div class="co-summary">
            <div>
                <div class="lbl">Kamar</div>
                <div class="val mono">{{ $groupRooms->implode(', ') }}</div>
            </div>
            <div>
                <div class="lbl">Sekarang</div>
                <div class="val mono">{{ now()->format('H:i') }}</div>
            </div>
        </div>

        <form method="POST" action="{{ \App\Support\Panel::rute('rooms.checkout.store', $booking) }}" enctype="multipart/form-data"
              data-sisa="{{ $sisaBayar }}" data-lain="{{ $kamarLain }}" id="form-checkout">
            @csrf
            @php
                // Status, batas waktu, dan biaya dihitung per kamar: tamu dalam
                // satu pesanan bisa pulang tepat waktu dan terlambat bersamaan.
                $sekarang = now();
                $baris = $calon->map(function ($m) use ($sekarang) {
                    [$st] = $m->statusCheckoutPada($sekarang);
                    $biaya = $m->biayaLateCheckout($sekarang);
                    $extend = $m->keluarPadaMalamPerpanjangan($sekarang);

                    return [
                        'id'      => $m->id,
                        'nomor'   => $m->room?->nomor ?? '—',
                        'info'    => 'Batas ' . $m->batasCheckout()->translatedFormat('j M, H:i')
                            . ' · check-in ' . ($m->checked_in_at?->translatedFormat('j M, H:i') ?? '—'),
                        'label'   => $m->labelCheckoutPada($sekarang),
                        'kelas'   => $st === \App\Models\Booking::CO_TEPAT ? 'ok' : 'awas',
                        'catatan' => match (true) {
                            $extend => 'Sudah dihitung extend ' . (int) $m->perpanjang_otomatis_malam . ' malam di tagihan. Tidak ada biaya late terpisah.',
                            $biaya > 0 => 'Biaya ' . \App\Models\Booking::rupiah($biaya) . ($m->overLatePada($sekarang)
                                ? ' — lewat ' . \App\Models\Booking::JAM_LATE_SETENGAH . ', dihitung 1 malam penuh.'
                                : ' — 50% harga malam, berlaku sampai ' . \App\Models\Booking::JAM_LATE_SETENGAH . '.'),
                            default => null,
                        },
                        'data'    => ['biaya' => $biaya],
                    ];
                })->all();
                $adaBiaya = collect($baris)->sum(fn ($b) => $b['data']['biaya']) > 0;
            @endphp

            @include('partials.pilih-kamar', [
                'baris'         => $baris,
                'terpilih'      => $terpilih,
                'judul'         => 'Pilih kamar yang check-out sekarang',
                'tampilTunggal' => true,
            ])

            {{-- Sisa tagihan milik pesanan: wajib lunas saat kamar terakhir
                 keluar. Selama ada kamar lain, tamu boleh melunasinya nanti. --}}
            @if ($sisaBayar > 0)
                <div class="co-status telat" id="sisa-wajib">
                    @include('partials.icon', ['name' => 'alert', 'class' => 'ic-lg'])
                    <span>
                        <b>Masih ada sisa tagihan {{ \App\Models\Booking::rupiah($sisaBayar) }}</b>
                        {{ $booking->ringkasanBayar() }}. Tagikan sebelum tamu pulang; bukti pelunasannya wajib diunggah.
                    </span>
                </div>
                <div class="co-status telat" id="sisa-nanti" hidden>
                    @include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-lg'])
                    <span>
                        <b>Sisa tagihan {{ \App\Models\Booking::rupiah($sisaBayar) }} bisa ditagih nanti</b>
                        Masih ada kamar lain yang belum check-out, jadi sisanya wajib lunas saat kamar terakhir keluar.
                        <label class="co-lunasi">
                            <input type="checkbox" name="lunasi_sisa" value="1" id="lunasi-sisa" @checked(old('lunasi_sisa'))>
                            Tamu melunasi sisa tagihan sekarang
                        </label>
                    </span>
                </div>
            @endif

            @if ($sisaBayar > 0 || $adaBiaya)
                <div id="bayar-box">
                    <div class="co-total">Yang harus dibayar sekarang: <b id="bayar-total">—</b></div>
                    @include('partials.foto-uploader', [
                        'name'  => 'foto_pembayaran',
                        'ikon'  => 'clipboard',
                        'judul' => 'Bukti pembayaran',
                        'pesan' => 'Bukti pembayaran late check-out atau sisa tagihan.',
                        'hint'  => 'Wajib.',
                    ])
                </div>
            @endif

            @include('partials.foto-uploader', [
                'name'  => 'foto_kartu',
                'ikon'  => 'key-card',
                'judul' => 'Kartu kunci yang dikembalikan',
                'pesan' => 'Potret kartu kamar yang check-out dan dikembalikan.',
                'hint'  => 'Wajib. Pastikan nomor kartu terbaca.',
            ])

            <div class="kondisi">
                {{-- Tidak ada nilai bawaan: staff harus memilih sendiri.
                     Centang yang sudah tercentang duluan cenderung dilewati
                     begitu saja, dan justru itu yang membuat temuan hilang. --}}
                <div class="kondisi-judul">Kondisi kamar saat ditinggalkan</div>

                <label class="kondisi-opsi">
                    <input type="radio" name="kondisi" value="ok" @checked(old('kondisi') === 'ok')>
                    <span class="t">Tidak ada kerusakan atau barang tertinggal</span>
                </label>

                <label class="kondisi-opsi">
                    <input type="radio" name="kondisi" value="rusak" @checked(old('kondisi') === 'rusak')>
                    <span class="t">Ada kerusakan atau barang tertinggal
                        <span class="d">Laporannya otomatis jadi tiket untuk admin.</span>
                    </span>
                </label>

                <div class="rusak-box" id="rusak-box" hidden>
                    <div class="rusak-head">
                        @include('partials.icon', ['name' => 'alert', 'class' => 'ic-sm'])
                        Kerusakan / barang tertinggal
                    </div>

                    @include('partials.foto-uploader', [
                        'name'  => 'foto_kerusakan',
                        'ikon'  => 'camera',
                        'judul' => 'Foto temuan',
                        'pesan' => 'Potret kerusakan atau barang yang tertinggal.',
                        'hint'  => 'Wajib.',
                    ])

                    <div class="co-field">
                        <label for="catatan_kerusakan">Keterangan temuan</label>
                        <textarea name="catatan_kerusakan" id="catatan_kerusakan" maxlength="2000"
                                  placeholder="Mis. gorden robek di sisi jendela, atau charger tertinggal di laci">{{ old('catatan_kerusakan') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="co-field">
                <label for="catatan">Catatan check-out <span style="font-weight:500">(opsional)</span></label>
                <input type="text" name="catatan" id="catatan" maxlength="255"
                       value="{{ old('catatan') }}" placeholder="Mis. kartu 201-B hilang">
            </div>

            <div class="form-actions">
                <a href="{{ \App\Support\Panel::rute('rooms.index', ['tanggal' => $booking->check_in->toDateString()]) }}" class="btn btn-ghost">Batal</a>
                <button type="submit" id="btn-checkout" class="btn btn-accent">
                    @include('partials.icon', ['name' => 'log-out', 'class' => 'ic-sm'])
                    <span id="btn-label">Catat Check-out</span>
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const radios  = Array.from(document.querySelectorAll('input[name="kondisi"]'));
    const kotak   = document.getElementById('rusak-box');
    const btn     = document.getElementById('btn-checkout');
    const label   = document.getElementById('btn-label');
    const catatan = document.getElementById('catatan_kerusakan');
    const form    = document.getElementById('form-checkout');
    const kamar   = Array.from(document.querySelectorAll('.js-kamar'));
    const bayar   = document.getElementById('bayar-box');
    const total   = document.getElementById('bayar-total');
    const wajib   = document.getElementById('sisa-wajib');
    const nanti   = document.getElementById('sisa-nanti');
    const lunasi  = document.getElementById('lunasi-sisa');

    if (!radios.length || !btn) return;

    const SISA = parseInt(form.dataset.sisa, 10) || 0;
    const LAIN = parseInt(form.dataset.lain, 10) || 0;
    const rupiah = n => 'Rp ' + n.toLocaleString('id-ID');
    const terpilih = () => kamar.filter(k => k.type === 'hidden' || k.checked);

    // Yang wajib dibayar mengikuti kamar yang dicentang.
    function hitungBayar() {
        const pilih = terpilih();
        const biaya = pilih.reduce((n, k) => n + (parseInt(k.dataset.biaya, 10) || 0), 0);
        const terakhir = pilih.length === kamar.length && LAIN === 0;
        const lunas = SISA > 0 && (terakhir || (lunasi && lunasi.checked));

        if (wajib) wajib.hidden = !terakhir;
        if (nanti) nanti.hidden = terakhir;
        if (bayar) bayar.hidden = !(biaya > 0 || lunas);
        if (total) total.textContent = rupiah(biaya + (lunas ? SISA : 0));
    }

    const pilihan = () => (radios.find(r => r.checked) || {}).value || null;

    // Kotak unggah di bagian kerusakan hanya wajib bila "rusak" dipilih.
    function uploaderWajib() {
        return Array.from(document.querySelectorAll('[data-uploader]')).filter(function (k) {
            if (bayar && bayar.hidden && bayar.contains(k)) return false;
            return !(kotak && kotak.contains(k)) || pilihan() === 'rusak';
        });
    }

    function periksa() {
        hitungBayar();
        const p = pilihan();
        if (kotak) kotak.hidden = p !== 'rusak';

        radios.forEach(r => r.closest('.kondisi-opsi').classList.toggle('aktif', r.checked));

        const kotakLengkap = uploaderWajib().every(function (k) {
            const inp = k.querySelector('[data-role="main"]');
            return k.classList.contains('has-file') || (inp && inp.files && inp.files.length > 0);
        });

        const catatanLengkap = p !== 'rusak' || (catatan && catatan.value.trim().length > 0);

        // Tombol tetap terkunci selama kondisi kamar belum dinyatakan.
        const jumlah = terpilih().length;
        const lengkap = jumlah > 0 && p !== null && kotakLengkap && catatanLengkap;

        btn.disabled = !lengkap;
        btn.style.opacity = lengkap ? '' : '.45';
        btn.style.cursor  = lengkap ? '' : 'not-allowed';
        btn.title = jumlah === 0 ? 'Pilih minimal satu kamar'
            : p === null ? 'Pilih kondisi kamar terlebih dahulu'
            : (lengkap ? '' : 'Lengkapi foto dan keterangan yang diminta');

        if (label) label.textContent = (p === 'rusak' ? 'Check-out & Kirim Laporan' : 'Catat Check-out')
            + (kamar.length > 1 && jumlah > 0 ? ' (' + jumlah + ' kamar)' : '');
    }

    radios.forEach(r => r.addEventListener('change', periksa));
    kamar.forEach(k => k.addEventListener('change', periksa));
    if (lunasi) lunasi.addEventListener('change', periksa);
    if (catatan) catatan.addEventListener('input', periksa);
    document.querySelectorAll('[data-uploader] [data-role="main"]').forEach(function (inp) {
        inp.addEventListener('change', periksa);
    });

    periksa();
})();
</script>
@endpush
