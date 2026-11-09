# NyamanSuperApp

Aplikasi web manajemen penginapan — evolusi dari proyek "Bukit Bintang" dengan tema gelap **hitam + gradien ungu + putih** dan fitur yang jauh lebih banyak: **Stok**, **Kamar**, dan **Laporan**.

Dibangun dengan **Laravel 12**, **PHP 8.2+**, **SQLite**, dan Blade murni (CSS hand-written, tanpa framework CSS). Font: Plus Jakarta Sans + JetBrains Mono.

## Cara Menjalankan

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Lalu buka **http://localhost:8000**.

> File `.env`, folder `vendor/`, database, log, dan semua foto unggahan (KTP, bukti bayar, absensi) **sengaja tidak ikut di repo** — lihat `.gitignore`. Setiap komputer/server memakai `.env`-nya sendiri.
>
> `php artisan storage:link` **tidak diperlukan** — foto KTP sengaja disimpan di penyimpanan privat, bukan folder publik (lihat *Keamanan Foto KTP* di bawah).

## Akun

Tidak ada halaman register publik dan **tidak ada kredensial apa pun yang tertanam di kode** — halaman login tidak menampilkan atau mengisi otomatis email/kata sandi.

`php artisan db:seed` membuat tepat dua akun, satu `admin` dan satu `staff`:

- Kalau `ADMIN_EMAIL`/`ADMIN_PASSWORD` (dan `STAFF_*`) diisi di `.env`, nilai itu yang dipakai.
- Kalau dikosongkan, email diturunkan dari `APP_URL` dan **kata sandi acak 16 karakter dicetak sekali** di terminal saat seeding. Catat saat itu juga — sandi tidak bisa ditampilkan lagi.
- Menjalankan ulang `db:seed` tidak menimpa kata sandi akun yang sudah ada, jadi tidak akan mengunci pemilik akun secara diam-diam.

Mengelola akun setelahnya:

```bash
php artisan user:create     # buat akun baru / ubah kata sandi (input tersembunyi)
php artisan user:list       # lihat semua akun & perannya
```

Setelah akun terbentuk, kosongkan kembali `ADMIN_PASSWORD`/`STAFF_PASSWORD` di `.env` agar kata sandi tidak tersimpan sebagai teks biasa.

> Data contoh (booking, stok, laporan) **hanya dibuat di luar `APP_ENV=production`**. Di server produksi, seeder hanya membuat 20 kamar dan dua akun tersebut.

## Alur Aplikasi

```
Landing (/) → Login (/login?as=admin|staff) → Dashboard (/dashboard)
                                                ├─ Admin : 📦 Stok · 🛏️ Kamar (kalender) · 📮 Laporan
                                                └─ Staff : 📦 Stok · 🛏️ Kamar (input)    · 📮 Laporan
```

`/dashboard` adalah halaman dashboard sungguhan berisi kartu fitur sesuai role. Middleware `role:admin` / `role:staff` menolak akses lintas peran dengan 403.

## Tema Tampilan

Tema gelap: latar **hitam** ber-tint ungu, aksen **gradien ungu**, teks **putih**. Seluruh warna dideklarasikan sebagai variabel CSS di `:root` (`layouts/panel.blade.php`, dan disamakan di `welcome`, `login`, `403` yang punya CSS sendiri) — tidak ada warna yang di-hardcode di komponen.

| Token | Nilai | Dipakai untuk |
|---|---|---|
| `--bg` / `--bg-2` | `#0a0710` / `#100b1a` | latar halaman |
| `--surface` … `--surface-3` | `#16101f` → `#251c34` | kartu, modal, elevasi |
| `--grad` | `#7c3aed → #5b21b6` | permukaan **berisi teks putih** (tombol utama) |
| `--grad-bright` | `#a78bfa → #7c3aed` | elemen dekoratif (dot brand, teks ber-gradien) |
| `--accent` / `--accent-2` | `#a78bfa` / `#d8b4fe` | aksen utama & sekunder |
| `--text` / `--muted` | `#f6f4fb` / `#9b93ad` | teks putih & redup |
| `--ok` / `--alert` / `--danger` / `--info` | hijau / merah / merah mawar / biru | status fungsional |

