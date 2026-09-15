# Menjalankan NyamanSuperApp di Windows (dari nol)

Panduan ini untuk Windows 10/11. Perkiraan waktu: 20–30 menit, sebagian besar menunggu unduhan.

**Yang TIDAK perlu dipasang** (sering dikira wajib untuk Laravel):

| Tidak perlu | Alasan |
|---|---|
| Node.js / npm | Proyek ini tidak punya `package.json` — tampilannya Blade + CSS biasa, tanpa proses build |
| MySQL / phpMyAdmin | Databasenya SQLite, cukup satu berkas `database/database.sqlite` |
| Apache / Nginx | Untuk pengembangan cukup `php artisan serve` |
| Docker / WSL | Tidak diperlukan; PHP native di Windows sudah cukup |

---

## 1. Pasang PHP + Composer

### Cara A — Laragon (disarankan, paling ringkas)

Laragon sekaligus membawa PHP, Composer, dan terminal yang sudah dikonfigurasi.

1. Unduh **Laragon Full** dari <https://laragon.org/download/>
2. Pasang dengan pengaturan bawaan (biarkan lokasinya `C:\laragon`)
3. Buka Laragon → tombol **Start All**
4. Periksa versi PHP-nya: menu **Menu → PHP → Version**. Harus **8.2** atau lebih baru. Kalau masih 8.1, unduh PHP 8.3 dari <https://windows.php.net/download/> (pilih **Thread Safe**), ekstrak ke `C:\laragon\bin\php\php-8.3.x`, lalu pilih versi itu dari menu yang sama.

Selanjutnya, semua perintah dijalankan lewat **Laragon → Terminal** (tombol *Terminal* di jendela Laragon).

### Cara B — Manual (tanpa Laragon)

1. **PHP**: unduh **PHP 8.3 VS16 x64 Thread Safe** (ZIP) dari <https://windows.php.net/download/>
   - Ekstrak ke `C:\php`
   - Tambahkan `C:\php` ke PATH: tekan Start → ketik *"environment variables"* → **Edit the system environment variables** → **Environment Variables** → pada *System variables* pilih **Path** → **Edit** → **New** → isi `C:\php` → OK semuanya
   - Salin `C:\php\php.ini-development` menjadi `C:\php\php.ini`
2. **Composer**: unduh installer dari <https://getcomposer.org/Composer-Setup.exe> lalu pasang. Saat ditanya lokasi PHP, arahkan ke `C:\php\php.exe`.

**Tutup semua jendela terminal setelah mengubah PATH**, lalu buka yang baru — kalau tidak, perubahannya belum terbaca.

---

## 2. Aktifkan ekstensi PHP yang dibutuhkan

Buka file `php.ini`:

- Laragon: **Menu → PHP → php.ini**
- Manual: `C:\php\php.ini` (buka dengan Notepad atau VS Code)

Cari baris-baris berikut dan **hapus tanda titik-koma (`;`) di depannya**:

```ini
extension=curl
extension=fileinfo
extension=gd
extension=mbstring
extension=exif
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=zip
```

Pastikan juga baris ini tidak diberi komentar:

```ini
extension_dir = "ext"
```

Kegunaan masing-masing di aplikasi ini:

| Ekstensi | Dipakai untuk |
|---|---|
| `pdo_sqlite`, `sqlite3` | Database — tanpa ini muncul error *"could not find driver"* |
| `gd` | **Kompresi foto KTP & selfie absensi.** Tanpa ini aplikasi tetap jalan, tetapi foto tersimpan mentah (3–8 MB per foto) |
| `exif` | Memutar foto potret dari kamera ponsel agar tidak miring (butuh `mbstring` aktif) |
| `zip`, `xml` | Ekspor Excel (laporan stok & log) |
| `fileinfo` | Validasi jenis berkas saat unggah foto |
| `mbstring`, `openssl`, `curl` | Kebutuhan dasar Laravel |

Simpan `php.ini`. Di Laragon, klik **Stop All → Start All** agar terbaca.

### Verifikasi

Buka terminal baru, lalu:

```powershell
php -v
composer -V
php -m
```

- `php -v` harus menampilkan **8.2** atau lebih tinggi
- `php -m` harus memuat `gd`, `pdo_sqlite`, `sqlite3`, `exif`, `zip`, `mbstring`, `fileinfo`

