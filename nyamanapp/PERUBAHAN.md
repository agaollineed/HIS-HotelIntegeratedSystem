# Perubahan NyamanApp

---

## 0. Unggah foto dengan seret & lepas, dan Nomor WhatsApp tamu

Tidak ada migrasi untuk bagian ini.

**Seret & lepas foto**
- Semua kotak unggah foto (e-tiket, bukti bayar, KTP, check-out, absensi, laporan) sekarang bisa
  diisi dengan **menyeret foto dari folder/desktop lalu melepasnya di atas kotak**. Kotaknya menyala
  ungu saat foto dibawa ke atasnya, lalu foto diperkecil otomatis seperti tombol "Pilih File".
- Berkas yang bukan JPG/PNG (mis. PDF e-tiket) ditolak dengan pesan untuk mengambil screenshot-nya.
- Foto yang terlepas di luar kotak tidak lagi membuka gambar di browser (isian formulir aman).

**Nomor WhatsApp**
- Kolom telepon diganti **"Nomor WhatsApp"** dan dipindah ke atas, tepat di bawah nama tamu.
- Nomor harus nomor sungguhan (9–15 angka; boleh 08…, +62…, dengan spasi/strip).
- Tamu **Tiket.com** yang tidak meninggalkan nomor: centang **"Tamu Tiket.com tanpa nomor"**
  (kolom terisi "-"). Untuk pesanan Umum, "-" ditolak.
- Di daftar kamar admin dan staff, nomor tamu bisa **diklik untuk langsung chat WhatsApp**
  (wa.me). Tamu tanpa nomor ditulis "Tanpa nomor".

---

## 1. E-tiket wajib untuk Tiket.com, dan Saldo Rekening otomatis dari Buku Besar

Tidak ada migrasi untuk bagian ini.

**E-tiket Tiket.com**
- Pesanan Tiket.com sekarang **wajib melampirkan e-tiket** (foto atau screenshot) saat dibuat. Tanpa
  e-tiket, pesanan ditolak dengan pesan yang jelas. Statusnya tetap otomatis lunas.
- Kotak unggahnya sama dengan bukti bayar, tetapi berjudul "E-tiket Tiket.com". Begitu asal pesanan
  diganti ke Umum, kotaknya kembali menjadi bukti pembayaran (foto yang terlanjur dipilih dibuang).
- Pesanan Tiket.com lama yang belum punya e-tiket diminta e-tiketnya saat diubah, dan di daftar kamar
  admin ditandai "Pesanan Tiket.com tanpa e-tiket".
- E-tiket **tidak dihitung sebagai bukti bayar hotel**. Jadi selisih upgrade/tambah malam tetap
  diminta bukti bayarnya, dan pesanan yang dipindah dari Tiket.com ke Umum tidak bisa memakai
  e-tiket sebagai bukti lunas.

**Saldo Rekening dari Buku Besar**
- Saldo **tidak diketik lagi**. Semua angka di halaman Saldo Rekening dihitung dari Buku Besar:
  saldo saat ini (BNI, BRI, total), grafik saldo harian, dan perbandingan setiap tanggal 5.
- Mencatat atau mengoreksi transaksi di Buku Besar langsung menggeser angka dan grafik di halaman
  Saldo (halaman menyegarkan diri tiap 30 detik).
- Tombol utama menjadi **"Catat Transaksi"**, yang membuka Buku Besar dengan jendela catat hari ini.
- Di bawah grafik ada daftar 7 hari transaksi terakhir (masuk, keluar, saldo akhir).
- Export CSV sekarang berisi saldo akhir BNI, BRI, dan total setiap hari. Import CSV saldo dan
  hapus saldo dihapus karena tidak diperlukan lagi.
- Data saldo yang dulu diketik manual (tabel `saldo_harian`) **tidak dihapus**, hanya tidak dipakai.

---

## 2. Tampilan Keuangan dirapikan

Tidak ada migrasi untuk bagian ini.

