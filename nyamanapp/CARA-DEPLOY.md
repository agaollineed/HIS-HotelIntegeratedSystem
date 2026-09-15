# CARA DEPLOY — NyamanApp: Peran, Alur Booking, Tampilan Grup, Perbaikan Bug, Keuangan

Paket ini berisi **hanya berkas yang berubah**. Timpa versi sebelumnya, jangan
hapus apa pun yang sudah ada di server.

Tidak ada `.env`, `vendor/`, `storage/`, atau berkas database di dalam ZIP.

---

## 0. Sebelum mulai — cadangkan dulu

Migrasi paket ini **mengubah tabel `users` dan `bookings`, serta menambah tabel
`keuangan_bulanan`**. Ambil dump database lebih dulu lewat phpMyAdmin
(Export → Quick → SQL), atau dari terminal cPanel:

```
mysqldump -u djic2531_nyamanapp -p djic2531_nyamanapp > ~/backup-nyaman-sebelum-update.sql
```

Ini bukan formalitas. Migrasi `2026_09_01_000001` **menaikkan satu akun admin
menjadi superadmin** — lihat bagian 4.

---

## 1. Unggah & ekstrak

1. Buka **cPanel → File Manager → `~/extracted/djimatosuperapp/public/nyamanapp`**
2. Upload `nyamanapp-update-peran-keuangan.zip`
3. Klik kanan → **Extract** ke folder itu
4. Pilih **Replace all** saat ditanya
5. Hapus file ZIP-nya setelah selesai

---

## 2. Jalankan perintah — SATU BARIS PER WAKTU

Terminal cPanel menggabungkan tempelan multi-baris menjadi satu perintah rusak.
Salin dan jalankan satu per satu, tunggu selesai sebelum lanjut.

```
cd ~/extracted/djimatosuperapp/public/nyamanapp
```

```
php artisan migrate --force
```

```
php artisan config:clear
```

```
php artisan route:clear
```

```
php artisan view:clear
```

```
php artisan cache:clear
```

Kalau muncul `CollisionServiceProvider not found`, jalankan dua baris ini lalu
ulangi perintah di atas:

```
rm -f bootstrap/cache/packages.php
```

```
rm -f bootstrap/cache/services.php
```

---

## 3. Verifikasi migrasi

```
php artisan migrate:status
```

Tiga baris terakhir harus `Ran`:

- `2026_09_01_000001_tambah_peran_superadmin`
- `2026_09_01_000002_add_dp_dan_bukti_bayar_to_bookings`
- `2026_09_01_000003_create_keuangan_bulanan_table`

---

## 4a. Lupa kata sandi? Reset dari terminal

Perintah `user:sandi` ikut dalam paket ini. Kata sandinya diminta lewat prompt
tersembunyi — jangan pernah menaruhnya sebagai argumen, karena argumen tersimpan
di `~/.bash_history`.

```
php artisan user:list
```

```
php artisan user:sandi email@anda.com
```

```
php artisan user:sandi --semua --acak
```

Catat sandinya sebelum layar tertutup — tidak ditampilkan lagi. `remember_token`
ikut diputar, jadi cookie "ingat saya" yang lama tidak berlaku lagi. Sesi yang
sedang aktif TIDAK otomatis terputus; untuk memaksa semua orang login ulang,
jalankan `rm -f storage/framework/sessions/*` (jangan hapus foldernya).

---

## 4. PENTING — cek siapa yang jadi superadmin

Wewenang **hapus booking**, **hapus data bulanan**, **kelola akun**, dan
**halaman Keuangan** sekarang hanya milik superadmin. Supaya aplikasi tidak
terkunci dari dirinya sendiri, migrasi otomatis menaikkan **akun admin tertua
yang aktif** menjadi superadmin.

```
php artisan user:list
```

Kalau yang terangkat bukan akun Anda:

```
php artisan user:peran email-anda@domain.com superadmin
```

```
php artisan user:peran email-lain@domain.com admin
```

> Superadmin aktif terakhir tidak bisa diturunkan atau dinonaktifkan — baik
> lewat halaman maupun lewat perintah ini. Itu disengaja.

---

## 5. Uji cepat di produksi (10 menit)

**Peran** — masuk sebagai superadmin:

- [ ] Dashboard punya kartu **Kelola Akun** dan **Keuangan Bulanan**
- [ ] Panel Kamar → buka pesanan → tiap kamar punya tombol **Ubah / Hapus**
- [ ] Panel Kamar → tombol **Hapus Data \<bulan\>** muncul
- [ ] Stok → **Ubah** item → isian **Jumlah** bisa diketik

Masuk sebagai admin:

- [ ] Dashboard **tanpa** kartu Kelola Akun dan Keuangan
- [ ] Buka `/admin/akun` dan `/admin/keuangan` langsung di URL → harus **403**
- [ ] Panel Kamar → tombolnya **"Ubah"** saja, tanpa zona hapus
- [ ] Panel Kamar → buka detail → ada tombol **Buat Invoice**
- [ ] Stok → **Ubah** item → jumlah terkunci, ada tautan ke Tambah / Kurangi Stok
- [ ] Pengajuan perubahan dari staff masih bisa disetujui

**Alur booking:**

