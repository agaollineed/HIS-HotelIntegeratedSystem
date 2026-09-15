<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\Report;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Dashboard sungguhan: kartu fitur sesuai role.
     * Admin juga melihat grafik pendapatan dan jadwal shift hari ini
     * lengkap dengan status absensi tiap karyawan.
     */
    public function __invoke()
    {
        $user = Auth::user();

        if (! $user->isAdmin()) {
            return view('dashboard', ['openReports' => 0] + $this->jadwalHariIni());
        }

        return view('dashboard', [
            'openReports' => Report::where('status', 'open')->count(),
        ] + $this->jadwalHariIni());
    }

    /**
     * Jadwal shift hari ini + siapa yang libur.
     *
     * @return array{jadwalHariIni: \Illuminate\Support\Collection, liburHariIni: \Illuminate\Support\Collection, tanggalHariIni: \Illuminate\Support\Carbon}
     */
    private function jadwalHariIni(): array
    {
        $hariIni = now()->startOfDay();

        $jadwal = Jadwal::with(['karyawan', 'absensi'])
            ->tanggal($hariIni)
            ->get()
            ->sortBy('jam_mulai')
            ->values();

        $libur = Karyawan::aktif()
            ->orderBy('nama')
            ->get()
            ->reject(fn (Karyawan $k) => $jadwal->contains('karyawan_id', $k->id))
            ->pluck('nama')
            ->values();

        return [
            'jadwalHariIni'  => $jadwal,
            'liburHariIni'   => $jadwal->isEmpty() ? collect() : $libur,
            'tanggalHariIni' => $hariIni,
        ];
    }
}