Keempat halaman Keuangan (Saldo Rekening, Keuntungan & Bonus, Pajak, Buku Besar) kini memakai pola
yang sama — lebih sedikit tombol, angka penting lebih menonjol:

- **Angka utama besar di atas:** total saldo rekening; keuntungan bulan ini + bonus karyawan;
  pajak yang **belum dibayar**; total saldo BNI + BRI.
- **Satu tombol utama** "Catat …" di kanan atas. Formulirnya muncul sebagai **jendela**,
  tidak lagi memenuhi halaman.
- **Export CSV, Import CSV, Unduh Excel, dan Ubah saldo awal** pindah ke menu **Lainnya**.
- **Tidak ada tombol Ubah/Hapus di setiap baris.** Klik barisnya untuk membuka jendela ubah
  (tanggal/bulan terkunci); tombol **Hapus** ada di dalam jendela (superadmin saja).
- Pajak: status bayar cukup satu centang di barisnya.
- Buku Besar: ringkasan masuk/keluar/saldo akhir bulan di atas tabel, dan pilihan
  "Sembunyikan hari tanpa transaksi".
- Saldo Rekening: ajakan "Saldo hari ini belum dicatat" muncul bila belum diisi.
- Isian yang ditolak membuka lagi jendelanya lengkap dengan pesan kesalahannya.
- Keterangan/catatan yang mengandung tanda petik tidak lagi berisiko merusak baris.

---

## 3. Keuangan: Pajak dan Buku Besar (+ perbaikan keamanan folder)

**Wajib setelah unggah:** `php artisan migrate --force` lalu `php artisan optimize:clear`.
Ada dua migrasi: tabel pajak & buku besar, dan **isi buku besar dari Excel "SALDO BANK"**
(hanya diisi bila buku besar masih kosong).

**Pajak** (tab baru di Keuangan)
- Isian: bulan, nominal, centang "Sudah dibayar". Tabel menampilkan status dibayar/belum,
  dengan tombol **Tandai dibayar** / **Batalkan**.
- Tanggal bayar otomatis: bulan **November 2026 dan sesudahnya** = tanggal hari dicentang;
  bulan-bulan sebelumnya = **tanggal 5 bulan berikutnya** (pajak April → 5 Mei).
- Mengubah nominal tidak menggeser tanggal bayar yang sudah tercatat.
- Ringkasan: total pajak, sudah dibayar, belum dibayar. Import/Export CSV.

**Buku Besar** (tab baru di Keuangan) — salinan lembar Excel "SALDO BANK"
- Kolom seperti Excel: TGL | UANG MASUK (BNI, BRI, Total) | UANG KELUAR (BNI, BRI, Total) |
  SALDO AKHIR (BNI, BRI, Total) | Keterangan. Satu bulan per halaman, setiap hari tampil
  (termasuk hari tanpa transaksi), dengan baris saldo awal bulan dan total bulan.
- Saldo akhir **dihitung otomatis** dari saldo awal + masuk − keluar; mengoreksi satu hari
  otomatis membetulkan semua hari sesudahnya.
- Saldo awal 31 Maret 2026: BNI Rp 50.779.050, BRI Rp 10.000 (bisa diubah).
- **Data Excel sudah dimasukkan**: 215 hari bertransaksi 1 April – 5 November 2026, lengkap
  dengan keterangannya. Hasil hitung ulang cocok dengan kolom SALDO AKHIR Excel di semua hari
  (5 Nov 2026: BNI Rp 4.858.050, BRI Rp 313.101.528, total Rp 317.959.578).
- Import CSV menerima langsung ekspor lembar Excel itu (tanggal 01-Apr-26, 01-Agust-26, angka
  bertitik), mencocokkan kolom saldo akhir di berkas dengan hitungan aplikasi, dan memberi
  peringatan bila ada yang beda. Export CSV memakai susunan kolom yang sama.
- Hapus hari hanya superadmin; admin bisa mengisi dan mengoreksi.