- [ ] Tambah Booking → **Lunas** → kotak unggah muncul; simpan tanpa foto → **ditolak**
- [ ] Pilih **DP** → isian nominal muncul, sisa tagihan terhitung di layar
- [ ] DP diisi ≥ tagihan → ditolak dengan pesan "pilih Lunas"
- [ ] Pilih **Belum Lunas** → kotak unggah hilang; simpan tanpa foto → **berhasil**
- [ ] Pesanan **Tiket.com** tetap bisa disimpan tanpa foto
- [ ] Booking DP berfoto → **Tamu Check-in** → masih diminta bukti pelunasan

**Tampilan grup:**

- [ ] Pesan 2 kamar untuk satu tamu → panel menampilkan **satu kotak**, judulnya `101 · 103`
- [ ] Buka kotaknya → ada rincian per kamar, masing-masing dengan tombol **Ubah**
- [ ] Ubah kamar 101 jadi lebih panjang → simpan
- [ ] Kembali ke panel → **103 tidak ikut diperpanjang**, dan kotaknya menandai "Berbeda antar kamar"
- [ ] Pada pesanan >1 kamar, tersedia **Check-in / Check-out kamar ini** per baris

**Bug:**

- [ ] Tambah Booking → pilih **Tanpa Breakfast** → total di layar **turun** (250rb/malam untuk King & Twin)
- [ ] Kamar Family → pilihan tanpa sarapan terkunci
- [ ] Susun jadwal hari ini → absen → susun ulang jadwal hari yang sama → absen lagi
      → muncul pesan "sudah absen", **bukan halaman error**
- [ ] Riwayat Tamu → tamu yang sama dengan ejaan/nomor berbeda tergabung jadi **satu profil**

**Keuangan (superadmin):**

- [ ] Dashboard → Keuangan Bulanan
- [ ] Isi bulan lalu: pemasukan & pengeluaran → simpan
- [ ] Grafik muncul: batang pemasukan, pendapatan tercatat, pengeluaran
- [ ] Tabel menampilkan **Selisih** terhadap pendapatan tercatat
- [ ] Isi bulan yang sama lagi → **memperbarui**, bukan menambah baris
- [ ] Bulan yang belum berjalan → ditolak

---

## 6. Kalau ada yang rusak — cara mundur

```
cd ~/extracted/djimatosuperapp/public/nyamanapp
```

```
php artisan migrate:rollback --step=3 --force
```

Lalu kembalikan berkas dari ZIP versi sebelumnya.

> Rollback **menurunkan semua superadmin kembali menjadi admin**, **menghapus
> kolom `dp_nominal` dan `foto_bayar_awal`** beserta isinya, dan **menghapus
> seluruh tabel `keuangan_bulanan`**. Untuk itulah dump di langkah 0 ada.

---

## Yang TIDAK boleh dijalankan di produksi

- `php artisan db:seed` — menimpa data kamar dan membuat data contoh
- `php artisan migrate:fresh` — mengosongkan seluruh database
- `php artisan migrate` tanpa `--force`

---

## Catatan teknis

- **Bug harga sarapan ada di layar, bukan di database.** Server sudah selalu
  menyimpan 250rb untuk King/Twin tanpa sarapan; yang salah adalah `data-harga`
  di formulir yang selalu memakai tarif dengan sarapan, sehingga staff menyebut
  angka yang berbeda dari yang tersimpan. Data lama Anda tidak perlu diperbaiki.

- **Bug absensi 500 penyebabnya jadwal disusun ulang.** Menyusun ulang jadwal
  menghapus baris jadwal lama, dan absensi yang sudah tercatat kehilangan
  `jadwal_id`-nya. Halaman lalu menampilkan orang itu "belum absen", ia absen
  lagi, dan indeks unik `(karyawan_id, tanggal)` menolaknya. Sekarang absensi
  ditautkan ulang otomatis, dan duplikat ditolak dengan pesan yang bisa dibaca.

- **Riwayat tamu juga memperbaiki bug MySQL yang tidak pernah terlihat.** Query
  lama memakai `COALESCE(grup, 'single-' || id)`. Operator `||` adalah penyambung
  teks di SQLite tetapi **OR logika di MySQL** — di server, ekspresi itu bernilai
  1 untuk setiap baris, sehingga semua booking tanpa grup menyatu menjadi satu
  "kunjungan". Pengelompokan sekarang dikerjakan di PHP.

- **Laporan pendapatan sekarang memakai tarif yang benar-benar berlaku**
  (`Booking::hargaMalam()`), dan rekap per tanggal dipecah per tipe **+ pilihan
  sarapan** — supaya King tanpa sarapan tidak dilaporkan 300rb. Angka bulan-bulan
  lalu akan **berubah** setelah update ini; itu koreksi, bukan kerusakan.

- **Keuangan sengaja tidak diisi otomatis dari booking.** Yang dicari adalah
  selisih antara setoran nyata dan pendapatan tercatat. Kalau angkanya berasal
  dari sumber yang sama, selisihnya selalu nol dan halaman itu tidak ada gunanya.

- **Edit booking sekarang terbagi**: kamar, tanggal, durasi, dan sarapan berlaku
  **per baris kamar**; nama tamu, telepon, asal, status bayar, DP, dan diskon
  berlaku **untuk seluruh pesanan**. Total tagihan dihitung dari masing-masing
  kamar, jadi memperpanjang satu kamar otomatis menaikkan tagihan pesanannya.

- **Check-in / check-out punya lingkup.** Tombol utama tetap mengenai seluruh
  pesanan. Pada pesanan lebih dari satu kamar, tersedia tombol per kamar
  (`?scope=one`) — dibutuhkan sejak masa menginap boleh berbeda antar kamar.
