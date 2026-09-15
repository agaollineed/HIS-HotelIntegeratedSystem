@php
    /**
     * Kotak unggah foto: ambil dari kamera atau pilih berkas, dengan
     * pemampatan di peramban sebelum dikirim.
     *
     * ── Kenapa tombol kamera dulu dihapus, dan kenapa sekarang aman ──
     * Versi lama memakai INPUT KEDUA berisi capture="environment", lalu
     * memindahkan berkasnya ke input utama lewat DataTransfer. Di sebagian
     * ponsel pemindahan itu gagal diam-diam: input utama tetap kosong dan
     * formulir terkirim TANPA foto.
     *
     * Sekarang tidak ada input kedua sama sekali. Di ponsel, tombol kamera
     * hanya MENYALAKAN atribut capture pada input utama lalu mengekliknya —
     * kamera bawaan menulis langsung ke input yang benar, persis seperti
     * tombol berkas. Tidak ada pemindahan, tidak ada yang bisa hilang.
     * Atribut itu dinyalakan/dimatikan tepat sebelum setiap klik, jadi kedua
     * tombol selalu deterministik:
     *
     *     Buka Kamera  -> setAttribute('capture', ...)  -> click()
     *     Pilih File   -> removeAttribute('capture')    -> click()
     *
     * Di komputer, atribut capture diabaikan peramban (tombolnya akan terasa
     * rusak karena cuma membuka dialog berkas), jadi di sana dipakai kamera
     * dalam-halaman lewat getUserMedia: pratinjau langsung + tombol jepret.
     * Jalur itu memang butuh DataTransfer, maka dukungannya DIUJI DULU saat
     * skrip dimuat, dan hasil penulisannya DIPERIKSA setelah ditulis. Bila
     * salah satunya gagal, tombol otomatis mundur ke kamera bawaan perangkat.
     *
     * ── Pemampatan ──
     * Hasil dari jalur mana pun berakhir sebagai JPEG sisi terpanjang 1600 px
     * mutu 0,82 — sama dengan App\Support\FotoKompresor di sisi server:
     *   - jalur kamera dalam-halaman: dijepret langsung pada ukuran itu;
     *   - jalur kamera bawaan & berkas: lewat kecilkan() seperti sebelumnya.
     * Server tetap memampatkan ulang sebagai pengaman, dan validasinya tidak
     * berubah (image|mimes:jpg,jpeg,png) karena yang terkirim tetap berkas
     * biasa pada input yang sama.
     *
     * Dipakai bersama oleh check-in tamu (foto KTP + bukti pelunasan),
     * check-out (kartu kembali, bukti bayar, foto temuan), absensi karyawan
     * (selfie + laci kartu), dan lampiran laporan — satu sumber kebenaran,
     * supaya perbaikan pada salah satunya berlaku untuk semuanya.
     *
     * BISA DIPAKAI BEBERAPA KALI DALAM SATU HALAMAN: setiap elemen ditandai
     * data-role (bukan id tetap) dan skripnya memasang diri ke setiap
     * [data-uploader] yang ditemukan.
     *
     * Parameter:
     *   name     - nama field (mis. 'foto_ktp' atau 'foto_kartu')
     *   fotoUrl  - URL foto yang sudah ada (opsional)
     *   ikon     - nama ikon untuk keadaan kosong (default 'id-card')
     *   judul    - judul kecil di atas kotak (opsional)
     *   pesan    - teks keadaan kosong
     *   hint     - keterangan di bawah tombol
     *   kamera   - 'environment' (kamera belakang, bawaan) atau 'user'
     *              (kamera depan, untuk selfie absensi)
     */
    $name    = $name    ?? 'foto';
    $fotoUrl = $fotoUrl ?? null;
    $ikon    = $ikon    ?? 'id-card';
    $judul   = $judul   ?? null;
    $pesan   = $pesan   ?? 'Belum ada foto.';
    $hint    = $hint    ?? 'JPG/PNG, otomatis diperkecil sebelum dikirim.';
    $kamera  = (($kamera ?? 'environment') === 'user') ? 'user' : 'environment';
@endphp