Dua gradien dipisah karena alasan keterbacaan: gradien cerah tidak cukup kontras untuk teks putih berukuran tombol, jadi permukaan yang memuat teks memakai `--grad` yang lebih pekat. Semua pasangan warna teks/latar diverifikasi **lulus WCAG AA** (rasio terendah 5.70).

### Ikon

Tidak ada emoji di antarmuka. Semua ikon berupa **SVG garis monokrom** dari satu partial:

```blade
@include('partials.icon', ['name' => 'box'])
@include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm'])
```

Ikon mewarisi warna teks lewat `currentColor` (`stroke="currentColor"`, `fill="none"`) dan nama yang tidak dikenal jatuh ke ikon cadangan alih-alih merusak halaman.

**Skala tunggal** — dipakai konsisten sesuai ukuran teks di sekitarnya:

| Kelas | Ukuran | Dipakai di |
|---|---|---|
| `ic-xs` | 14px | `.btn-sm`, badge status, chevron accordion, tautan kecil |
| `ic-sm` | 16px | tombol biasa (`.btn`), toggle, stepper, kolom pencarian |
| `ic` (default) | 18px | tombol bundar navigasi, flash, tutup lightbox |
| `ic-lg` | 22px | callout/catatan, ikon modal |
| `ic-xl` | 26px | ikon kartu dashboard |

Ukuran ditulis sebagai **atribut `width`/`height` pada elemen `<svg>`**, bukan hanya lewat CSS. Ini penting: halaman `login`, `welcome`, dan `403` tidak memuat stylesheet panel, dan SVG tanpa dimensi akan melebar memenuhi kontainernya. Aturan CSS-nya sendiri hidup di `partials/icon-css.blade.php` yang di-include keempat tempat itu, jadi tidak bisa lagi menyimpang. Ukuran khusus bisa dipaksa lewat `['size' => 34]`.

Untuk baris yang dibangun lewat JavaScript (form stok multi-baris), markup ikon dibaca dari elemen sumber tersembunyi (`#ic-src-*`) — menyisipkan `@include` langsung ke dalam literal string JS akan memutus string tersebut dan mematikan seluruh blok skrip.

## Keamanan Foto KTP

Foto KTP adalah data pribadi, jadi tidak pernah disajikan sebagai berkas statis:

- Disimpan di **disk privat** `local` (`storage/app/ktp`), bukan disk `public`. Tidak ada URL yang bisa dibuka langsung.
- Disajikan lewat route `GET /ktp/{booking}` (`KtpController`) yang berada di balik middleware `auth`, dengan penjagaan path (`ktp/` saja, tanpa `..`) dan header `Cache-Control: private, no-store` agar tidak mengendap di cache browser atau proxy.
- **Kompresi otomatis dua lapis.** Di peramban, foto diperkecil lewat canvas sebelum dikirim (sisi terpanjang 1600 px, JPEG mutu 0.82) sehingga unggahan ringan di kuota staff. Di server, `App\Support\FotoKompresor` memampatkan ulang dengan aturan yang sama sebagai pengaman — jadi berkas besar tetap tidak bisa masuk walau JavaScript dimatikan. Hasil khasnya sekitar 90–350 KB per foto (hemat 80–95%), dan teks NIK tetap terbaca. Bila ekstensi GD tidak aktif di server, berkas asli disimpan apa adanya agar fitur tidak mati.
- **EXIF dibuang.** Encode ulang menghapus seluruh metadata, termasuk koordinat GPS yang sering disisipkan kamera ponsel; orientasi potret dikoreksi lebih dulu agar foto tidak miring.
- **Retensi:** `php artisan ktp:purge --days=30` menghapus foto milik booking yang sudah lama check-out; jadwalkan lewat cron. Tambahkan `--dry-run` untuk melihat dampaknya lebih dulu. Berkas yang masih dipakai booking lain dalam satu grup pesanan tidak ikut terhapus.
- **Pindahan dari versi lama:** `php artisan ktp:secure` memindahkan foto yang sebelumnya tersimpan di `storage/app/public/ktp` ke penyimpanan privat. Path di database tidak berubah.

