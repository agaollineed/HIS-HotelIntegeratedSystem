<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SKENARIO UJI — data contoh yang menutup SEMUA aturan hunian.
 *
 * Semua angka dan jam di bawah ditulis apa adanya (hardcode) supaya hasil di
 * layar bisa dicocokkan langsung dengan tabel harapan yang dicetak di akhir
 * seeder. Tanggalnya relatif terhadap HARI SEEDER DIJALANKAN, jadi skenario
 * selalu jatuh di sekitar hari ini.
 *
 * Jalankan ulang kapan saja:
 *
 *     php artisan db:seed --class=Database\\Seeders\\SkenarioSeeder
 *
 * Skenario lama (dikenali dari awalan nomor telepon) dihapus lebih dulu,
 * jadi menjalankan berkali-kali tidak menumpuk data.
 *
 * ATURAN YANG DIUJI
 *   Harga    : King/Twin 300.000 dengan sarapan, 250.000 tanpa sarapan.
 *              Family 450.000 dan TIDAK boleh tanpa sarapan.
 *   Early CI : masuk sebelum 12.30 -> tambahan 1x harga kamar penuh.
 *              12.30-13.00 tetap berstatus early, tanpa biaya.
 *   Late CO  : lewat 12.00 -> 1/2 harga kamar, berlaku sampai 17.00.
 *   Over late: lewat 17.00 -> naik menjadi 1x harga kamar penuh.
 *   Foto     : early CI, late CO, dan over late CO wajib ada bukti bayar.
 *   Early CO : pergi sebelum 09.00 tanggal check-out. Kamarnya LANGSUNG
 *              terbuka dan boleh dijual lagi lewat booking baru (S13).
 */
class SkenarioSeeder extends Seeder
{
    /**
     * Awalan telepon penanda data skenario. Dipakai untuk menghapus data
     * skenario lama tanpa menyentuh booking asli.
     */
    private const AWALAN_TELEPON = '0899000';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('SkenarioSeeder dilewati: ini lingkungan produksi.');