**Saldo Rekening:** peringatan "angka janggal" saat impor tidak lagi menyebut "salah ketik?",
karena transfer besar yang sah (mis. 500 juta pada 5–7 Mei) juga memicunya.

**Keamanan:** folder aplikasi juga terjangkau lewat djimatosuperapp.site/nyamanapp/…, sehingga
berkas di luar folder public (termasuk log error) bisa diunduh. `.htaccess` di folder aplikasi
kini menolak semua jalur itu kecuali folder public. nyamanapp.site tidak terpengaruh.

---

## 4. Halaman pembuka (logo) dan halaman login baru

Tidak ada migrasi untuk bagian ini.

- **Landing page dihapus.** Membuka nyamanapp.site kini menampilkan **logo senyum Nyaman** (kuning) dengan
  animasi singkat dan bilah loading (±1,7 detik), lalu otomatis masuk ke **halaman login**.
  Yang sudah login langsung ke Beranda. Logout juga kembali lewat halaman pembuka ini.
- **Halaman login** memakai latar ungu yang bergerak pelan (idle) dan sapaan
  **"Selamat datang 👋🏻"** dengan tangan melambai, sama seperti di Beranda (tanpa logo).
  Tautan "Kembali ke beranda" dihapus karena landing page sudah tidak ada.
- Animasi berhenti otomatis bila perangkat diatur "kurangi gerakan"; tanpa JavaScript,
  halaman pembuka tetap berpindah ke login.

---

## 5. Keuangan: saldo rekening harian, keuntungan, dan bonus karyawan

**Wajib setelah unggah:** `php artisan migrate --force` (satu tabel baru: saldo harian) lalu `php artisan optimize:clear`.

Menu **Keuangan** kini terbuka untuk **admin dan superadmin** (sebelumnya superadmin saja).
Isinya dua tab:

**Saldo Rekening (live report)**
- Isian cukup dua kolom: **tanggal** dan **saldo rekening**. Mengisi tanggal yang sama
  memperbarui saldonya, bukan menambah catatan baru. Tanggal masa depan dan saldo negatif ditolak.
- **Grafik saldo harian** (30 hari / 3 bulan / semua), diperbarui otomatis tiap 30 detik;
  arahkan kursor atau sentuh grafik untuk melihat angka dan perubahannya.
- **Grafik saldo tiap tanggal 5** mulai April 2026, lengkap dengan naik/turun dari bulan
  sebelumnya. Bila tanggal 5 tidak dicatat, dipakai saldo terakhir antara tanggal 1–5 bulan itu
  (tanggal yang dipakai ditampilkan); bila tanggal 1–5 kosong, bulan itu dibiarkan kosong.
- Ringkasan: saldo terakhir, perubahan dari catatan sebelumnya, selisih dari tanggal 5 bulan ini,
  dan apakah saldo hari ini sudah dicatat.

**Keuntungan & Bonus**
- Isi **omset** dan **pengeluaran** bulan ini → **keuntungan** (omset − pengeluaran) dan
  **bonus karyawan 5% dari keuntungan** langsung terhitung saat mengetik. Bulan rugi: bonus Rp 0.
- Tabel per bulan dan unduhan Excel ikut memuat kolom keuntungan dan bonus.
- Perbandingan omset dengan pendapatan menurut catatan kamar tetap ada.
- Pengingat "Belum dicatat" untuk bulan-bulan kosong dihapus (hotel baru berdiri April 2026,
  jadi bulan sebelumnya memang tidak ada datanya).

**Import & Export CSV** (di kedua tab, panel "Import / Export CSV")
- **Export CSV**: unduh semua data; berkasnya sekaligus contoh format impor.
- **Import CSV** untuk memasukkan data lama sekaligus:
  - Saldo: kolom `tanggal, saldo` — contoh `2026-04-05,25000000`.
  - Keuntungan: kolom `bulan, omset, pengeluaran, catatan (opsional)` — contoh `2026-04,40000000,28000000`.
    Kolom keuntungan & bonus di berkas diabaikan karena selalu dihitung ulang.