HTTPS wajib aktif di produksi. Penyimpanan data pribadi seperti KTP tunduk pada UU No. 27/2022 tentang Pelindungan Data Pribadi — sisi teknisnya sudah ditangani di sini, tapi kewajiban Anda sebagai pengendali data (pemberitahuan ke tamu, dasar pemrosesan, masa simpan) sebaiknya dikonsultasikan dengan pihak yang kompeten.

## Menjalankan di Windows

Panduan lengkap dari nol (PHP, Composer, VS Code, terminal, dan penanganan masalah umum) ada di **[SETUP-WINDOWS.md](SETUP-WINDOWS.md)**.

Ringkasnya: proyek ini **tidak butuh Node.js, MySQL, maupun Docker** — cukup PHP 8.2+, Composer, dan ekstensi `pdo_sqlite`, `sqlite3`, `gd`, `exif`, `zip`, `mbstring`, `fileinfo`.

## Deployment

Panduan lengkap untuk cPanel / shared hosting ada di **[DEPLOY-CPANEL.md](DEPLOY-CPANEL.md)**, beserta berkas pendukung di folder `deploy/`:

| Berkas | Kegunaan |
|---|---|
| `deploy/env.production.example` | contoh `.env` produksi (debug mati, cookie aman, path SQLite absolut) |
| `deploy/index-public_html.php` | front controller bila document root tidak bisa diarahkan ke `public/` |
| `deploy/htaccess-public_html` | `.htaccess` dengan paksa-HTTPS, penolakan akses `.env`/`.sqlite`, dan header keamanan |

## Jadwal Shift & Absensi

Menggantikan kebiasaan mengetik jadwal di grup chat.

**Sisi staff** (`/staff/jadwal` dan `/staff/absensi`):

- **Susun jadwal H-1.** Halaman jadwal terbuka dengan tanggal **besok** sebagai bawaan. Tiap karyawan punya centang "bertugas" plus jam mulai/selesai, dengan preset satu klik untuk shift yang sering dipakai (06.00–15.00, 08.00–17.00, 09.00–18.00, 12.00–21.00, 21.00–07.00). Karyawan yang tidak dicentang otomatis berarti libur, dan baris NB terbentuk sendiri: `NB: Ilham libur` atau `NB: Full Team` — persis format yang selama ini dipakai.
- **Absen dengan foto.** Pada hari-H, buka daftar absensi lalu tekan "Absen Sekarang" untuk mengambil selfie langsung dari kamera (atau pilih berkas). Fotonya dikompres di peramban dan di server, sama seperti foto KTP.
- **Kelola karyawan.** Tambah nama baru, atau nonaktifkan yang sudah keluar. Karyawan tidak pernah dihapus supaya riwayat jadwal dan absensinya tetap utuh.

**Status telat dihitung otomatis** dari jam mulai shift, memakai **waktu server** — jam ponsel yang dimundurkan tidak bisa dipakai menghindari status telat. Perbandingannya dibulatkan ke menit: masuk 07.00 lalu absen 07:00:59 masih tepat waktu, sedangkan 07:01 tercatat *Telat 1 menit*. Nilai keterlambatan disimpan saat absen terjadi, bukan dihitung ulang, sehingga mengubah jadwal di kemudian hari tidak menggeser riwayat kehadiran. Shift lintas tengah malam (21.00–07.00) dikenali otomatis.

**Sisi admin** — dashboard menampilkan **Jadwal Hari Ini** sebagai kartu vertikal di samping kartu fitur, berisi urutan shift, ringkasan hadir/telat/belum absen, dan baris NB libur. Panel ini khusus admin; dashboard staff tidak menampilkannya. Menekan sebuah nama membuka detailnya: jam shift, status, waktu absen sesungguhnya, siapa yang menginput, dan foto buktinya (klik untuk memperbesar). Admin hanya melihat, tidak bisa mengubah.

Foto absensi disimpan privat di `storage/app/absensi` dan hanya bisa dibuka lewat route berautentikasi, sama seperti foto KTP. Retensinya: `php artisan absensi:purge --days=60` menghapus fotonya saja — jam dan status kehadiran tetap tersimpan sebagai riwayat.

## Sarapan (Breakfast)

