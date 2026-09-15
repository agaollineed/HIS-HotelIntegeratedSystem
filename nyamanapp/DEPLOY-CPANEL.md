# Deploy NyamanSuperApp ke cPanel / Shared Hosting

Panduan ini mengasumsikan cPanel dengan akses **Terminal** atau **SSH**. Jika hosting Anda tidak menyediakannya, lihat bagian [Tanpa Terminal](#tanpa-terminal-composer-di-komputer-sendiri).

> **Uji di komputer sendiri dulu.** Aplikasi ini belum pernah dijalankan di runtime Laravel sungguhan (lingkungan pembuatannya memblokir Packagist, jadi `composer install` tidak pernah bisa dijalankan). Jalankan dan telusuri semua alur secara lokal sebelum menyentuh server.

---

## 0. Uji lokal (wajib, sebelum apa pun)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Buka `http://localhost:8000`, lalu periksa: login admin & staff, tambah booking multi-kamar, check-in + unggah foto KTP, kalender staff & admin, grafik pendapatan, stok, laporan, log. Kalau ada error, perbaiki dulu — jangan deploy kode yang belum pernah berjalan.

---

## 1. Syarat hosting

| Kebutuhan | Keterangan |
|---|---|
| PHP **8.2+** | cPanel → *MultiPHP Manager* → pilih 8.2 atau 8.3 |
| Ekstensi PHP | `pdo_sqlite` (atau `pdo_mysql`), **`gd`** (wajib — kompresi foto KTP), `exif` (koreksi orientasi), `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `curl` |
| SSL | AutoSSL / Let's Encrypt (gratis di kebanyakan cPanel) |

Aktifkan ekstensi lewat cPanel → *Select PHP Version* → tab *Extensions*.

---

## 2. Susunan folder

**Jangan pernah menaruh seluruh aplikasi Laravel di dalam `public_html`.** Kalau itu dilakukan, berkas `.env`, database SQLite, dan seluruh kode sumber bisa diunduh siapa pun lewat browser.

Susunan yang benar:

```
/home/USERCPANEL/
├── nyaman-app/            <- seluruh aplikasi (di LUAR public_html)
│   ├── app/  bootstrap/  config/  database/  resources/  routes/  storage/  vendor/
│   ├── .env
│   └── public/            <- tidak dipakai bila memakai Cara B
└── public_html/           <- yang bisa diakses publik
    ├── index.php
    ├── .htaccess
    ├── favicon.ico
    └── robots.txt
```

### Cara A — arahkan document root (paling bersih, kalau diizinkan)

cPanel → *Domains* → domain Anda → *Document Root* → isi `nyaman-app/public`. Selesai; `public_html` tidak dipakai.

### Cara B — pakai `public_html` (bila document root terkunci)

1. Unggah seluruh aplikasi ke `~/nyaman-app`.
2. Salin isi `public/` ke `~/public_html/` (`favicon.ico`, `robots.txt`).
3. Salin `deploy/index-public_html.php` ke `~/public_html/index.php`.
4. Salin `deploy/htaccess-public_html` ke `~/public_html/.htaccess`.

Kalau nama foldernya bukan `nyaman-app`, sesuaikan variabel `$app` di baris atas `index.php` tersebut.

---

## 3. Unggah & pasang dependensi

Unggah zip lewat *File Manager*, ekstrak ke `~/nyaman-app`, lalu di Terminal:

```bash
cd ~/nyaman-app
composer install --no-dev --optimize-autoloader
```

### Tanpa Terminal (Composer di komputer sendiri)

Jalankan `composer install --no-dev --optimize-autoloader` di komputer Anda, lalu unggah folder `vendor/` yang terbentuk bersama aplikasinya. Pastikan versi PHP lokal sama dengan yang di server.

---

## 4. Konfigurasi

```bash
cd ~/nyaman-app
cp deploy/env.production.example .env
php artisan key:generate
```

Lalu edit `.env`:

- `APP_URL=https://domain-anda.com`
- `DB_DATABASE` → path **absolut**, mis. `/home/USERCPANEL/nyaman-app/database/database.sqlite`
- Pastikan `APP_DEBUG=false` dan `APP_ENV=production`

> `APP_KEY` **wajib** dibuat ulang. Kunci yang ikut dalam paket contoh sudah tersebar dan tidak boleh dipakai di produksi — kunci itu yang mengenkripsi cookie sesi.

---

## 5. Database & izin folder

```bash
cd ~/nyaman-app
touch database/database.sqlite
php artisan migrate --force
php artisan db:seed --force        # hanya untuk data awal 20 kamar

chmod -R 775 storage bootstrap/cache
chmod 664 database/database.sqlite
chmod 775 database
```

`php artisan storage:link` **tidak diperlukan** — foto KTP sengaja tidak disajikan lewat folder publik (lihat bagian 7).

---

## 6. Akun admin & staff

Aplikasi tidak punya halaman register, dan halaman login tidak memuat kredensial contoh apa pun. Akun dibuat dari terminal.

Dengan `APP_ENV=production`, `php artisan db:seed --force` hanya membuat **20 kamar + satu akun admin + satu akun staff** — data contoh (booking, stok, laporan) dilewati.

**Cara 1 — tentukan sendiri lewat `.env` sebelum seeding:**

```env
ADMIN_NAME="Nama Anda"
ADMIN_EMAIL=admin@domain-anda.com
ADMIN_PASSWORD=kata-sandi-panjang-yang-acak

STAFF_NAME="Resepsionis"
STAFF_EMAIL=resepsionis@domain-anda.com
STAFF_PASSWORD=kata-sandi-panjang-lainnya
```

Setelah `php artisan db:seed --force` berhasil, **kosongkan lagi kedua baris `_PASSWORD`** supaya tidak tersimpan sebagai teks biasa di server, lalu jalankan `php artisan config:cache`.

**Cara 2 — biarkan kosong, sandi dibuat otomatis:**

Seeder akan mencetak kata sandi acak 16 karakter satu kali di terminal:

```
  Akun admin dibuat -> admin@domain-anda.com
  Kata sandi        -> 8fJq2LmXpR7vTn4c
  CATAT SEKARANG: kata sandi ini tidak ditampilkan lagi.
```

**Cara 3 — interaktif (paling aman, sandi tidak muncul di layar maupun riwayat perintah):**

```bash
php artisan user:create
```

Periksa hasilnya dan pastikan tidak ada akun uji yang tertinggal:

```bash
php artisan user:list
```

## 7. Foto KTP (data pribadi)

Foto KTP disimpan di `storage/app/ktp` (**privat**) dan hanya bisa dibuka lewat route `/ktp/{booking}` yang berada di balik login. Tidak ada URL publik ke berkas tersebut.

Beberapa hal yang perlu Anda pastikan sendiri:

- **Kompresi sudah otomatis.** Foto diperkecil di peramban lalu dimampatkan ulang di server (maks 1600 px, JPEG) — sekitar 90–350 KB per foto. Pastikan ekstensi **`gd`** aktif di cPanel → *Select PHP Version* → *Extensions*; tanpa gd, foto tersimpan mentah dan penyimpanan cepat penuh.
- **HTTPS wajib aktif.** Tanpa itu, foto KTP dan kata sandi melintas dalam bentuk terbaca di jaringan.
- **Retensi.** Jadwalkan penghapusan foto lama lewat cPanel → *Cron Jobs* (harian, mis. 02:00):
  ```
  /usr/local/bin/php /home/USERCPANEL/nyaman-app/artisan ktp:purge --days=30
  ```
  Coba dulu tanpa menghapus apa pun: `php artisan ktp:purge --days=30 --dry-run`

  Foto absensi karyawan punya perintah serupa (riwayat kehadirannya tetap disimpan):
  ```
  /usr/local/bin/php /home/USERCPANEL/nyaman-app/artisan absensi:purge --days=60
  ```
- **Pindahan dari versi lama.** Kalau sebelumnya sudah ada foto di `storage/app/public/ktp`, jalankan sekali: `php artisan ktp:secure`
- **Cadangan.** Unduh berkas `database.sqlite` + folder `storage/app/ktp` secara berkala, dan simpan cadangan itu dengan pengamanan yang sama.

Menyimpan foto KTP tamu tunduk pada UU No. 27/2022 tentang Pelindungan Data Pribadi. Sebaiknya konsultasikan kewajiban Anda sebagai pengendali data (pemberitahuan ke tamu, dasar pemrosesan, masa simpan) dengan pihak yang kompeten — panduan ini hanya mencakup sisi teknisnya.

---

## 8. Optimasi & pemeriksaan akhir

```bash
cd ~/nyaman-app
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jalankan ulang tiga perintah itu setiap kali `.env` atau kode berubah. Untuk membatalkan: `php artisan optimize:clear`.

Daftar periksa sebelum diumumkan:

- [ ] `https://domain-anda.com` terbuka dan gemboknya hijau
- [ ] `https://domain-anda.com/.env` menghasilkan **403/404** (bukan menampilkan isi)
- [ ] `https://domain-anda.com/storage/ktp/` menghasilkan **403/404**
- [ ] Salin URL foto KTP, buka di jendela penyamaran → harus dialihkan ke halaman login
- [ ] Halaman error tidak menampilkan stack trace (`APP_DEBUG=false`)
- [ ] `php artisan user:list` hanya menampilkan akun milik Anda (tidak ada akun uji)
- [ ] Halaman login tidak menampilkan kredensial apa pun
- [ ] `ADMIN_PASSWORD`/`STAFF_PASSWORD` sudah dikosongkan kembali dari `.env`
- [ ] Cron `ktp:purge` terpasang
- [ ] Cadangan pertama sudah diunduh
- [ ] `php -m | grep gd` menampilkan `gd` (kalau kosong, foto tidak akan dikompres)

---

## 9. Memperbarui aplikasi nanti

```bash
cd ~/nyaman-app
php artisan down
# unggah berkas baru (jangan timpa .env, database/database.sqlite, storage/app/ktp)
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

---

## Soal domain

Pembelian domain dilakukan sendiri lewat registrar (Niagahoster, Rumahweb, Domainesia, Cloudflare Registrar, Namecheap). Setelah dibeli:

1. Arahkan **nameserver** domain ke nameserver hosting Anda, **atau** buat A record ke alamat IP server (terlihat di cPanel bagian *Shared IP Address*).
2. Tambahkan domain di cPanel → *Domains* → *Create A New Domain*.
3. Tunggu propagasi DNS (biasanya 15 menit–2 jam), lalu jalankan AutoSSL.
4. Baru isi `APP_URL` di `.env` dengan domain final dan jalankan `php artisan config:cache`.

Kalau domainnya dibeli terpisah dari hosting, cukup ganti nameserver-nya — tidak perlu transfer domain.