- Format yang dikenali: pemisah koma atau titik koma (Excel Indonesia), baris judul boleh ada;
  tanggal `2026-04-05`, `05/04/2026`, `5 Apr 2026`; bulan `2026-04`, `04/2026`, `April 2026`;
  angka `25000000`, `25.000.000`, `Rp 25.000.000`.
- Judul kolom yang salah ketik (mis. "Tanngal") tetap dikenali; judul yang tidak dikenal dibaca
  menurut urutan kolom. Kolom kosong di ujung (sisa Excel) diabaikan.
- Urutan tanggal dikenali otomatis dari isi berkas: `1/4/2026` = 1 April (gaya Indonesia), kecuali
  berkasnya jelas bergaya Amerika (`4/13/2026`).
- **Peringatan angka janggal:** setelah impor saldo, angka yang jauh berbeda (≥3×) dari saldo
  hari-hari sekitarnya ditampilkan untuk dicek — biasanya salah ketik satu digit. Datanya tetap tersimpan.
- Tanggal/bulan yang sudah ada **diperbarui**. Bila ada satu baris saja yang salah (tanggal tak
  dikenal, masa depan, angka tidak sah, tanggal ganda), **tidak ada yang disimpan** dan daftar
  baris yang perlu diperbaiki ditampilkan.

**Hak akses:** admin dan superadmin bisa melihat, mengisi, dan mengoreksi. **Menghapus**
catatan saldo maupun bulan tetap hanya superadmin.

**Tampilan ponsel:** halaman tidak lagi bisa tergeser ke samping di layar sempit, dan bilah atas
(lencana peran + tombol Keluar) tidak terpotong.

---

## 6. Check-in dan check-out per kamar dalam satu pesanan

Bagian ini tidak menambah migrasi.

Tamu dalam satu pesanan tidak selalu datang dan pulang bersamaan. Sebelumnya
kartu kamar staff membaca status dari kamar pertama saja: begitu kamar pertama
check-out, kamar lain kehilangan tombol Check-out. Status dan biaya late juga
disamakan untuk semua kamar.

**Check-out**
- Halaman check-out menampilkan semua kamar yang sedang ditempati. Centang kamar
  yang pulang sekarang, boleh satu, boleh semua.
- Status (tepat waktu, early, late, over late) dan biaya late dihitung **per kamar**
  menurut batas waktu kamar itu. Contoh: 201 pulang 11.30 tepat waktu, 202 pulang
  14.00 late check-out, dan biaya late hanya untuk 202.
- Foto kartu, bukti bayar, kondisi kamar, dan catatan tersimpan per kamar yang keluar.

**Sisa tagihan**
- Sisa tagihan milik pesanan, jadi baru **wajib lunas saat kamar terakhir** check-out.
- Selama masih ada kamar lain, centang "Tamu melunasi sisa tagihan sekarang" bila tamu
  ingin membayar lebih awal.
- Bukti biaya late tidak lagi otomatis melunasi sisa tagihan pesanan.

**Check-in**
- Halaman check-in juga bisa memilih kamar, jadi pesanan bisa masuk bertahap.
- Kamar yang menyusul memakai KTP dan jam check-in sendiri; KTP kamar pertama tidak tertimpa.
- Aturan bayar saat check-in tidak berubah: tagihan pesanan dilunasi saat kamar pertama masuk.

**Kartu kamar dan pengingat**
- Staff: status dibaca dari semua kamar (Sebagian check-in, Sebagian check-out), tiap
  kamar menampilkan keadaannya, dan tombol Check-out mengarah ke kamar yang masih menginap.
- Admin: jejak check-in, check-out, dan foto tampil per kamar bila berbeda.
- Pengingat check-out dan Tandai Late hanya mengenai kamar yang memang lewat jam.

**Perpanjangan**
- Pengajuan perpanjang atau ubah tanggal tidak lagi menyentuh kamar yang sudah check-out,
  jadi tagihannya tidak ikut bertambah.

---

## 7. Tombol Edit, filter kamar, extend otomatis, dan pengingat check-out