Setiap pesanan punya pilihan **Dengan Breakfast** atau **Tanpa Breakfast**, dipilih sekali di form booking dan berlaku untuk seluruh kamar dalam pesanan itu. Pilihannya muncul di daftar booking staff, panel tanggal admin (beserta ringkasan "*N* dari *M* kamar terisi memesan breakfast" untuk persiapan dapur), detail entri admin, halaman check-in, dan rincian pendapatan harian.

> Saat ini breakfast **tidak menambah tarif** karena harganya belum ditentukan. Kalau ada biaya tambahan per kamar per malam, beri tahu angkanya — perhitungan pendapatan tinggal menyesuaikan.

## Diskon

Potongan harga diisi langsung di formulir booking, tepat sebelum status pembayaran.

- **Dua satuan: rupiah atau persen.** Pilih lewat tombol **Rupiah / Persen**, atau pakai tombol cepat 5% / 10% / 15% / 20%. Pada mode persen, nominalnya dihitung server (`Booking::nominalDariPersen()`, dibulatkan ke ribuan) — nominal kiriman peramban diabaikan, jadi angka di layar dan angka tersimpan selalu sama.
- **Diskon memotong tagihan, bukan menambah uang masuk.** `tagihan = total kamar − diskon`. Laporan pendapatan tidak pernah menghitung potongan sebagai pemasukan.
- **Milik satu pesanan** — nominalnya disalin ke setiap baris kamar dalam grup (seperti nama tamu dan tanggal), dibaca sekali, bukan dijumlahkan.
- **Tidak boleh sama dengan atau melebihi total kamar.** Kamar gratis adalah keputusan yang harus terlihat, bukan tersamar sebagai diskon.
- **Pesanan Tiket.com dikunci** — tarifnya ditetapkan mitra.

Kolom baru: `diskon_nominal` dan `diskon_persen` (0 = potongan diisi sebagai nominal). Angkanya tampil di panel staff, panel admin, export Excel (*Total Kamar*, *Diskon*, *Diskon %*, *Tagihan Pesanan*), dan catatan invoice.

## Booking Tanggal Lampau

Tanggal check-in boleh dimundurkan sampai **7 hari** (`BookingController::MUNDUR_MAKS_HARI`). Ini untuk tamu tanpa pesanan yang datang jam 1 pagi tanggal 25: malam yang dia pakai sebenarnya malam tanggal 24, dan check-out-nya siang tanggal 25 — kalau hanya boleh memilih hari ini, malam itu tidak bisa dicatat sama sekali.

Formulir tambah booking punya tombol **"Tamu datang dini hari (malam kemarin)"** yang sekali tekan menyetel check-in = kemarin, check-out = hari ini.

Batas 7 hari menjaga agar salah ketik bulan atau tahun tidak lolos menjadi booking di masa lalu yang jauh.

## Wewenang Admin atas Kamar

Admin bisa mengerjakan **seluruh** langkah yang bisa dikerjakan staff — membuat booking, check-in, check-out, menandai late — dan tanpa batas waktu, karena koreksi memang tugasnya.

Yang **tidak** dilonggarkan adalah syarat fotonya. Admin membuka halaman yang sama persis dengan staff: check-in tetap menuntut foto KTP (dan bukti pelunasan bila belum lunas), check-out tetap menuntut foto kartu kunci, pernyataan kondisi kamar, dan bukti pembayaran bila late. **Wewenang menentukan siapa yang boleh, bukan apa yang boleh dilewati.**

Agar tidak ada dua salinan aturan SOP yang bisa menyimpang diam-diam, view-nya tetap satu; nama rutenya yang menyesuaikan diri lewat `App\Support\Panel::rute()`.

Panel harian admin juga menampilkan **empat** angka: Kamar Terisi, Kamar Kosong, **Sudah Check-in**, dan **Belum Check-in**, plus baris "N dari M kamar sudah check-out".

## Pengajuan Perubahan Booking

Staff **tidak** mengubah booking secara langsung. Kamar, tanggal, dan nama tamu adalah jejak yang dipakai untuk mengawasi — kalau bisa disunting sendiri oleh yang menjualnya, jejak itu berhenti membuktikan apa pun. Karena itu staff **mengajukan**, admin **memutuskan**.

