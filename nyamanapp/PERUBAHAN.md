# Perubahan NyamanApp

---

## 0. Pengajuan perubahan booking (staff → persetujuan admin)

Staff kini bisa mengusulkan **pindah kamar**, **perpanjang menginap**, **ubah
tanggal**, dan **ubah nama/telepon** — tetapi booking baru berubah setelah admin
menyetujui.

**Satu pengajuan = satu jenis perubahan = satu persetujuan.** Ini dijaga sampai
ke isi permintaan HTTP-nya: kolom pada blok yang tersembunyi dinonaktifkan, jadi
satu pengajuan mustahil membawa dua perubahan sekaligus.

Cakupan saat disetujui mengikuti aturan formulir ubah yang sudah ada: **kamar**
hanya berpindah untuk baris itu; **nama, telepon, tanggal** berlaku untuk seluruh
kamar dalam satu pesanan.

Hasil uji alur penuh:

| Langkah | Hasil |
|---|---|
| Staff ajukan pindah 101 → 204 | tersimpan; kamar booking **masih 101** |
| Admin setujui (204 ternyata terisi) | **ditolak sistem** dengan menyebut tanggal bentroknya |
| Diarahkan ke kamar kosong (106), disetujui | diterapkan, tercatat atas nama Administrator |
| Staff ajukan lagi saat masih menunggu | **dikunci** — satu pengajuan tertunda per booking |
| Admin tolak tanpa alasan | ditolak — "Tuliskan alasan penolakan" |
| Admin tolak dengan alasan | tersimpan; booking **tidak tersentuh** |
| Ubah nama & ubah tanggal | keduanya diterapkan benar |

Ketersediaan kamar diperiksa **ulang di detik persetujuan** — kamar yang kosong
saat staff mengajukan bisa saja sudah terisi ketika admin menekan Setujui.
Memeriksa hanya di awal berarti menyetujui gambaran yang sudah basi.

Baris pengajuan tidak pernah dihapus, termasuk yang ditolak: "siapa pernah minta
apa, dan siapa menolaknya" adalah bagian dari pengawasan.

### Bug lama yang ikut diperbaiki

Layout hanya menampilkan `session('success')`, tidak pernah `session('error')`.
Artinya **setiap pesan gagal yang dikirim lewat `with('error')` hilang tanpa
jejak** — layar terlihat seolah tidak terjadi apa-apa. Ini yang membuat
penolakan persetujuan tadi awalnya tampak "diam saja". Sudah diperbaiki.

Tabel baru: `booking_requests`.

---

Empat perubahan, seluruhnya **aditif terhadap data**. Tidak ada tabel yang
dihapus, tidak ada baris yang dibuang, tidak ada kolom lama yang diubah artinya.

---

## 1. Tipe kamar: 101 → Twin, 105 → King

Dikerjakan lewat migrasi yang hanya meng-`update` kolom `tipe` pada dua baris
kamar. Kamarnya **tidak** dihapus dan dibuat ulang, sehingga `rooms.id` tetap
dan seluruh booking yang menunjuk ke kamar itu — termasuk yang sudah check-out
berbulan lalu — tetap tersambung ke kamar yang sama.

Dua hal yang perlu diketahui:

- Booking **lama** di kamar 101 dan 105 akan ikut menampilkan tipe yang baru.
  Tipe kamar tidak pernah disalin ke tabel booking (selalu dibaca dari
  kamarnya), jadi memang tidak ada tipe lama yang tersimpan untuk
  dipertahankan.
- **Tidak ada nilai uang yang bergeser.** Tarif King dan Twin sama persis:
  Rp 300.000 dengan sarapan, Rp 250.000 tanpa.

Migrasi ini punya `down()` yang mengembalikan keadaan semula, dan daftar kamar
di seeder sudah ikut disamakan.

---

## 2. Diskon

Kotak **Diskon** di formulir booking, dengan pemilih satuan **Rupiah / Persen**
dan tombol cepat 5% / 10% / 15% / 20%.

- Pada mode persen, nominalnya **dihitung ulang di server** — diuji dengan
  mengirim nominal palsu Rp 999.999 bersama persen 12% pada kamar Rp 600.000;
  yang tersimpan tetap Rp 72.000.
- **Diskon memotong tagihan, bukan menambah uang masuk.** Disimpan di kolom
  sendiri supaya laporan pendapatan tidak menghitung potongan sebagai
  pemasukan.
- Milik **satu pesanan**, dibaca sekali, bukan dijumlahkan antar baris kamar.
- Diskon ≥ total kamar ditolak: kamar gratis adalah keputusan yang harus
  terlihat, bukan tersamar sebagai diskon.
- Pesanan Tiket.com dikunci — tarifnya ditetapkan mitra.

Kolom baru: `diskon_nominal`, `diskon_persen`. Tampil di panel staff, panel
admin, export Excel, dan catatan invoice.

---

## 3. Booking untuk tanggal yang sudah lewat

Check-in boleh mundur sampai **7 hari**, dengan tombol **"Tamu datang dini hari
(malam kemarin)"** yang sekali tekan menyetel check-in = kemarin, check-out =
hari ini — persis kasus tamu jam 1 pagi tanggal 25 yang harus check-out siang
tanggal 25.

Batas 7 hari tetap ada supaya salah ketik bulan atau tahun tidak lolos
diam-diam. Diuji: tanggal 10 hari lampau ditolak dengan pesan yang menyebut
batasnya.

---

## 4. Wewenang penuh admin atas kamar + status check-in

Admin sekarang bisa **membuat booking, check-in, check-out, dan menandai late**
— kapan pun, tanpa batas waktu.

Yang **tidak** dilonggarkan adalah SOP fotonya. Admin membuka halaman yang sama
persis dengan staff. Diuji langsung:

| Percobaan sebagai admin | Hasil |
|---|---|
| Check-in tanpa foto | ditolak — "Foto KTP wajib diunggah saat check-in." |
| Check-out tanpa pernyataan kondisi | ditolak — "Nyatakan kondisi kamar terlebih dahulu." |
| Check-out late tanpa bukti bayar | ditolak — "bukti pembayaran kelebihan jam wajib diunggah." |
| Check-in & check-out dengan foto lengkap | berhasil, tercatat atas nama Administrator |

Wewenang menentukan **siapa** yang boleh, bukan **apa** yang boleh dilewati.

Agar tidak lahir dua salinan aturan SOP yang bisa menyimpang diam-diam,
view-nya tetap satu; nama rutenya yang menyesuaikan diri lewat kelas baru
`App\Support\Panel`.

**Status kedatangan di panel admin** kini empat angka: Kamar Terisi, Kamar
Kosong, **Sudah Check-in**, **Belum Check-in**, plus baris "N dari M kamar sudah
check-out". Inilah yang dicari saat panel dibuka di tengah hari — bukan sekadar
berapa kamar yang terjual, tapi berapa tamu yang benar-benar sudah datang.
