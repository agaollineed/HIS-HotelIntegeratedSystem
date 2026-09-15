# Skenario Uji di Localhost

Semua skenario ditulis apa adanya (hardcode) di
`database/seeders/SkenarioSeeder.php`. Tanggalnya relatif terhadap hari
seeder dijalankan, jadi datanya selalu jatuh di sekitar hari ini.

## Menjalankan

```bash
php artisan migrate            # sekali saja, kalau database masih kosong
php artisan db:seed            # akun + kamar + stok + seluruh skenario

# atau muat ulang skenarionya saja (aman diulang berkali-kali):
php artisan db:seed --class=Database\Seeders\SkenarioSeeder
```

Skenario lama dihapus lebih dulu berdasarkan awalan telepon `0899000`, jadi
booking asli tidak ikut terhapus. Setelah selesai, seeder mencetak tabel
berisi tarif, status, biaya tambahan, dan tanggal panel yang harus dibuka.

## Aturan yang diuji

| Aturan | Nilai |
| --- | --- |
| King / Twin dengan sarapan | Rp 300.000 / malam |
| King / Twin tanpa sarapan | Rp 250.000 / malam |
| Family | Rp 450.000 / malam, **wajib** sarapan |
| Early check-in | masuk sebelum 12.30 → tambahan 1× harga kamar penuh |
| Toleransi check-in | 12.30–13.00 → berstatus early, biaya Rp 0 |
| Late check-out | lewat 12.00 → ½ harga kamar, berlaku sampai 17.00 |
| Over late check-out | lewat 17.00 → 1× harga kamar penuh |
| Bukti pembayaran | wajib pada early check-in, late CO, dan over late CO |
| Early check-out | pergi sebelum 09.00 tanggal keluar — kamar langsung terbuka |

## Daftar skenario

| Kode | Kamar | Yang diperiksa |
| --- | --- | --- |
| S01 | 101 | King + sarapan = Rp 300.000, belum check-in |
| S02 | 102 | King tanpa sarapan = Rp 250.000 |
| S03 | 202 | Family Rp 450.000, opsi tanpa sarapan terkunci di form |
| S04 | 103 | Early check-in 11.20 → tambahan Rp 300.000 + bukti bayar |
| S05 | 104 | Check-in 12.45 → status early, biaya Rp 0 |
| S06 | 105 | Check-in normal 14.10, Twin tanpa sarapan |
| S07 | 106 | Check-out 14.30 → late, ½ harga = Rp 150.000 |
| S08 | 107 | Check-out 18.40 → over late, Rp 300.000 + tiket kerusakan |
| S09 | 108 | Check-out 16.01 sebelum masa sewa habis → early check-out |
| S10 | 201 | Check-out 10.15 → tepat waktu, tanpa biaya |
| S11 | 203 | Belum di-check-out lewat 12.00 → toast peringatan late |
| S12 | 204, 205 | Dua kamar dalam satu pesanan (grup) |
| S13 | 108 | Booking baru di kamar bekas S09 → kamar langsung terbuka |
| S14 | 206 | Belum lunas → bukti pelunasan wajib saat check-in |
| S15 | 207 | Tiket.com → status bayar terkunci lunas, menginap mulai besok |

## Catatan saat memeriksa

- Kolom **Buka panel** pada tabel seeder adalah tanggal yang harus dipilih di
  kalender panel Kamar. Entri hanya muncul pada tanggal yang benar-benar
  ditagih (`check_in` sampai malam terakhir).
- S07, S08, dan S10 masa sewanya berakhir hari ini, jadi entrinya berada di
  panel tanggal **kemarin**.
- S11 hanya memunculkan toast late bila jam sekarang sudah lewat 12.00.
- Foto contoh dibuat otomatis di `storage/app/{ktp,bayar,kartu,kerusakan}/`
  supaya tombol foto tidak kosong.