**Satu pengajuan = satu jenis perubahan = satu persetujuan.** Admin selalu memutuskan satu hal yang jelas, bukan sekumpulan perubahan yang harus diterima atau ditolak sekaligus.

| Jenis | Yang diubah |
|---|---|
| Pindah Kamar | kamar baris itu saja |
| Perpanjang Menginap | lama menginap seluruh pesanan |
| Ubah Tanggal Menginap | tanggal masuk seluruh pesanan (lama menginap tetap) |
| Ubah Nama / Telepon Tamu | data tamu seluruh pesanan |

Cakupannya mengikuti aturan yang sudah berlaku di formulir ubah milik admin: kamar hanya berpindah untuk baris itu sendiri, sedangkan nama, telepon, dan tanggal berlaku untuk seluruh kamar dalam satu pesanan.

**Aturan yang dijaga:**

- **Alasan wajib diisi.** Inilah yang dibaca admin saat memutuskan, dan yang bisa ditelusuri belakangan.
- **Satu pengajuan tertunda per booking.** Selama belum diputus, staff tidak bisa mengajukan yang lain untuk kamar itu.
- **Ketersediaan kamar diperiksa ULANG saat persetujuan**, bukan hanya saat pengajuan. Kamar yang kosong saat staff mengajukan bisa saja sudah terisi ketika admin menekan Setujui.
- **Penolakan wajib disertai alasan** — staff perlu tahu kenapa.
- **Baris pengajuan tidak pernah dihapus.** Yang ditolak pun tetap tersimpan: "siapa pernah minta apa, dan siapa menolaknya" adalah bagian dari pengawasan.
- Pengajuan yang menjadi **usang** (booking-nya sudah diubah admin langsung) ditandai di panel, supaya admin tahu tidak ada lagi yang akan berubah.

Daftar pengajuan muncul di **halaman kamar admin**, bukan di menu terpisah: keputusannya butuh gambaran hunian hari itu, dan gambaran itu ada di sana.

## Fitur Kamar (flow terbaru)

- **Data kamar (20 kamar):** lantai 1 = 101–108 (Twin: 103/105/107, sisanya King); lantai 2 = 201–212 (Family: 202, sisanya King).
- **Grup pesanan:** satu submit bisa memesan **beberapa kamar sekaligus** untuk satu tamu. Semua kamar tersebut berbagi satu ULID di kolom `grup`, sehingga edit data tamu/tanggal berlaku ke seluruh grup dan hapus menawarkan pilihan satu kamar atau seluruh grup.
- **Staff — halaman Kamar:** hanya tiga bagian — tombol **＋ Tambah Kamar** (atas) → **kalender bulanan** → **daftar booking untuk tanggal terpilih**. Booking hanya bisa dibuat untuk hari ini atau tanggal ke depan.
- **Staff — Tamu Check-in:** tombol oranye muncul **satu kali per grup** dan hanya bila grup itu belum check-in. Unggah foto KTP lewat **📷 Buka Kamera** (langsung membuka kamera belakang di ponsel) atau **🖼️ Pilih File**; `checked_in_at` + foto diterapkan ke **semua kamar dalam grup** sekaligus, dan tidak diminta lagi pada malam-malam berikutnya.
- **Admin — halaman Kamar (read-only):** kalender → dua angka besar **Kamar Terisi** (ungu) dan **Kamar Kosong** (oranye) → daftar entri kamar terisi dengan badge `Belum Check-in` (oranye) / `Sudah Check-in` (ungu). Klik entri untuk membuka detail lengkap: nama, telepon, tanggal check-in/out, durasi, waktu check-in aktual, kamar lain dalam grup, foto KTP (klik untuk memperbesar), dan staff penginput.
- **Definisi "terisi":** kamar dihitung terisi pada tanggal T bila `check_in <= T < check_out` — dipakai konsisten oleh badge kalender, ringkasan terisi/kosong, daftar per tanggal di kedua panel, dan perhitungan pendapatan (tamu multi-malam tampil di seluruh rentang menginapnya). Aturan ini tinggal di satu tempat saja: scope `Booking::occupyingOn()` dan trait `HandlesRoomCalendar`, sehingga angka kalender dan daftar di bawahnya tidak bisa berbeda.
- **Stok:** saat ada item berjumlah **0**, halaman stok admin & staff memunculkan popup peringatan (staff mendapat pintasan "Laporkan ke Admin"), item kosong juga ditandai **titik merah** di samping namanya, dan tabel bisa diurutkan lewat dropdown **Urutkan** (nama, jumlah menaik/menurun, atau stok habis dulu).
- **Pendapatan harian (dashboard admin):** grafik batang satu bulan penuh yang menyegarkan diri sendiri tiap menit, lengkap dengan navigasi bulan dan ringkasan (total, rata-rata per hari terisi, hari tertinggi). Klik sebuah batang untuk melihat rincian tanggal itu: rekap per tipe kamar dan daftar kamar beserta tamunya. Tarif per malam: **King & Twin Rp 300.000**, **Family Rp 450.000** (didefinisikan di `Room::HARGA`); satu kamar menghasilkan tarif satu malam untuk setiap tanggal `check_in <= T < check_out`.
- **Navigasi:** tombol **← Beranda** ada di topbar setiap halaman panel (disembunyikan di dashboard itu sendiri).