Cara cepat memeriksa satu ekstensi:

```powershell
php -r "var_dump(extension_loaded('gd'), extension_loaded('pdo_sqlite'), extension_loaded('exif'));"
```

Ketiganya harus `bool(true)`.

---

## 3. Pasang VS Code + ekstensi

Unduh dari <https://code.visualstudio.com/>. Setelah terpasang, buka VS Code → panel **Extensions** (`Ctrl+Shift+X`) → pasang:

| Ekstensi | ID | Kegunaan |
|---|---|---|
| **PHP Intelephense** | `bmewburn.vscode-intelephense-client` | Autocomplete & navigasi kode PHP (paling penting) |
| **Laravel Blade Snippets** | `onecentlin.laravel-blade` | Pewarnaan sintaks `.blade.php` |
| **Laravel Extra Intellisense** | `amiralizadeh9480.laravel-extra-intellisense` | Autocomplete `route()`, `view()`, `config()` |
| **DotENV** | `mikestead.dotenv` | Pewarnaan berkas `.env` |
| **SQLite Viewer** | `qwtel.sqlite-viewer` | Melihat isi `database.sqlite` langsung dari VS Code |
| **EditorConfig** | `EditorConfig.EditorConfig` | Mengikuti aturan indentasi proyek |

Setelah memasang Intelephense, **nonaktifkan PHP bawaan VS Code** agar tidak dobel: `Ctrl+Shift+P` → *"Extensions: Show Built-in Extensions"* → cari **PHP Language Features** → **Disable**.

### Pengaturan VS Code

`Ctrl+Shift+P` → *"Preferences: Open User Settings (JSON)"* → tambahkan:

```json
{
    "php.validate.executablePath": "C:\\laragon\\bin\\php\\php-8.3.0-Win32-vs16-x64\\php.exe",
    "intelephense.environment.phpVersion": "8.3.0",
    "emmet.includeLanguages": { "blade": "html" },
    "files.associations": { "*.blade.php": "blade" },
    "terminal.integrated.defaultProfile.windows": "PowerShell"
}
```

Sesuaikan path PHP-nya dengan lokasi di komputer Anda (kalau memakai cara manual: `C:\\php\\php.exe`). Untuk mengetahui path pastinya, jalankan `where.exe php` di terminal.

---

## 4. Jalankan proyeknya

Ekstrak `nyaman-super-app.zip` ke folder yang mudah dijangkau, misalnya `C:\laragon\www\nyaman-super-app` (kalau memakai Laragon) atau `D:\proyek\nyaman-super-app`.

Buka foldernya di VS Code: **File → Open Folder**. Lalu buka terminal di dalam VS Code dengan `Ctrl+` `` ` `` (backtick).

### Pastikan dulu PHP yang terpakai benar

Kalau di komputer Anda pernah terpasang **Laravel Herd**, **XAMPP**, atau PHP bawaan lain, bisa jadi yang terpanggil bukan PHP dari Laragon. Periksa dulu:

```powershell
where.exe php
php --ini
```

`where.exe php` menampilkan semua PHP yang ditemukan — **yang paling atas** itulah yang dipakai. Kalau baris teratasnya menunjuk ke `C:\Program Files\Herd\...` padahal Anda ingin memakai Laragon, lihat bagian penanganan masalah di bawah.

### Jalankan

```powershell
# 1. Pasang dependensi (sekali saja, butuh internet, ~2-5 menit)
composer install

# 2. Siapkan konfigurasi
Copy-Item .env.example .env
php artisan key:generate

# 3. Siapkan database
New-Item -ItemType File -Force database\database.sqlite
php artisan migrate:fresh --seed

# 4. Jalankan
php artisan serve
```

Buka <http://127.0.0.1:8000> di browser.

> `New-Item -ItemType File -Force` adalah padanan `touch` di PowerShell. Kalau memakai Command Prompt (bukan PowerShell), gunakan `type nul > database\database.sqlite` dan `copy .env.example .env`.

### Akun untuk masuk

Sebelum menjalankan langkah 3, buka `.env` dan tambahkan di bagian bawah:

```env
ADMIN_NAME="Administrator"
ADMIN_EMAIL=admin@localhost
ADMIN_PASSWORD=lokal12345678