**Wajib setelah unggah:** `php artisan migrate --force` lalu `php artisan optimize:clear`.
Ada satu migrasi baru yang menambah dua kolom (catatan extend otomatis); data lama tidak berubah.

**Kartu kamar lebih ringkas**
- Staff: tombol "Pindah Kamar" dan "Ajukan Perubahan" digabung menjadi satu tombol
  **Edit**. Di dalamnya keduanya tetap terpisah: Pindah Kamar (langsung) dan
  Ajukan Perubahan (diputus admin). Di kartu tinggal Check-in/Check-out, Edit, Invoice.
- Admin & superadmin: tombol "Ubah / Hapus" dan "Pindah Kamar" per kamar digabung
  menjadi **Edit**.

**Beranda admin & superadmin tanpa pintasan**
- Carousel pintasan dihapus untuk admin dan superadmin (menu sudah di samping).
  Jumlah laporan terbuka tampil sebagai tautan kecil di bawah sapaan.
  Staff tetap memakai carousel.

**Filter di halaman Kamar (semua peran)**
- Tombol **Tiket.com, Umum, Breakfast, DP, Belum Lunas**, lengkap dengan jumlahnya,
  di samping filter nama. Bisa dipilih lebih dari satu: Tiket.com + Breakfast =
  pesanan Tiket.com yang pakai sarapan. Pilihan tetap berlaku saat tanggal diganti.

**Extend otomatis saat over late**
- Tamu yang masih di kamar lewat **17.00** pada tanggal check-out dianggap extend:
  sistem otomatis menambah 1 malam di **kamar yang sama**. Tagihan naik sebesar
  1 malam; pesanan lunas berubah menjadi DP dan selisihnya ditagih saat check-out.
- Bila kamar itu **sudah dipesan tamu lain**, sistem tidak memperpanjang dan
  memunculkan peringatan merah: check-out-kan tamu lama atau pindahkan salah satunya.
- Bila tamu akhirnya pulang malam itu juga, statusnya "Over late check-out" dan
  tidak ada biaya late tambahan (malamnya sudah masuk tagihan).
- Kartu kamar memberi tanda "Extend otomatis +N malam".

**Pengingat check-out (Beranda dan halaman Kamar, semua peran)**
- Mulai **11.00**: daftar tamu yang harus check-out hari ini (batas 12.00).
  Tamu yang kamarnya sudah dipesan orang lain ditaruh paling atas dengan tanda merah
  "tidak bisa extend".
- Lewat 12.00: daftar tamu yang lewat jam, dengan tombol Tandai Late, Check-out,
  dan Pindah Kamar bila kamarnya sudah dipesan.
- Daftar tamu yang di-extend otomatis hari ini.
- Pemeriksaan dijalankan setiap kali Beranda atau halaman Kamar dibuka, tanpa
  perlu cron di hosting.

---

## 8. Renovasi tampilan + pindah kamar langsung (tanpa persetujuan)

**Tampilan**
- Menu kini berada di **sidebar kiri** untuk semua peran (superadmin, admin,
  staff). Di layar HP sidebar berubah menjadi baris menu yang bisa digeser.
  Tombol "Beranda" di pojok halaman dihapus karena sudah ada di sidebar.
- Beranda menyapa dengan **lambaian tangan 👋🏻**, dan latar ungu gelap kini
  punya **cahaya ungu yang bergerak pelan (idle)**. Bila perangkat diatur
  "kurangi gerakan", animasinya otomatis berhenti.
- Tulisan dan kartu menu yang berjejal di beranda diganti **carousel**
  (geser otomatis tiap 5 detik, berhenti saat disentuh, ada tombol dan titik).
- Warna tetap ungu gelap.

**Staff bisa melihat detail booking**
- Di kartu kamar staff ada bagian **Detail pesanan**: semua kamar dalam pesanan,
  tanggal, malam × tarif, subtotal, total, diskon, tagihan, **DP / sudah
  dibayar**, **sisa**, status bayar, siapa yang menginput, dan tombol untuk
  membuka bukti bayar (awal, pelunasan, selisih).