> **Upgrade dari versi sebelumnya:** jalankan `php artisan migrate:fresh --seed` (cara termudah), atau `php artisan migrate` bila ingin mempertahankan data — booking lama tanpa kolom `grup` tetap berfungsi dan diperlakukan sebagai grup berisi satu kamar.

## Fitur

### 📦 Stok (port 100% dari proyek lama)
- **Admin** (`/admin/stocks`): CRUD penuh, stats bar (jenis item, total unit, nilai stok), pencarian live di browser, export Excel mengikuti filter (`?q=`), log setiap perubahan per kolom.
- **Staff** (`/staff/stocks`): form multi-baris dinamis — autocomplete nama stok (menampilkan jumlah saat ini), toggle Tambah/Kurangi, stepper jumlah — plus tabel stok terkini read-only.
- **Log** (`/log`, khusus admin): riwayat lengkap dengan badge aksi, selisih (+hijau/−merah), filter rentang tanggal, export Excel.

### 🛏️ Kamar (21 kamar: 17 King, 3 Twin, 1 Family)
- **Staff** (`/staff/rooms`): kartu tanggal hari ini + ringkasan hunian per tipe, tambah/edit/hapus booking dengan pilihan kamar tersedia (cek ketersediaan live sesuai tanggal & durasi), check-out otomatis, data tamu, dan upload foto KTP (JPG/PNG maks 2 MB) dengan preview + lightbox.
- **Admin** (`/admin/rooms`): kalender bulanan berisi jumlah check-in per tanggal; klik tanggal (default: hari ini) menampilkan detail tiap kamar — nomor, tipe, tamu, telepon, durasi, check-out, foto KTP, dan siapa yang menginput.
- Anti double-booking: satu kamar tidak bisa punya dua booking yang rentang tanggalnya tumpang tindih (check-out dianggap eksklusif, jadi check-in baru di hari check-out lama tetap boleh).

### 📮 Laporan (tiket staff → admin)
- **Staff** (`/staff/reports`): kirim tiket (judul + isi) dan pantau status tiket sendiri.
- **Admin** (`/admin/reports`): semua tiket dengan tab filter Semua/Open/Selesai, tombol "Tandai Selesai" ↔ "Buka Kembali". Jumlah tiket Open tampil sebagai badge di kartu Laporan pada dashboard admin.

## Catatan Teknis

- Seluruh antarmuka berbahasa Indonesia; tanggal & Rupiah memakai format Indonesia (`APP_LOCALE=id`, zona waktu `Asia/Jakarta`).
- Tema didefinisikan lewat CSS variables di `resources/views/layouts/panel.blade.php` (`--accent: #8b5cf6`, dst.).
- Foto KTP disimpan di `storage/app/public/ktp` (butuh `php artisan storage:link`).
- Export Excel memakai `maatwebsite/excel` (terpasang otomatis lewat `composer install`).
- Seeder aman dijalankan ulang (`updateOrCreate` / `firstOrCreate`).
