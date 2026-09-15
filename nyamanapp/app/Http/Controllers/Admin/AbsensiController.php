<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AbsensiExport;
use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Panel absensi untuk admin — hanya melihat, tidak bisa mengabsenkan siapa pun.
 *
 * Yang dilihat admin bukan cuma "siapa hadir", melainkan DUA foto per shift:
 * selfie karyawan dan foto laci kartu kunci. Foto kartu itulah yang membuat
 * hitungan kunci bisa direkonsiliasi dengan data kamar — tanpa akses admin ke
 * foto tersebut, pengendaliannya hanya ada di tangan orang yang diawasi.
 */
class AbsensiController extends Controller
{
    public function index(Request $request)
    {
        $tanggal = $this->tanggalDari($request->query('tanggal'));

        return view('admin.absensi.index', [
            'tanggal' => $tanggal,
            'shift'   => $this->shiftHari($tanggal),
            'prev'    => $tanggal->copy()->subDay()->toDateString(),
            'next'    => $tanggal->copy()->addDay()->toDateString(),
        ]);
    }

    /**
     * Unduh kehadiran satu bulan (mengikuti bulan tanggal yang dilihat).
     */
    public function export(Request $request)
    {
        $tanggal = $this->tanggalDari($request->query('tanggal'));

        return \Maatwebsite\Excel\Facades\Excel::download(
            new AbsensiExport($tanggal),
            'absensi-' . $tanggal->format('Y-m') . '.xlsx'
        );
    }

    // ------------------------------------------------------------------

    private function tanggalDari(?string $nilai): Carbon
    {
        return (is_string($nilai) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) && strtotime($nilai))
            ? Carbon::createFromFormat('Y-m-d', $nilai)->startOfDay()
            : now()->startOfDay();
    }

    /**
     * Shift pada tanggal tsb, terurut jam mulai, lengkap dengan absensinya.
     */
    private function shiftHari(Carbon $tanggal): Collection
    {
        return Jadwal::with(['karyawan', 'absensi'])
            ->tanggal($tanggal)
            ->get()
            ->sortBy('jam_mulai')
            ->values();
    }
}