**Pindah kamar langsung, tanpa persetujuan admin**
- Staff kini punya tombol **Pindah Kamar** sendiri, langsung diterapkan.
  Jenis "Pindah kamar" dihapus dari formulir pengajuan staff.
- Pengajuan pindah kamar lama yang masih menunggu tetap bisa diputus admin.
- **Hapus booking tetap terkunci** — hanya superadmin (staff & admin ditolak).

**Pindah kamar untuk pesanan beberapa kamar**
- Contoh: Mas Bintang memesan 209 dan 210. Halaman Pindah Kamar kini
  menampilkan **kedua kamar**; centang salah satu atau keduanya.
- Tiap kamar bisa dipilih: tanggal mulai pindah (seluruh masa / mulai malam
  tertentu), kamar tujuan, dan sarapan.
- Tukar kamar otomatis tetap berlaku bila kamar tujuan terisi.
- Selisih harga dihitung **sekali untuk seluruh pesanan**; bila naik, pilih
  dibayar sekarang (wajib bukti) atau ditagih nanti — sama seperti sebelumnya.
- Semua kamar dipindah dalam satu transaksi: bila satu gagal, tidak ada yang
  berubah.

Berkas: `routes/web.php`, `app/Support/PindahKamarHarian.php`,
`app/Http/Controllers/Admin/PindahKamarController.php`,
`app/Http/Controllers/Staff/BookingController.php`,
`app/Http/Controllers/Staff/PerubahanController.php`,
`resources/views/layouts/panel.blade.php`, `resources/views/dashboard.blade.php`,
`resources/views/partials/icon.blade.php`, `resources/views/admin/rooms/pindah.blade.php`,
`resources/views/staff/rooms/index.blade.php`, `resources/views/staff/rooms/_day.blade.php`,
`resources/views/staff/rooms/perubahan.blade.php`.

---

## 9. Siap dilewatkan Cloudflare

Aplikasi kini mengenali Cloudflare sebagai perantara resmi. Bila nanti domain
dipindah ke Cloudflare, IP pengunjung asli tetap terbaca benar — penting untuk
pembatas percobaan login, yang dikunci per email + IP. Hanya IP resmi Cloudflare
yang dipercaya, jadi permintaan dari tempat lain tidak bisa memalsukan IP-nya.
Selama domain belum lewat Cloudflare, perubahan ini tidak berpengaruh apa pun.

Berkas: `bootstrap/app.php`.

---

## 10. Invoice: buat dan cetak untuk admin & superadmin

Rute pembuatan invoice untuk admin sebenarnya sudah ada, tetapi tidak ada tombol
yang menuju ke sana — halaman Invoice admin hanya berisi ringkasan dan daftar,
sehingga membuat dan mencetak invoice terasa hanya bisa dilakukan staff.

- Halaman **Invoice** admin kini punya tombol **Buat Invoice**. Formulir dan
  aturannya sama persis dengan milik staff, dan invoice tercatat atas nama admin
  atau superadmin yang membuatnya.
- Setelah disimpan, admin langsung dibawa ke halaman cetak.
- Tombol di daftar invoice menjadi **Lihat / Cetak**.
- Panel kamar admin kini punya tombol **Buat Invoice** di setiap pesanan, sama
  seperti tombol Invoice di panel staff; isiannya terisi otomatis dari booking.
- Staff tidak berubah, dan halaman ringkasan Invoice admin tetap tertutup untuk staff.

Diuji untuk ketiga peran: superadmin, admin, dan staff.

---

## 11. Pencarian tamu, pindah kamar per hari, bukti bayar, dan perbaikan konsistensi

**Wajib setelah unggah:** jalankan `php artisan migrate --force`. Ada satu
migrasi baru yang menambah tiga kolom, tanpa mengubah data yang sudah ada.

### Pencarian tamu (semua peran)