@once
@push('head')
<style>
    .uploader {
        border: 2px dashed var(--border); border-radius: 16px;
        background: var(--accent-soft); padding: 22px; text-align: center;
        transition: border-color .2s, background .2s;
    }
    .uploader.has-file { border-style: solid; background: var(--bg); }
    .uploader + .uploader { margin-top: 18px; }

    .up-judul {
        display: flex; align-items: center; justify-content: center; gap: 8px;
        font-size: .78rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase;
        color: var(--accent); margin-bottom: 16px;
    }

    .up-preview-wrap { margin-bottom: 16px; }
    .up-preview {
        width: 100%; max-width: 440px; max-height: 270px; object-fit: contain;
        border-radius: 12px; background: var(--surface-3); border: 1px solid var(--border-2);
        box-shadow: 0 18px 40px -20px var(--accent-glow); cursor: zoom-in;
    }
    .up-preview[hidden] { display: none !important; }
    .up-empty { color: var(--muted); font-size: .92rem; margin-bottom: 16px; }
    .up-empty[hidden] { display: none !important; }
    .up-empty .big { display: flex; justify-content: center; margin-bottom: 10px; color: var(--accent); }
    .up-empty .big .ic { width: 34px; height: 34px; }

    .up-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .up-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 9px; cursor: pointer;
        font-family: inherit; font-weight: 700; font-size: .92rem;
        border: none; border-radius: 12px; padding: 13px 22px;
        transition: transform .12s, box-shadow .2s, background .2s, border-color .2s;
    }
    /* Kamera = aksi utama: bukti foto sebaiknya diambil saat itu juga,
       bukan diambil dari galeri. */
    .up-btn.cam { background: var(--grad); color: #fff; box-shadow: 0 12px 26px -12px var(--accent-glow); }
    .up-btn.cam:hover { background: var(--grad-rev); transform: translateY(-1px); }
    .up-btn.file { background: transparent; color: var(--accent); border: 1px solid var(--border); }
    .up-btn.file:hover { background: var(--hover); border-color: var(--accent); transform: translateY(-1px); }

    .up-name { margin-top: 12px; font-size: .8rem; color: var(--muted); font-variant-numeric: tabular-nums; }
    .up-name[hidden] { display: none !important; }
    .up-galat { margin-top: 12px; font-size: .8rem; color: var(--danger); }
    .up-galat[hidden] { display: none !important; }
    .up-hint { margin-top: 14px; font-size: .78rem; color: var(--muted); }

    .visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

    /* ---------- Kamera dalam-halaman (komputer berkamera) ---------- */
    .cam-lapis {
        position: fixed; inset: 0; z-index: 90;
        display: flex; align-items: center; justify-content: center; padding: 18px;
        background: var(--overlay); backdrop-filter: blur(6px);
    }
    .cam-lapis[hidden] { display: none !important; }
    .cam-kotak {
        width: min(720px, 100%); background: var(--surface);
        border: 1px solid var(--border-2); border-radius: 18px; overflow: hidden;
        box-shadow: 0 50px 110px -30px rgba(0, 0, 0, .95);
    }
    .cam-head {
        display: flex; align-items: center; gap: 9px; padding: 14px 18px;
        font-size: .8rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase;
        color: var(--accent); border-bottom: 1px solid var(--border-2);
    }
    .cam-head .cam-tutup {
        margin-left: auto; width: 32px; height: 32px; border-radius: 50%; cursor: pointer;
        display: grid; place-items: center; padding: 0;
        background: var(--surface-3); color: var(--text); border: 1px solid var(--border-2);
    }
    .cam-head .cam-tutup:hover { border-color: var(--accent); color: var(--accent); }

    .cam-panggung { position: relative; background: #000; min-height: 260px; }
    .cam-panggung video {
        display: block; width: 100%; max-height: 62vh; object-fit: contain; background: #000;
    }
    /* Pratinjau selfie dicerminkan supaya tidak membingungkan; hasil jepretan
       TIDAK dicerminkan, sama seperti kamera bawaan ponsel. */
    .cam-panggung video.cermin { transform: scaleX(-1); }
    .cam-pesan {
        position: absolute; inset: 0; display: grid; place-items: center; gap: 10px;
        padding: 26px; text-align: center; color: var(--muted); font-size: .9rem; line-height: 1.6;
    }
    .cam-pesan[hidden] { display: none !important; }

    .cam-aksi { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; padding: 16px; }
    .cam-aksi [hidden] { display: none !important; }
</style>
@endpush
@endonce

<div class="uploader {{ $fotoUrl ? 'has-file' : '' }}" data-uploader data-kamera="{{ $kamera }}">
    @if ($judul)
        <div class="up-judul">
            @include('partials.icon', ['name' => $ikon, 'class' => 'ic-sm'])
            <span>{{ $judul }}</span>
        </div>
    @endif

    <div class="up-preview-wrap">
        <img data-role="preview"
             class="up-preview"
             alt="Pratinjau foto"
             src="{{ $fotoUrl ?? '' }}"
             @if ($fotoUrl) data-lightbox="{{ $fotoUrl }}" @else hidden @endif>
        <div class="up-empty" data-role="empty" @if ($fotoUrl) hidden @endif>
            <span class="big">@include('partials.icon', ['name' => $ikon])</span>
            {{ $pesan }}
        </div>
    </div>

    <div class="up-actions">
        <button type="button" class="up-btn cam" data-role="btn-cam">
            @include('partials.icon', ['name' => 'camera', 'class' => 'ic']) Buka Kamera
        </button>
        <button type="button" class="up-btn file" data-role="btn-file">
            @include('partials.icon', ['name' => 'image', 'class' => 'ic']) Pilih File
        </button>
    </div>

    <div class="up-name" data-role="name" hidden></div>
    <div class="up-galat" data-role="galat" hidden></div>

    {{-- input utama yang benar-benar terkirim --}}
    <input type="file" name="{{ $name }}" data-role="main" class="visually-hidden" accept="image/png,image/jpeg">

    <div class="up-hint">{{ $hint }}</div>
</div>

@once
@push('scripts')
{{-- Satu jendela kamera dipakai bergantian oleh semua kotak unggah di halaman ini.
     Ditulis sebagai markup Blade (bukan string di dalam JavaScript) supaya ikonnya
     tetap lewat partials.icon. --}}
<div class="cam-lapis" data-cam-modal hidden>
    <div class="cam-kotak">
        <div class="cam-head">
            @include('partials.icon', ['name' => 'camera', 'class' => 'ic-sm'])
            <span data-cam-judul>Ambil Foto</span>
            <button type="button" class="cam-tutup" data-cam-batal title="Tutup">
                @include('partials.icon', ['name' => 'x', 'class' => 'ic-sm'])
            </button>
        </div>

        <div class="cam-panggung">
            <video data-cam-video playsinline muted autoplay></video>
            <div class="cam-pesan" data-cam-pesan hidden></div>
        </div>

        <div class="cam-aksi">
            <button type="button" class="up-btn cam" data-cam-jepret>
                @include('partials.icon', ['name' => 'camera', 'class' => 'ic']) Jepret
            </button>
            <button type="button" class="up-btn file" data-cam-ganti hidden>
                @include('partials.icon', ['name' => 'undo', 'class' => 'ic']) Ganti Kamera
            </button>
            <button type="button" class="up-btn file" data-cam-bawaan hidden>
                @include('partials.icon', ['name' => 'image', 'class' => 'ic']) Pakai Kamera Perangkat
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    // Samakan dengan App\Support\FotoKompresor di sisi server.
    const MAKS_SISI = 1600;
    const MUTU      = 0.82;

    const kb   = b => (b / 1024).toFixed(0) + ' KB';
    const mb   = b => (b / 1048576).toFixed(2) + ' MB';
    const ukur = b => b >= 1048576 ? mb(b) : kb(b);

    /**
     * Uji sekali di awal apakah peramban benar-benar bisa menulis berkas ke
     * sebuah <input type="file">. Inilah operasi yang dulu gagal diam-diam di
     * sebagian ponsel; kalau di sini saja sudah gagal, jalur yang bergantung
     * padanya tidak akan pernah dipakai.
     */
    const bisaTulisInput = (function () {
        try {
            const dt = new DataTransfer();
            dt.items.add(new File(['x'], 'x.jpg', { type: 'image/jpeg' }));
            const uji = document.createElement('input');
            uji.type = 'file';
            uji.files = dt.files;
            return uji.files.length === 1;
        } catch (err) {
            return false;
        }
    })();

    const adaGetUserMedia = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

    // Perangkat layar sentuh memakai kamera BAWAAN: kualitas fokus/blitz-nya
    // jauh lebih baik untuk membaca NIK di KTP atau nomor kartu kunci, dan
    // jalurnya tidak menyentuh DataTransfer sama sekali.
    const ponsel = (navigator.userAgentData && typeof navigator.userAgentData.mobile === 'boolean')
        ? navigator.userAgentData.mobile
        : !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);

    const pakaiKameraHalaman = !ponsel && adaGetUserMedia && bisaTulisInput;

    /**
     * Perkecil foto di peramban sebelum dikirim: unggahan jadi ringan
     * (penting untuk kuota di ponsel) dan server tidak perlu menerima
     * berkas 5 MB hanya untuk dibuang lagi. Server tetap memampatkan
     * ulang sebagai pengaman.
     */
    async function kecilkan(file) {
        if (!window.HTMLCanvasElement || !file.type.startsWith('image/')) return null;

        try {
            let bitmap;
            if (window.createImageBitmap) {
                bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
            } else {
                bitmap = await new Promise((res, rej) => {
                    const img = new Image();
                    img.onload = () => res(img);
                    img.onerror = rej;
                    img.src = URL.createObjectURL(file);
                });
            }

            const w = bitmap.width, h = bitmap.height;
            const skala = Math.min(1, MAKS_SISI / Math.max(w, h));

            // Sudah kecil dan sudah JPEG -> tidak perlu diproses ulang.
            if (skala === 1 && file.type === 'image/jpeg' && file.size < 400 * 1024) return null;

            const canvas = document.createElement('canvas');
            canvas.width  = Math.max(1, Math.round(w * skala));
            canvas.height = Math.max(1, Math.round(h * skala));

            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            if (bitmap.close) bitmap.close();

            const blob = await new Promise(res => canvas.toBlob(res, 'image/jpeg', MUTU));
            if (!blob || blob.size >= file.size) return null;

            return new File([blob], 'foto.jpg', { type: 'image/jpeg', lastModified: Date.now() });
        } catch (err) {
            return null; // apa pun yang gagal -> pakai berkas asli
        }
    }

    /* ================= Jendela kamera dalam-halaman ================= */

    const modal = document.querySelector('[data-cam-modal]');

    const kamera = {
        aktif: false,
        arus: null,          // MediaStream yang sedang jalan
        mode: 'environment',
        selesai: null,       // fn(File) bila berhasil
        batal: null,         // fn() bila ditutup / mundur ke kamera bawaan
    };

    function elModal(sel) { return modal ? modal.querySelector(sel) : null; }

    function hentikanArus() {
        if (!kamera.arus) return;
        kamera.arus.getTracks().forEach(t => { try { t.stop(); } catch (e) {} });
        kamera.arus = null;
        const v = elModal('[data-cam-video]');
        if (v) v.srcObject = null;
    }

    function tutupKamera(jalankanBatal) {
        hentikanArus();
        kamera.aktif = false;
        if (modal) modal.hidden = true;
        const batal = kamera.batal;
        kamera.selesai = null;
        kamera.batal = null;
        if (jalankanBatal && batal) batal();
    }

    function pesanGalat(err) {
        const nama = err && err.name ? err.name : '';
        if (nama === 'NotAllowedError' || nama === 'SecurityError') {
            return 'Izin kamera ditolak. Izinkan akses kamera di pengaturan peramban, lalu coba lagi.';
        }
        if (nama === 'NotFoundError' || nama === 'OverconstrainedError' || nama === 'DevicesNotFoundError') {
            return 'Tidak ada kamera yang terdeteksi di perangkat ini.';
        }
        if (nama === 'NotReadableError' || nama === 'TrackStartError') {
            return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi itu lalu coba lagi.';
        }
        return 'Kamera tidak bisa dibuka di peramban ini.';
    }

    function tampilkanPesan(teks) {
        const p = elModal('[data-cam-pesan]');
        const jepret = elModal('[data-cam-jepret]');
        const ganti  = elModal('[data-cam-ganti]');
        const bawaan = elModal('[data-cam-bawaan]');
        if (p) { p.textContent = teks; p.hidden = false; }
        if (jepret) jepret.hidden = true;
        if (ganti)  ganti.hidden  = true;
        if (bawaan) bawaan.hidden = false;
    }

    async function nyalakan(mode) {
        hentikanArus();

        const video = elModal('[data-cam-video]');
        const pesan = elModal('[data-cam-pesan]');
        if (!video) return false;

        if (pesan) { pesan.textContent = 'Menyalakan kamera…'; pesan.hidden = false; }

        let arus = null;
        try {
            arus = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: mode }, width: { ideal: 1920 }, height: { ideal: 1080 } },
                audio: false,
            });
        } catch (err) {
            // Sebagian webcam menolak facingMode; coba sekali lagi tanpa syarat itu.
            try {
                arus = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            } catch (err2) {
                tampilkanPesan(pesanGalat(err2));
                return false;
            }
        }

        kamera.arus = arus;
        kamera.mode = mode;
        video.srcObject = arus;
        video.classList.toggle('cermin', mode === 'user');

        try { await video.play(); } catch (e) { /* autoplay kadang ditolak; frame tetap terbaca */ }

        await new Promise(res => {
            if (video.videoWidth > 0) return res();
            const cek = () => { if (video.videoWidth > 0) { bersih(); res(); } };
            const bersih = () => {
                video.removeEventListener('loadedmetadata', cek);
                video.removeEventListener('canplay', cek);
            };
            video.addEventListener('loadedmetadata', cek);
            video.addEventListener('canplay', cek);
            setTimeout(() => { bersih(); res(); }, 4000);
        });

        if (pesan) pesan.hidden = true;
        const jepret = elModal('[data-cam-jepret]');
        const bawaan = elModal('[data-cam-bawaan]');
        if (jepret) jepret.hidden = false;
        if (bawaan) bawaan.hidden = true;

        // Tombol ganti kamera hanya berguna kalau kameranya memang lebih dari satu.
        try {
            const daftar = await navigator.mediaDevices.enumerateDevices();
            const jml = daftar.filter(d => d.kind === 'videoinput').length;
            const ganti = elModal('[data-cam-ganti]');
            if (ganti) ganti.hidden = jml < 2;
        } catch (e) { /* biarkan tersembunyi */ }

        return true;
    }

    async function bukaJendelaKamera(mode, judul, onFile, onBatal) {
        if (!modal) { onBatal(); return; }

        kamera.aktif   = true;
        kamera.selesai = onFile;
        kamera.batal   = onBatal;

        const j = elModal('[data-cam-judul]');
        if (j) j.textContent = judul;

        modal.hidden = false;
        await nyalakan(mode);
    }

    /** Ambil satu bingkai dari video, langsung pada ukuran & mutu akhir. */
    function jepretFile() {
        const video = elModal('[data-cam-video]');
        if (!video || !video.videoWidth) return Promise.resolve(null);

        const w = video.videoWidth, h = video.videoHeight;
        const skala = Math.min(1, MAKS_SISI / Math.max(w, h));

        const canvas = document.createElement('canvas');
        canvas.width  = Math.max(1, Math.round(w * skala));
        canvas.height = Math.max(1, Math.round(h * skala));

        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        return new Promise(res => {
            canvas.toBlob(blob => {
                if (!blob) return res(null);
                res(new File([blob], 'foto.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
            }, 'image/jpeg', MUTU);
        });
    }

    if (modal) {
        const jepret = elModal('[data-cam-jepret]');
        const ganti  = elModal('[data-cam-ganti]');
        const bawaan = elModal('[data-cam-bawaan]');

        if (jepret) {
            jepret.addEventListener('click', async function () {
                jepret.disabled = true;
                const file = await jepretFile();
                jepret.disabled = false;

                if (!file) { tampilkanPesan('Gambar dari kamera belum siap. Tunggu sebentar lalu jepret lagi.'); return; }

                const lanjut = kamera.selesai;
                kamera.selesai = null;
                kamera.batal = null;
                tutupKamera(false);
                if (lanjut) lanjut(file);
            });
        }

        if (ganti) {
            ganti.addEventListener('click', function () {
                nyalakan(kamera.mode === 'user' ? 'environment' : 'user');
            });
        }

        // "Pakai Kamera Perangkat" = tutup jendela lalu jalankan jalur cadangan.
        if (bawaan) bawaan.addEventListener('click', function () { tutupKamera(true); });

        elModal('[data-cam-batal]').addEventListener('click', function () {
            kamera.batal = null; // ditutup sengaja: jangan mundur ke kamera bawaan
            tutupKamera(false);
        });

        modal.addEventListener('click', function (e) {
            if (e.target === modal) { kamera.batal = null; tutupKamera(false); }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && kamera.aktif) { kamera.batal = null; tutupKamera(false); }
        });
    }

    /* ================= Pemasangan tiap kotak unggah ================= */

    function pasang(kotak) {
        const el = r => kotak.querySelector('[data-role="' + r + '"]');

        const mainInput = el('main');
        const btnFile   = el('btn-file');
        const btnCam    = el('btn-cam');
        const preview   = el('preview');
        const empty     = el('empty');
        const nameEl    = el('name');
        const galatEl   = el('galat');

        if (!mainInput) return;

        const modeKamera = kotak.getAttribute('data-kamera') === 'user' ? 'user' : 'environment';
        const judulKamera = modeKamera === 'user' ? 'Ambil Selfie' : 'Ambil Foto';

        let sedangMenulisBalik = false;

        /**
         * Menulis input.files secara programatik TIDAK memicu event 'change'.
         * Halaman check-in dan check-out menyalakan tombol kirimnya berdasarkan
         * event itu, jadi tanpa pemberitahuan ini tombolnya tetap mati ketika
         * SEMUA foto diambil lewat kamera — sementara "1 kamera + 1 berkas"
         * lolos, karena tombol berkas memicu event aslinya.
         *
         * Event ditembakkan manual dengan penanda, supaya kotak ini tidak
         * memproses ulang berkasnya sendiri sementara pendengar lain di
         * halaman tetap menerimanya.
         */
        function beritahuPerubahan() {
            sedangMenulisBalik = true;
            try {
                mainInput.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (err) {
                const ev = document.createEvent('HTMLEvents');   // peramban lama
                ev.initEvent('change', true, false);
                mainInput.dispatchEvent(ev);
            }
            sedangMenulisBalik = false;
        }

        function galat(teks) {
            if (!galatEl) return;
            if (!teks) { galatEl.hidden = true; galatEl.textContent = ''; return; }
            galatEl.textContent = teks;
            galatEl.hidden = false;
        }

        /**
         * Tulis berkas ke input utama DAN pastikan benar-benar masuk.
         * Sebagian peramban menerima penugasan ini tanpa melempar galat tapi
         * juga tanpa efek — itulah sumber bug "formulir terkirim tanpa foto".
         */
        function taruhDiInput(file) {
            try {
                const dt = new DataTransfer();
                dt.items.add(file);
                mainInput.files = dt.files;
                return !!(mainInput.files && mainInput.files.length === 1 && mainInput.files[0].size > 0);
            } catch (err) {
                return false;
            }
        }

        function tampilkanPreview(file, infoTambahan) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.hidden = false;
                preview.setAttribute('data-lightbox', e.target.result);
                empty.hidden = true;
                kotak.classList.add('has-file');
            };
            reader.readAsDataURL(file);
            nameEl.textContent = infoTambahan;
            nameEl.hidden = false;
        }

        /**
         * Berkas pilihan pengguna sudah berada di input yang benar sejak
         * awal. Kompresi hanya MENGGANTI isinya bila berhasil; bila
         * DataTransfer tidak didukung, berkas asli tetap terkirim —
         * tidak ada lagi keadaan "formulir terkirim tanpa foto".
         */
        async function proses(file) {
            galat('');
            nameEl.textContent = 'Memproses foto…';
            nameEl.hidden = false;

            const kecil = await kecilkan(file);

            if (kecil && taruhDiInput(kecil)) {
                const hemat = Math.round((1 - kecil.size / file.size) * 100);
                tampilkanPreview(kecil, ukur(file.size) + ' → ' + ukur(kecil.size) + ' (hemat ' + hemat + '%)');
            } else {
                tampilkanPreview(file, ukur(file.size));
            }
        }

        /** Kamera bawaan perangkat: menulis LANGSUNG ke input utama. */
        function kameraBawaan() {
            mainInput.setAttribute('capture', modeKamera);
            mainInput.click();
        }

        btnFile.addEventListener('click', function () {
            galat('');
            mainInput.removeAttribute('capture');
            mainInput.click();
        });

        btnCam.addEventListener('click', function () {
            galat('');

            if (!pakaiKameraHalaman) { kameraBawaan(); return; }

            bukaJendelaKamera(modeKamera, judulKamera, function (file) {
                // Hasil jepretan sudah 1600 px / mutu 0.82 — tinggal dimasukkan.
                if (taruhDiInput(file)) {
                    tampilkanPreview(file, ukur(file.size) + ' (dari kamera)');
                    beritahuPerubahan();
                    return;
                }
                // Penulisan gagal: JANGAN diam. Mundur ke kamera bawaan.
                galat('Hasil kamera tidak bisa dimasukkan ke formulir di peramban ini. Membuka kamera perangkat…');
                kameraBawaan();
            }, kameraBawaan);
        });

        mainInput.addEventListener('change', function () {
            if (sedangMenulisBalik) { sedangMenulisBalik = false; return; }
            const f = this.files && this.files[0];
            if (!f) return;
            sedangMenulisBalik = true;
            proses(f).finally(() => { sedangMenulisBalik = false; });
        });
    }

    document.querySelectorAll('[data-uploader]').forEach(pasang);
})();
</script>
@endpush
@endonce
