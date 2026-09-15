<?php

namespace Database\Seeders;

use App\Models\Absensi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\Report;
use App\Models\Room;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ----- Satu akun admin + satu akun staff -----
        // Kredensial TIDAK ditanam di kode. Isi lewat .env bila mau menentukan
        // sendiri; kalau kosong, kata sandi acak dibuat dan dicetak SEKALI di
        // sini — catat sebelum layar tertutup, sandi tidak bisa dilihat lagi.
        $admin = $this->buatAkun('admin', 'Administrator');
        $staff = $this->buatAkun('staff', 'Staff Resepsionis');

        // ----- Karyawan (data operasional, dibuat juga di produksi) -----
        foreach (['Arga', 'Ilham', 'Krisna', 'Lukman', 'Riyan'] as $nama) {
            Karyawan::firstOrCreate(['nama' => $nama], ['aktif' => true]);
        }

        // ----- 20 kamar -----
        // Lantai 1 (101–108): Twin = 101, 103, 107 — sisanya King.
        // Lantai 2 (201–212): Family = 202 — sisanya King.
        $kamar = [];
        foreach (range(101, 108) as $n) {
            $kamar[(string) $n] = in_array($n, [101, 103, 107], true) ? 'twin' : 'king';
        }
        foreach (range(201, 212) as $n) {
            $kamar[(string) $n] = $n === 202 ? 'family' : 'king';
        }

        foreach ($kamar as $nomor => $tipe) {
            Room::updateOrCreate(['nomor' => $nomor], ['tipe' => $tipe]);
        }

        // Bersihkan kamar berformat lama (mis. "King-01") yang tidak dipakai booking.
        Room::where('nomor', 'like', '%-%')
            ->whereDoesntHave('bookings')
            ->delete();

        // ----- Contoh data stok -----
        $samples = [
            ['nama' => 'Kopi Robusta 1kg',    'jumlah' => 24, 'harga' => 85000],
            ['nama' => 'Gula Pasir 1kg',      'jumlah' => 40, 'harga' => 17000],
            ['nama' => 'Susu UHT 1L',         'jumlah' => 12, 'harga' => 19000],
            ['nama' => 'Sabun Mandi',         'jumlah' => 48, 'harga' => 4500],
            ['nama' => 'Tisu Gulung',         'jumlah' => 36, 'harga' => 6500],
            ['nama' => 'Gelas Plastik (pak)', 'jumlah' => 0,  'harga' => 22000],
        ];

        foreach ($samples as $s) {
            Stock::updateOrCreate(['nama' => $s['nama']], $s + ['updated_by' => 'Seeder']);
        }

        // ----- Data contoh: JANGAN dibuat di server produksi -----
        if (app()->environment('production')) {
            $this->command?->info('Lingkungan produksi: kamar dan akun dibuat, data contoh dilewati.');

            return;
        }

        // ----- Contoh jadwal shift & absensi -----
        if (Jadwal::count() === 0) {
            $hariIni = now()->startOfDay();
            $orang   = Karyawan::orderBy('nama')->get()->keyBy('nama');

            // Hari ini: full team, salah satunya telat.
            $shiftHariIni = [
                'Arga'   => ['06:00', '15:00'],
                'Lukman' => ['08:00', '17:00'],
                'Riyan'  => ['09:00', '18:00'],
                'Krisna' => ['12:00', '21:00'],
                'Ilham'  => ['21:00', '07:00'],
            ];

            foreach ($shiftHariIni as $nama => [$mulai, $selesai]) {
                if (! isset($orang[$nama])) {
                    continue;
                }

                $j = Jadwal::create([
                    'karyawan_id' => $orang[$nama]->id,
                    'tanggal'     => $hariIni,
                    'jam_mulai'   => $mulai,
                    'jam_selesai' => $selesai,
                    'created_by'  => $staff->id,
                ]);

                // Arga absen tepat waktu, Lukman telat 12 menit, sisanya belum absen.
                $absen = match ($nama) {
                    'Arga'   => ['menit' => 0],
                    'Lukman' => ['menit' => 12],
                    default  => null,
                };

                if ($absen === null) {
                    continue;
                }

                Absensi::create([
                    'karyawan_id' => $j->karyawan_id,
                    'jadwal_id'   => $j->id,
                    'tanggal'     => $hariIni,
                    'waktu_absen' => $j->mulaiPada()->addMinutes($absen['menit']),
                    'status'      => $absen['menit'] > 0 ? Absensi::TELAT : Absensi::HADIR,
                    'menit_telat' => $absen['menit'],
                    'foto'        => 'absensi/contoh-' . strtolower($nama) . '.jpg',
                    'created_by'  => $staff->id,
                ]);
            }

            // Besok: satu orang libur (meniru "NB: Ilham libur").
            $shiftBesok = [
                'Riyan'  => ['06:00', '15:00'],
                'Lukman' => ['09:00', '18:00'],
                'Krisna' => ['12:00', '21:00'],
                'Arga'   => ['21:00', '07:00'],
            ];

            foreach ($shiftBesok as $nama => [$mulai, $selesai]) {
                if (! isset($orang[$nama])) {
                    continue;
                }

                Jadwal::create([
                    'karyawan_id' => $orang[$nama]->id,
                    'tanggal'     => $hariIni->copy()->addDay(),
                    'jam_mulai'   => $mulai,
                    'jam_selesai' => $selesai,
                    'created_by'  => $staff->id,
                ]);
            }
        }

        // ----- Booking contoh: seluruh skenario aturan hunian -----
        // Daftarnya ada di SkenarioSeeder (hardcode, bisa dijalankan ulang
        // sendiri dengan: php artisan db:seed --class=Database\Seeders\SkenarioSeeder).
        $this->call(SkenarioSeeder::class);

        // ----- Contoh tiket laporan (1 open, 1 selesai) -----
        if (Report::count() === 0) {
            Report::create([
                'user_id' => $staff->id,
                'judul'   => 'AC kamar 207 kurang dingin',
                'isi'     => 'AC di kamar 207 sudah disetel 18 derajat tapi ruangan tetap terasa hangat. Mohon dicek freon atau filternya sebelum akhir pekan.',
                'status'  => 'open',
            ]);

            Report::create([
                'user_id' => $staff->id,
                'judul'   => 'Stok handuk menipis',
                'isi'     => 'Handuk bersih di gudang lantai 2 tersisa sedikit. Sudah dipesan tambahan ke laundry, ditandai selesai setelah barang datang.',
                'status'  => 'selesai',
            ]);
        }
    }

    /**
     * Buat (atau perbarui) satu akun untuk sebuah peran.
     *
     * Sumber kredensial, berurutan:
     *   1. .env  -> ADMIN_EMAIL / ADMIN_PASSWORD / ADMIN_NAME (idem STAFF_*)
     *   2. kalau tidak ada -> email default + kata sandi acak yang dicetak sekali
     *
     * Akun yang sudah ada tidak ditimpa kata sandinya, supaya menjalankan
     * ulang `db:seed` tidak diam-diam mengunci pemilik akun.
     */
    private function buatAkun(string $role, string $namaBawaan): User
    {
        $awalan = strtoupper($role);
        $email  = env($awalan . '_EMAIL', $role . '@' . $this->domainBawaan());
        $nama   = env($awalan . '_NAME', $namaBawaan);
        $sandi  = env($awalan . '_PASSWORD');

        $ada = User::where('email', $email)->first();

        if ($ada) {
            $ada->update(['name' => $nama, 'role' => $role]);
            $this->command?->line("  Akun {$role} sudah ada: {$email} (kata sandi tidak diubah)");

            return $ada;
        }

        $acak  = $sandi === null;
        $sandi = $sandi ?: Str::password(16, symbols: false);

        $user = User::create([
            'name'     => $nama,
            'email'    => $email,
            'password' => Hash::make($sandi),
            'role'     => $role,
        ]);

        if ($acak) {
            $this->command?->warn("  Akun {$role} dibuat -> {$email}");
            $this->command?->warn("  Kata sandi   -> {$sandi}");
            $this->command?->warn('  CATAT SEKARANG: kata sandi ini tidak ditampilkan lagi.');
        } else {
            $this->command?->line("  Akun {$role} dibuat: {$email} (kata sandi dari .env)");
        }

        return $user;
    }

    /**
     * Domain email bawaan diturunkan dari APP_URL agar tidak memakai
     * alamat contoh yang sama di setiap pemasangan.
     */
    private function domainBawaan(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return in_array($host, ['localhost', '127.0.0.1'], true) ? 'localhost' : $host;
    }
}