Halaman kamar staff dan admin punya kolom "Cari tamu" di atas kalender.
Bisa mencari nama, nomor telepon, atau nomor kamar (persis), lintas tanggal.
Hasilnya per kunjungan, berurutan: sedang menginap, akan datang, lalu yang
sudah lewat. Klik hasil untuk membuka tanggalnya di kalender.

### Pindah kamar per hari

Contoh: tamu memesan 202 untuk 2 malam, lalu mulai malam kedua ingin pindah
ke 203 atau upgrade ke Family. Baris booking dipecah menjadi dua kamar yang
tetap **satu pesanan**: satu tamu, satu tagihan, satu diskon.

- **Admin:** tombol "Pindah Kamar" di rincian per kamar pada panel tanggal,
  dan di halaman ubah booking. Pilih malam mulai, kamar tujuan, dan sarapan;
  halaman menghitung selisih tagihan sebelum disimpan.
- **Staff:** "Ajukan Perubahan" → Pindah Kamar kini punya pilihan
  "Pindah mulai malam". Kartu pengajuan di panel admin menunjukkan dampak ke
  tagihan sebelum disetujui.
- Memilih tanggal check-in sebagai tanggal mulai = seluruh masa menginap
  pindah, dengan aturan tukar kamar yang lama.
- Tamu yang **sudah di dalam** dan pindah mulai hari ini: perpindahan langsung
  tercatat. Kamar lama ditutup dengan status "Pindah kamar", kamar baru aktif.
- Tamu yang pindahnya masih nanti: kamar baru menunggu dengan tombol
  **"Check-in Pindahan"**. Saat ditekan, kamar lama tertutup otomatis, KTP
  tidak diminta ulang, dan tidak ada biaya early check-in.
- Kamar yang berlanjut ke kamar lain tidak memicu peringatan late check-out.

### Pembayaran menyesuaikan tagihan

- Tagihan yang naik karena upgrade atau tambah malam tidak lagi diam-diam
  dianggap lunas. Pesanan lunas turun menjadi DP sebesar uang yang sudah
  diterima, dan selisihnya muncul sebagai sisa.
- Selisih bisa dibayar saat itu juga (dengan bukti) atau ditagih nanti.
- Sisa tagihan ditagih saat check-in pindahan, atau saat **check-out**: tamu
  tidak bisa di-check-out selama masih ada sisa tanpa bukti pelunasannya.
- Diskon persen dihitung ulang dari total baru. Tagihan yang turun di bawah
  uang yang sudah diterima dilaporkan sebagai kelebihan bayar.
- Pesanan Tiket.com yang di-upgrade: selisihnya dibayar di hotel dengan bukti,
  jadi dikerjakan lewat menu Pindah Kamar atau formulir ubah.

### Bukti bayar tidak diminta ulang

Penyebab keluhan: pesanan yang dilunasi saat check-in menyimpan buktinya
sebagai bukti pelunasan, tetapi formulir ubah hanya mengenali bukti awal.
Akibatnya, mengubah nomor kamar selalu meminta bukti lagi. Sekarang bukti dari
pintu mana pun dihitung. Bukti baru hanya diminta bila **uang yang diakui
diterima bertambah**, yaitu upgrade atau tambah malam pada pesanan yang tetap
ditandai lunas. Buktinya disimpan terpisah sebagai "bukti bayar selisih",
jadi bukti lama tidak tertimpa.

### Inkonsistensi lain yang ikut diperbaiki

- Memindah kamar ke kamar lain milik pesanan yang sama dulu lolos tanpa
  bentrok, sehingga dua baris bisa menempati satu kamar. Sekarang terhitung
  terisi, dan memilihnya berarti tukar kamar.
- Tukar kamar hanya terjadi bila tarif tamu yang digeser tidak berubah.
- Panel tanggal admin menghitung tagihan dari seluruh kamar pesanan, bukan
  hanya kamar yang menginap malam itu.
- Pratinjau tagihan di formulir ubah memakai malam masing-masing kamar.
- Check-in pesanan tidak lagi ikut menandai kamar pindahan; check-out pesanan
  tidak lagi menutup kamar yang belum pernah di-check-in.