STAFF_NAME="Staff Resepsionis"
STAFF_EMAIL=staff@localhost
STAFF_PASSWORD=lokal12345678
```

Lalu jalankan `php artisan migrate:fresh --seed`. Masuk dengan `admin@localhost` / `lokal12345678`.

Kalau bagian itu dibiarkan kosong, seeder membuat kata sandi acak dan **mencetaknya sekali** di terminal — catat saat muncul.

> Sandi sederhana di atas hanya untuk komputer sendiri. Jangan dipakai di server.

---

## 5. Perintah harian

```powershell
php artisan serve                  # jalankan (Ctrl+C untuk berhenti)
php artisan serve --port=8080      # kalau port 8000 dipakai program lain
php artisan migrate:fresh --seed   # reset database ke kondisi awal
php artisan user:list              # lihat akun yang ada
php artisan user:create            # buat akun / ganti kata sandi
php artisan optimize:clear         # bersihkan cache bila .env diubah tapi tidak terbaca
```

---

## 6. Kalau ada masalah

**`'php' is not recognized as an internal or external command`**
PATH belum termuat. Tutup semua terminal, buka yang baru. Kalau masih, ulangi langkah menambahkan `C:\php` ke PATH dan pastikan menekan OK sampai jendela terakhir.

**`could not find driver` saat `php artisan migrate`**
`pdo_sqlite` belum aktif. Buka `php.ini`, hapus `;` di depan `extension=pdo_sqlite` dan `extension=sqlite3`, simpan, buka terminal baru. Periksa dengan `php -m | findstr sqlite`.

**`composer install` gagal dengan pesan soal `zip` atau SSL**
Aktifkan `extension=zip` dan `extension=openssl` di `php.ini`.

**`database/database.sqlite does not exist`**
Berkasnya belum dibuat. Jalankan `New-Item -ItemType File -Force database\database.sqlite`.

**Foto KTP/absensi tersimpan besar sekali**
`gd` belum aktif — aplikasi sengaja tetap berjalan tanpanya, tapi tanpa kompresi. Periksa dengan `php -r "var_dump(extension_loaded('gd'));"`.

**Foto dari kamera tampil miring**
`exif` belum aktif (dan `exif` butuh `mbstring` aktif lebih dulu).

**Halaman error tapi pesannya tidak jelas**
Pastikan `APP_DEBUG=true` di `.env` untuk pengembangan lokal, lalu jalankan `php artisan optimize:clear`. Log lengkapnya ada di `storage\logs\laravel.log`.

**`composer install` sangat lambat**
Windows Defender sering memindai ribuan berkas di `vendor/`. Tambahkan folder proyek ke pengecualian: Settings → Privacy & security → Windows Security → Virus & threat protection → Manage settings → Add or remove exclusions.

**`Failed to listen on 127.0.0.1:8000 (reason: ?)` padahal `php -S 127.0.0.1:8000` berhasil**

Kalau server bawaan PHP jalan saat dipanggil langsung tapi `php artisan serve` gagal di semua port, penyebabnya bukan port yang bentrok, melainkan **variabel lingkungan `SystemRoot` yang terbuang**.

Laravel menjalankan server sebagai proses anak, dan sengaja membuang hampir semua variabel lingkungan agar perubahan `.env` langsung terbaca. Variabel yang dipertahankan ada dalam daftar tetap, dan di dalamnya tertulis `SYSTEMROOT` (huruf besar semua) — sedangkan Windows menamainya `SystemRoot`. Perbandingannya peka huruf besar/kecil, jadi variabel itu ikut terbuang. Tanpa `SystemRoot`, Winsock di Windows gagal membuka soket, dan pesan yang muncul hanya `(reason: ?)`.

Pembuangan itu hanya terjadi bila `$_ENV` terisi, yaitu ketika `variables_order` pada `php.ini` memuat huruf `E`. Bawaan PHP adalah `GPCS` (tanpa E) sehingga kebanyakan orang tidak pernah mengalaminya — tetapi beberapa distribusi PHP, termasuk **Laravel Herd**, memakai `EGPCS`.

Periksa dulu:

```powershell
php -i | findstr /i variables_order
php -r "var_dump(isset($_ENV['SystemRoot']));"
```

Kalau hasilnya `EGPCS` dan `bool(true)`, itulah penyebabnya. Pilih salah satu solusi:

**Solusi 1 — kembalikan `variables_order` ke bawaan PHP (disarankan).** Jalankan `php --ini` untuk menemukan berkasnya, buka, lalu ubah:

```ini
variables_order = "GPCS"
```

Simpan, buka terminal baru, jalankan `php artisan serve` seperti biasa. `$_ENV` menjadi kosong sehingga Laravel tidak membuang apa pun, dan seluruh lingkungan diwariskan utuh ke proses anak.

**Solusi 2 — jalankan servernya langsung, tanpa mengubah konfigurasi.** Persis seperti yang dilakukan `artisan serve` di balik layar:

```powershell
cd public
php -S 127.0.0.1:9000 ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
```

Buka <http://127.0.0.1:9000>. Tekan `Ctrl+C` untuk berhenti, dan `cd ..` untuk kembali ke folder proyek. Kekurangannya: server tidak otomatis dimulai ulang saat `.env` berubah — jalankan ulang perintahnya secara manual.

**Solusi 3 — pakai PHP dari Laragon**, yang `php.ini`-nya memakai bawaan `GPCS`. Buka Laragon → tombol **Terminal**, lalu jalankan `php artisan serve` dari sana.

---

**Port 8000 sudah dipakai program lain**
Jalankan `php artisan serve --port=8080` lalu buka <http://127.0.0.1:8080>.

Kalau *semua* port berurutan gagal, periksa apakah Windows memesan rentang tersebut untuk Hyper-V/WSL/Docker:

```powershell
netsh interface ipv4 show excludedportrange protocol=tcp
netstat -ano | findstr :8000
```

Kalau rentangnya memang dipesan, pakai port di luar daftar itu. Kalau daftarnya justru tidak memuat port yang Anda coba, lihat kembali masalah `SystemRoot` di atas.

---

**Muncul banyak `PHP Warning: Unable to load dynamic library ... php_redis-8.4.dll` / `php_mongodb-8.4.dll`**

Peringatan ini datang dari PHP milik **Laravel Herd** yang `php.ini`-nya masih menunjuk ke berkas ekstensi yang sudah tidak ada. Peringatannya sendiri **tidak menggagalkan** aplikasi — tapi berarti PHP yang terpanggil adalah milik Herd, bukan Laragon.

Pilih salah satu:

**Bersihkan peringatannya (tetap memakai PHP Herd).** Cari lokasi `php.ini` yang sedang dipakai dengan `php --ini`, buka berkasnya, lalu beri tanda `;` di depan dua baris ini:

```ini
;extension=C:\Program Files\Herd\...\php_redis-8.4.dll
;extension=C:\Program Files\Herd\...\php_mongodb-8.4.dll
```

Aplikasi ini tidak memakai Redis maupun MongoDB, jadi mematikannya aman. Setelah itu pastikan ekstensi yang memang dibutuhkan tetap aktif: `php -m | findstr /i "sqlite gd exif zip"`.

**Atau alihkan ke PHP Laragon.** Buka Laragon → tombol **Terminal** (bukan PowerShell biasa) — terminal itu sudah menaruh PHP Laragon di urutan pertama. Kalau ingin permanen di semua terminal, buka *Environment Variables* → **Path**, lalu naikkan entri `C:\laragon\bin\php\php-8.x.x` ke atas entri Herd dengan tombol **Move Up**.

Setelah mengubah PATH, tutup semua terminal dan buka yang baru, lalu pastikan dengan `where.exe php`.

---

## 7. Menguji fitur yang butuh kamera

Fitur check-in tamu dan absensi memakai kamera. Beberapa hal yang perlu diketahui:

- Di **laptop**, tombol "Buka Kamera" akan membuka dialog pilih berkas (bukan kamera) — ini normal, karena atribut `capture` hanya berpengaruh di ponsel. Gunakan "Pilih File" untuk mengujinya.
- Untuk mencoba dari **ponsel** di jaringan yang sama, jalankan `php artisan serve --host=0.0.0.0`, cari alamat IP komputer Anda dengan `ipconfig` (cari *IPv4 Address*, misalnya `192.168.1.5`), lalu buka `http://192.168.1.5:8000` dari ponsel.
- Kamera di browser umumnya hanya aktif pada HTTPS atau `localhost`. Lewat IP jaringan lokal, tombol kamera mungkin dialihkan ke galeri — hal ini tidak terjadi setelah aplikasi dipasang di domain ber-HTTPS.