            return;
        }

        $staff = User::where('role', 'staff')->first() ?? User::first();

        if (! $staff) {
            $this->command?->error('Belum ada akun. Jalankan db:seed lebih dulu.');

            return;
        }

        $rooms = Room::all()->keyBy('nomor');
        $foto  = $this->siapkanFotoContoh();

        Booking::where('telepon', 'like', self::AWALAN_TELEPON . '%')->delete();
        Report::where('judul', 'like', '[SKENARIO]%')->delete();

        $dibuat = [];

        foreach ($this->skenario() as $s) {
            $grup = (string) Str::ulid();

            foreach ($s['kamar'] as $nomor) {
                $room = $rooms->get((string) $nomor);

                if (! $room) {
                    $this->command?->warn('  Kamar ' . $nomor . ' tidak ada — skenario ' . $s['kode'] . ' dilewati.');

                    continue;
                }

                $booking = $this->buat($s, $room, $grup, $staff, $foto);
                $dibuat[] = [$s, $booking];
            }
        }

        $this->cetakTabel($dibuat);
    }

    // ------------------------------------------------------------------
    // Daftar skenario (hardcode)
    // ------------------------------------------------------------------

    /**
     * Setiap skenario:
     *   mulai / malam  : geser hari dari HARI INI, dan lama menginap
     *   ci / co        : [geser hari, 'HH:MM'] atau null bila belum terjadi
     *   panel          : tanggal panel yang harus dibuka untuk melihatnya
     */
    private function skenario(): array
    {
        return [
            [
                'kode' => 'S01', 'kamar' => ['101'], 'nama' => 'S01 Ayu Kartika',
                'breakfast' => true, 'sumber' => 'umum', 'bayar' => 'belum',
                'mulai' => 0, 'malam' => 1, 'ci' => null, 'co' => null,
                'harap' => 'King + sarapan = Rp 300.000/malam. Belum check-in.',
            ],
            [
                'kode' => 'S02', 'kamar' => ['102'], 'nama' => 'S02 Bagas Nugroho',
                'breakfast' => false, 'sumber' => 'umum', 'bayar' => 'lunas',
                'mulai' => 0, 'malam' => 1, 'ci' => null, 'co' => null,
                'harap' => 'King tanpa sarapan = Rp 250.000/malam.',
            ],
            [
                'kode' => 'S03', 'kamar' => ['202'], 'nama' => 'S03 Citra Melati',
                'breakfast' => true, 'sumber' => 'umum', 'bayar' => 'lunas',
                'mulai' => 0, 'malam' => 1, 'ci' => null, 'co' => null,
                'harap' => 'Family = Rp 450.000/malam, wajib sarapan (opsi tanpa sarapan terkunci di form).',
            ],
            [
                'kode' => 'S04', 'kamar' => ['103'], 'nama' => 'S04 Dimas Prayoga',
                'breakfast' => true, 'sumber' => 'umum', 'bayar' => 'belum',
                'mulai' => 0, 'malam' => 1, 'ci' => [0, '11:20'], 'co' => null,
                'harap' => 'Early check-in berbayar (sebelum 12.30): tambahan Rp 300.000. Bukti bayar wajib.',
            ],
            [
                'kode' => 'S05', 'kamar' => ['104'], 'nama' => 'S05 Endah Sari',
                'breakfast' => true, 'sumber' => 'umum', 'bayar' => 'lunas',
                'mulai' => 0, 'malam' => 1, 'ci' => [0, '12:45'], 'co' => null,
                'harap' => 'Early check-in TOLERANSI (12.30-13.00): status early, biaya Rp 0.',
            ],
            [
                'kode' => 'S06', 'kamar' => ['105'], 'nama' => 'S06 Fajar Ramadhan',
                'breakfast' => false, 'sumber' => 'umum', 'bayar' => 'lunas',
                'mulai' => 0, 'malam' => 2, 'ci' => [0, '14:10'], 'co' => null,
                'harap' => 'Check-in normal (setelah 13.00). Twin tanpa sarapan = Rp 250.000/malam.',
            ],
            [
                'kode' => 'S07', 'kamar' => ['106'], 'nama' => 'S07 Gilang Saputra',
                'breakfast' => true, 'sumber' => 'umum', 'bayar' => 'lunas',
                'mulai' => -1, 'malam' => 1, 'ci' => [-1, '14:00'], 'co' => [0, '14:30'],
                'harap' => 'Late check-out (12.00-17.00) = 1/2 harga = Rp 150.000. Foto pembayaran ada.',
            ],
            [
                'kode' => 'S08', 'kamar' => ['107'], 'nama' => 'S08 Hana Puspita',
                'breakfast' => true, 'sumber' => 'umum', 'bayar' => 'lunas',
                'mulai' => -1, 'malam' => 1, 'ci' => [-1, '13:30'], 'co' => [0, '18:40'],
                'rusak' => 'Gelas pecah dan handuk hilang 1.',
                'harap' => 'OVER late check-out (lewat 17.00) = 1x harga penuh = Rp 300.000, plus tiket kerusakan.',
            ],
            [
                'kode' => 'S09', 'kamar' => ['108'], 'nama' => 'S09 Irfan Maulana',
                'breakfast' => true, 'sumber' => 'umum', 'bayar' => 'lunas',
                'mulai' => 0, 'malam' => 1, 'ci' => [0, '15:45'], 'co' => [0, '16:01'],
                'harap' => 'Early check-out (pergi sebelum 09.00 tanggal keluar). Tanpa biaya.',
            ],
            [
                'kode' => 'S10', 'kamar' => ['201'], 'nama' => 'S10 Joko Susilo',
                'breakfast' => true, 'sumber' => 'tiket', 'bayar' => 'lunas',
                'mulai' => -1, 'malam' => 1, 'ci' => [-1, '15:00'], 'co' => [0, '10:15'],
                'harap' => 'Check-out tepat waktu (09.00-12.00). Tanpa biaya tambahan.',
            ],
            [
                'kode' => 'S11', 'kamar' => ['203'], 'nama' => 'S11 Kirana Dewi',
                'breakfast' => true, 'sumber' => 'umum', 'bayar' => 'lunas',
                'mulai' => -1, 'malam' => 1, 'ci' => [-1, '14:20'], 'co' => null,
                'harap' => 'Belum di-check-out padahal sudah lewat 12.00 -> toast peringatan late di panel kamar.',
            ],
            [
                'kode' => 'S12', 'kamar' => ['204', '205'], 'nama' => 'S12 Lukman Hakim',
                'breakfast' => true, 'sumber' => 'umum', 'bayar' => 'lunas',
                'mulai' => 0, 'malam' => 3, 'ci' => [0, '15:00'], 'co' => null,
                'harap' => '2 kamar dalam 1 pesanan (grup). Check-in sekali untuk dua kamar.',
            ],
            [
                'kode' => 'S13', 'kamar' => ['108'], 'nama' => 'S13 Maya Anggraini',
                'breakfast' => false, 'sumber' => 'umum', 'bayar' => 'belum',
                'mulai' => 0, 'malam' => 1, 'ci' => null, 'co' => null,
                'harap' => 'Booking BARU di kamar 108 yang tadi early check-out (S09) — bukti kamar langsung terbuka.',
            ],
            [
                'kode' => 'S14', 'kamar' => ['206'], 'nama' => 'S14 Nanda Pratiwi',
                'breakfast' => false, 'sumber' => 'umum', 'bayar' => 'belum',
                'mulai' => 0, 'malam' => 1, 'ci' => null, 'co' => null,
                'harap' => 'Belum lunas: bukti pelunasan wajib diunggah saat check-in.',
            ],
            [
                'kode' => 'S15', 'kamar' => ['207'], 'nama' => 'S15 Oscar Wijaya',
                'breakfast' => true, 'sumber' => 'tiket', 'bayar' => 'lunas',
                'mulai' => 1, 'malam' => 2, 'ci' => null, 'co' => null,
                'harap' => 'Pesanan Tiket.com: status bayar terkunci lunas. Menginap mulai besok.',
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Pembuatan satu baris booking
    // ------------------------------------------------------------------

    /**
     * @param  array<string, string>  $foto  path contoh per folder:
     *                                       ktp / bayar / kartu / kerusakan
     */
    private function buat(array $s, Room $room, string $grup, User $staff, array $foto): Booking
    {
        $checkIn  = $this->hari($s['mulai']);
        $checkOut = $checkIn->copy()->addDays($s['malam']);

        $booking = Booking::create([
            'room_id'      => $room->id,
            'grup'         => $grup,
            'nama_tamu'    => $s['nama'],
            'telepon'      => $this->telepon($s['kode']),
            'breakfast'    => $s['breakfast'],
            'sumber'       => $s['sumber'],
            'status_bayar' => $s['sumber'] === Booking::SUMBER_TIKET ? Booking::BAYAR_LUNAS : $s['bayar'],
            'check_in'     => $checkIn,
            'durasi'       => $s['malam'],
            'check_out'    => $checkOut,
            'created_by'   => $staff->id,
        ]);

        $booking->setRelation('room', $room);

        // ---------- Check-in ----------
        if ($s['ci'] !== null) {
            $waktu = $this->waktu($s['ci']);

            $booking->checked_in_at      = $waktu;
            $booking->checkin_menit_awal = $booking->menitEarlyCheckinPada($waktu);
            $booking->foto_ktp           = $foto['ktp'];

            // Early check-in maupun tagihan yang belum lunas sama-sama
            // meninggalkan bukti bayar saat tamu tiba.
            if ($booking->butuhFotoPelunasan($waktu)) {
                $booking->foto_pelunasan = $foto['bayar'];
                $booking->status_bayar   = Booking::BAYAR_LUNAS;
            }
        }

        // ---------- Check-out ----------
        if ($s['co'] !== null) {
            $waktu            = $this->waktu($s['co']);
            [$status, $menit] = $booking->statusCheckoutPada($waktu);

            $booking->checked_out_at      = $waktu;
            $booking->checkout_status     = $status;
            $booking->checkout_menit      = $menit;
            $booking->foto_kartu          = $foto['kartu'];
            $booking->checkout_by         = $staff->id;
            $booking->checkout_kondisi_ok = ! isset($s['rusak']);

            // Late maupun over late: bukti pembayaran kelebihan jam wajib.
            if ($booking->biayaLateCheckout($waktu) > 0) {
                $booking->foto_pembayaran_late = $foto['bayar'];
            }

            if (isset($s['rusak'])) {
                $report = Report::create([
                    'user_id' => $staff->id,
                    'judul'   => '[SKENARIO] Kerusakan / barang tertinggal — kamar ' . $room->nomor,
                    'isi'     => $s['rusak'],
                    'status'  => 'open',
                ]);

                $booking->foto_kerusakan   = $foto['kerusakan'];
                $booking->report_id        = $report->id;
                $booking->checkout_catatan = $s['rusak'];
            }
        }

        $booking->save();

        return $booking;
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    private function hari(int $geser): Carbon
    {
        return Carbon::today()->addDays($geser);
    }

    /** @param  array{0: int, 1: string}  $spec */
    private function waktu(array $spec): Carbon
    {
        return $this->hari($spec[0])->setTimeFromTimeString($spec[1]);
    }

    private function telepon(string $kode): string
    {
        return self::AWALAN_TELEPON . str_pad(preg_replace('/\D/', '', $kode), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Satu gambar contoh dipakai ulang untuk semua jenis foto, disalin ke
     * tiap folder karena penyaji foto hanya melayani path dari folder yang
     * sesuai (ktp/, bayar/, kartu/, kerusakan/).
     *
     * @return array<string, string>
     */
    private function siapkanFotoContoh(): array
    {
        $biner = base64_decode(self::FOTO_CONTOH_B64);
        $disk  = Storage::disk('local');
        $hasil = [];

        foreach (['ktp', 'bayar', 'kartu', 'kerusakan'] as $folder) {
            $path = $folder . '/skenario-contoh.jpg';

            if (! $disk->exists($path)) {
                $disk->put($path, $biner);
            }

            $hasil[$folder] = $path;
        }

        return $hasil;
    }

    /**
     * @param  array<int, array{0: array, 1: Booking}>  $dibuat
     */
    private function cetakTabel(array $dibuat): void
    {
        $baris = [];

        foreach ($dibuat as [$s, $booking]) {
            /** @var Booking $booking */
            $status = match (true) {
                $booking->isCheckedOut() => $booking->labelStatusCheckout(),
                $booking->earlyCheckin() => 'Early check-in',
                $booking->isCheckedIn()  => 'Sudah check-in',
                default                  => 'Belum check-in',
            };

            $biaya = $booking->biayaEarlyCheckin() + $booking->biayaLateCheckout();

            $baris[] = [
                $s['kode'],
                $booking->room?->nomor,
                $booking->check_in->format('d/m') . '-' . $booking->check_out->format('d/m'),
                $booking->check_in->format('d/m'),
                Booking::rupiah($booking->hargaMalam()),
                $status,
                $biaya > 0 ? Booking::rupiah($biaya) : '—',
            ];
        }

        $this->command?->newLine();
        $this->command?->info('SKENARIO UJI SIAP — ' . count($baris) . ' baris booking dibuat.');
        $this->command?->table(
            ['Kode', 'Kamar', 'Menginap', 'Buka panel', 'Tarif/malam', 'Status', 'Biaya tambahan'],
            $baris
        );

        foreach ($this->skenario() as $s) {
            $this->command?->line('  ' . $s['kode'] . '  ' . $s['harap']);
        }

        $this->command?->newLine();
        $this->command?->line('  Catatan:');
        $this->command?->line('  - Kolom "Buka panel" = tanggal yang harus dipilih di kalender panel Kamar.');
        $this->command?->line('  - S07, S08, S10 masa sewanya berakhir hari ini, jadi entrinya berada di panel');
        $this->command?->line('    tanggal KEMARIN (malam terakhir yang ditagih).');
        $this->command?->line('  - S11 memunculkan toast late hanya bila jam sekarang sudah lewat 12.00.');
        $this->command?->line('  - Semua telepon skenario berawalan ' . self::AWALAN_TELEPON . '.');
        $this->command?->newLine();
    }

    /** Gambar JPEG kecil sebagai pengganti foto asli (KTP, kartu, bukti bayar). */
    private const FOTO_CONTOH_B64 =
        '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDABIMDRANCxIQDhAUExIVGywdGxgYGzYnKSAsQDlEQz85Pj1HUGZXR0thTT0+WXla'
        . 'YWltcnNyRVV9hnxvhWZwcm7/2wBDARMUFBsXGzQdHTRuST5Jbm5ubm5ubm5ubm5ubm5ubm5ubm5ubm5ubm5ubm5ubm5ubm5u'
        . 'bm5ubm5ubm5ubm5ubm7/wAARCACgAPADASIAAhEBAxEB/8QAGAABAQEBAQAAAAAAAAAAAAAAAAUEAwL/xAA2EAABAgMGBQMC'
        . 'BQMFAAAAAAAAAQIDBBEFEhZUo9EGITWCshMxQSJRFGGBkaEjMmIzQlJxwf/EABYBAQEBAAAAAAAAAAAAAAAAAAABAv/EABQR'
        . 'AQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhEDEQA/AIstLxZuO2DAbfiOrRKolaJX5N2HbUyuo3ccNddlu7xUmGhTw7amV1G7'
        . 'jDtqZXUbuTABTw7amV1G7jDtqZXUbuTABTw7amV1G7jDtqZXUbuTABTw7amV1G7jDtqZXUbuTABTw7amV1G7jDtqZXUbuTAB'
        . 'Tw7amV1G7jDtqZXUbuTABTw7amV1G7jDtqZXUbuTABTw7amV1G7jDtqZXUbuTABTw7amV1G7jDtqZXUbuTABTw7amV1G7jDt'
        . 'qZXUbuTABTw7amV1G7jDtqZXUbuTABTw7amV1G7jDtqZXUbuTABTw7amV1G7jDtqZXUbuTABTw7amV1G7mGZl4spHdBjtuRG'
        . '0qlUWlUr8HIp8S9dme3xQBw112W7vFSYU+Guuy3d4qTAAAKAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABT4l67M9vihMKfEvX'
        . 'Znt8UIHDXXZbu8VJhT4a67Ld3ipMAAAoAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAFPiXrsz2+KEwp8S9dme3xQgcNddlu7xU'
        . 'mFPhrrst3eKkwAACgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAU+JeuzPb4oTCnxL12Z7fFCBw112W7vFSYU+Guuy3d4qTAAO'
        . '0CA18N8SK9WQ2URVRt5VVfZESqfZf2OqysJjViRI6+krrrHMZeV3JFXlVKUqlefuBkBujWasKUfGSKjla+l1E92Uat6ve3l+'
        . 'YWSgsiTbIkeIiyyrW7CRbyI5G/8AL8wMINkSSY18WE2Mro8JFVzVZROXNURa+6c/j4U8xJP0405Dv1/DV50/uo9G/p71Ayg1'
        . 'tlGKyXRIj1jTCVYxGJSt5Wola/l9gstAVr3Q47npCpf/AKdOVaVTnz5096e4GQG+LIwIaOcsxEuNaxVX0kr9baoifV9q/sck'
        . 'k0WYhtR96E9ixEfSi3UrXl90ov7AZQakk0STbHc9yXkVW/RVvJfZV+F5e1PsdHSMFs7ElkjxFWF6l93pJ/sRV5fVz9gMINTJ'
        . 'eXdBiRVjxUhsc1v+klVVUcvte/xPcaShy8JHxozvqcqNuMqipda5F5qnujkAxAow7NZEf6bYzliemx9FZRKvRKIi1+7k+PuT'
        . 'gAAKAAAAAAAAAAAFPiXrsz2+KEwp8S9dme3xQgcNddlu7xUmFPhrrst3eKkwDvAjthw4kKKxXw30VUR11UVK0VFov3X9zos1'
        . 'Ce1YcSAvpI68xrH3VbyRF50WtaJUyADctpKroarDS6x7lVteSsVrW3f2b7nOJOepGnIlyn4mvKv9tXo79fahlAGx86xzokVI'
        . 'SpHiNVHPv8ufJVRKe68/n5PUe040d03fc9WTFaMV6qjPqR3/AJQwgDWloR2Q5dkJ72JBT2Ry0ct5XVp+v8HmJMwvTiNgQVhr'
        . 'FpfVX3kpWtE5ckrT7+xmAGtZ1r1c2LCVYbmQ2q1HUVFY1GoqLT/v4+Q2cT8TDdcVsJjFho1FqqNVFRef3+pVMgA1QZtsGC5r'
        . 'YSo9zFYqo/6XIteatpzVK/f4Q0RLWR84sxdmKqr/AKVj1u3kVPp+nlSv8E0AbVnIT2RGRYceIj3MdV0ZFdVqOT3u/wCX8Htb'
        . 'TvwVhubFal5VRIUW6lLrWoipRa0RpPAFGHarke1YiPcxjGemy9VGvYiIi/rT+ScAAABQAAAAAAAAAAAp8S9dme3xQmFPiXrs'
        . 'z2+KEDhrrst3eKkwp8Nddlu7xUmAAAUAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACnxL12Z7fFCYU+JeuzPb4oQOGuuy3d4qT'
        . 'Cnw112W7vFSYAABQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAKfEvXZnt8UJhT4l67M9vihA4a67Ld3ipMKfDXXZbu8VJgAAF'
        . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAp8S9dme3xQmFPiXrsz2+KEDhrrst3eKkw6y0xFlI7Y0B1yI2tFoi0qlPk3YitTN'
        . 'abdgJgKeIrUzWm3YYitTNabdgJgKeIrUzWm3YYitTNabdgJgKeIrUzWm3YYitTNabdgJgKeIrUzWm3YYitTNabdgJgKeIrUz'
        . 'Wm3YYitTNabdgJgKeIrUzWm3YYitTNabdgJgKeIrUzWm3YYitTNabdgJgKeIrUzWm3YYitTNabdgJgKeIrUzWm3YYitTNabd'
        . 'gJgKeIrUzWm3YYitTNabdgJgKeIrUzWm3YYitTNabdgJgKeIrUzWm3YYitTNabdgJgKeIrUzWm3YYitTNabdgJhT4l67M9vi'
        . 'gxFama027GGZmIs3HdGjuvxHUqtEStEp8Af/2Q==';
}