- Bukti pelunasan saat check-in kini melunasi seluruh pesanan, bukan hanya
  kamar yang sedang di-check-in. Menyimpan ulang foto KTP tidak lagi
  "melunasi" pesanan yang tagihannya sudah naik.
- Panel admin tidak lagi menulis "tanpa bukti bayar" untuk pesanan yang punya
  bukti pelunasan.
- Riwayat tamu menghitung malam dari rentang kunjungan; isian otomatis invoice
  memakai malam dan sarapan masing-masing kamar.
- Hapus booking dan hapus data per bulan ikut membersihkan berkas bukti bayar
  selisih, supaya tidak tertinggal sebagai berkas yatim di hosting.
- Persetujuan "Perpanjang" ikut menyesuaikan pembayaran, dan pemeriksaan
  bentroknya memakai tanggal masing-masing kamar.
- Pesanan yang sudah dipecah tidak bisa diubah tanggalnya lewat pengajuan
  staff; tanggal di sambungan antar kamar dikunci di formulir ubah.

Diuji dengan 76 skenario otomatis lewat HTTP kernel Laravel (SQLite di
memori), ditambah pemeriksaan tampilan di browser dengan data demo.

---

## 12. Tukar kamar saat kamar tujuan terisi + ikon tab browser

**Tukar kamar.** Pindah kamar ke kamar yang sedang terisi dulu selalu ditolak,
dan itu paling menyulitkan justru saat hotel penuh: tamu 101 ingin ke 102,
tetapi 102 "terisi" jadi tidak bisa apa-apa. Sekarang, bila kamar tujuan
ditempati **tepat satu** tamu lain pada masa menginap yang akan berlaku, tamu
itu otomatis digeser ke kamar asal — keduanya bertukar kamar dalam satu
transaksi (101 → 102, dan penghuni 102 → 101).

Berlaku di dua tempat, dengan aturan yang sama (`app/Support/PindahKamar.php`):

- **Formulir ubah milik admin** — kamar terisi kini bisa dipilih; di bawah
  pilihan muncul catatan siapa yang akan ditukar sebelum disimpan.
- **Pengajuan pindah kamar dari staff** — kamar terisi bisa diajukan; kartu
  pengajuan di panel admin menyebut siapa yang ikut bergeser (atau kenapa
  tidak bisa) sebelum tombol setujui ditekan.

Tukar **tidak** dilakukan bila: kamar tujuan ditempati dua tamu atau lebih
(dua pesanan berurutan), tamu yang digeser tidak muat di kamar asal pada
tanggalnya sendiri, atau kamar asal Family sedangkan pesanan tamu itu tanpa
sarapan (Family wajib sarapan; mengubah sarapan orang lain diam-diam berarti
mengubah tagihannya diam-diam). Pindah ke kamar yang sama dengan tanggal baru
tetap bentrok biasa, bukan tukar.

**Ikon tab browser.** Logo piksel Nyaman dipasang sebagai favicon di semua
halaman (`public/favicon.svg`, `favicon.ico`, `apple-touch-icon.png`, lewat
`partials/favicon.blade.php`).

---

## 13. Pengajuan perubahan booking (staff → persetujuan admin)

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

## 14. Tipe kamar: 101 → Twin, 105 → King

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

## 15. Diskon

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

## 16. Booking untuk tanggal yang sudah lewat

Check-in boleh mundur sampai **7 hari**, dengan tombol **"Tamu datang dini hari
(malam kemarin)"** yang sekali tekan menyetel check-in = kemarin, check-out =
hari ini — persis kasus tamu jam 1 pagi tanggal 25 yang harus check-out siang
tanggal 25.

Batas 7 hari tetap ada supaya salah ketik bulan atau tahun tidak lolos
diam-diam. Diuji: tanggal 10 hari lampau ditolak dengan pesan yang menyebut
batasnya.

---

## 17. Wewenang penuh admin atas kamar + status check-in

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
