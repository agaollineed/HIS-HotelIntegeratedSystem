<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Penyusunan jadwal shift oleh staff.
 *
 * Meniru kebiasaan sebelumnya (jadwal diketik di grup): disusun H-1 untuk
 * hari berikutnya, karyawan yang tidak diberi jam berarti libur, dan bila
 * semua kebagian jam maka statusnya "Full Team".
 */
class JadwalController extends Controller
{
    /** Preset jam yang paling sering dipakai. */
    public const PRESET = ['06:00-15:00', '08:00-17:00', '09:00-18:00', '12:00-21:00', '21:00-07:00'];

    public function index(Request $request)
    {
        // Bawaannya BESOK, karena jadwal disusun H-1.
        $tanggal = $this->tanggalDari($request->query('tanggal'), now()->addDay());

        $karyawan = Karyawan::aktif()->orderBy('nama')->get();
        $jadwal   = Jadwal::with('karyawan')->tanggal($tanggal)->get()->keyBy('karyawan_id');

        return view('staff.jadwal.index', [
            'tanggal'       => $tanggal,
            'karyawan'      => $karyawan,
            'jadwal'        => $jadwal,
            'libur'         => $this->namaLibur($karyawan, $jadwal),
            'preset'        => self::PRESET,
            'besok'         => now()->addDay()->toDateString(),
            'hariIni'       => now()->toDateString(),
            'semuaKaryawan' => Karyawan::orderBy('nama')->get(),
        ]);
    }

    /**
     * Simpan jadwal satu tanggal (menimpa jadwal lama pada tanggal tsb).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tanggal'             => 'required|date|after_or_equal:today',
            'shift'               => 'array',
            'shift.*.bertugas'    => 'nullable|boolean',
            'shift.*.jam_mulai'   => 'nullable|date_format:H:i',
            'shift.*.jam_selesai' => 'nullable|date_format:H:i',
        ], [
            'tanggal.required'                => 'Tanggal jadwal wajib diisi.',
            'tanggal.after_or_equal'          => 'Jadwal hanya bisa disusun untuk hari ini atau ke depan.',
            'shift.*.jam_mulai.date_format'   => 'Format jam mulai harus HH:MM.',
            'shift.*.jam_selesai.date_format' => 'Format jam selesai harus HH:MM.',
        ]);

        $tanggal = Carbon::parse($data['tanggal'])->startOfDay();
        $baris   = [];

        foreach ($data['shift'] ?? [] as $karyawanId => $isi) {
            if (empty($isi['bertugas'])) {
                continue; // tidak dicentang = libur
            }

            if (empty($isi['jam_mulai']) || empty($isi['jam_selesai'])) {
                return back()->withInput()->withErrors([
                    'shift' => 'Karyawan yang bertugas harus punya jam mulai dan jam selesai.',
                ]);
            }

            $baris[] = [
                'karyawan_id' => (int) $karyawanId,
                'jam_mulai'   => $isi['jam_mulai'],
                'jam_selesai' => $isi['jam_selesai'],
            ];
        }

        if ($baris === []) {
            return back()->withInput()->withErrors([
                'shift' => 'Minimal satu karyawan harus bertugas pada tanggal tersebut.',
            ]);
        }

        $idValid = Karyawan::aktif()->pluck('id')->all();

        DB::transaction(function () use ($tanggal, $baris, $idValid) {
            // Jadwal lama tanggal ini dihapus, bukan diperbarui satu per satu:
            // karyawan yang dicoret dari jadwal harus benar-benar hilang.
            //
            // Menghapusnya membuat absensi yang sudah terlanjur dicatat
            // kehilangan jadwal_id-nya (foreign key nullOnDelete). Absensi itu
            // TIDAK ikut terhapus — dan memang tidak boleh, karena ia bukti
            // kehadiran yang sudah terjadi. Tetapi kalau dibiarkan yatim,
            // halaman absensi akan menampilkan orang itu sebagai "belum absen",
            // ia mencoba absen lagi, dan indeks unik (karyawan_id, tanggal)
            // menolaknya dengan galat 500 tanpa penjelasan apa pun.
            //
            // Karena itu absensi yang ada dicatat dulu, lalu ditautkan ulang
            // ke jadwal baru milik karyawan yang sama.
            $absenLama = Absensi::whereDate('tanggal', $tanggal->toDateString())
                ->get()
                ->keyBy('karyawan_id');

            Jadwal::tanggal($tanggal)->delete();

            foreach ($baris as $b) {
                if (! in_array($b['karyawan_id'], $idValid, true)) {
                    continue;
                }

                $jadwal = Jadwal::create($b + [
                    'tanggal'    => $tanggal,
                    'created_by' => Auth::id(),
                ]);

                if ($absen = $absenLama->get($jadwal->karyawan_id)) {
                    $absen->forceFill(['jadwal_id' => $jadwal->id])->save();
                }
            }
        });

        return redirect()
            ->route('staff.jadwal.index', ['tanggal' => $tanggal->toDateString()])
            ->with('success', 'Jadwal ' . $tanggal->translatedFormat('l, j F Y') . ' berhasil disimpan ('
                . count($baris) . ' orang bertugas).');
    }

    /**
     * Tambah karyawan baru ke daftar.
     */
    public function tambahKaryawan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => 'required|string|max:100',
        ], ['nama.required' => 'Nama karyawan wajib diisi.']);

        Karyawan::create(['nama' => trim($data['nama']), 'aktif' => true]);

        return back()->with('success', 'Karyawan ' . trim($data['nama']) . ' ditambahkan.');
    }

    /**
     * Aktifkan / nonaktifkan karyawan.
     * Sengaja tidak dihapus agar riwayat jadwal & absensinya tetap utuh.
     */
    public function toggleKaryawan(Karyawan $karyawan): RedirectResponse
    {
        $karyawan->update(['aktif' => ! $karyawan->aktif]);

        return back()->with('success', $karyawan->nama . ' ditandai '
            . ($karyawan->aktif ? 'aktif' : 'nonaktif') . '.');
    }

    // ------------------------------------------------------------------

    private function tanggalDari(?string $nilai, Carbon $bawaan): Carbon
    {
        return (is_string($nilai) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) && strtotime($nilai))
            ? Carbon::createFromFormat('Y-m-d', $nilai)->startOfDay()
            : $bawaan->copy()->startOfDay();
    }

    /**
     * Karyawan aktif yang tidak dijadwalkan pada tanggal tsb = libur.
     */
    private function namaLibur(Collection $karyawan, Collection $jadwal): Collection
    {
        if ($jadwal->isEmpty()) {
            return collect();
        }

        return $karyawan->reject(fn (Karyawan $k) => $jadwal->has($k->id))->pluck('nama')->values();
    }
}
