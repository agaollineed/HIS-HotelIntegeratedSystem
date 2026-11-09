<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Jadwal;
use App\Models\Report;
use App\Models\StockLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Hapus data per bulan — pelengkap tombol Unduh Excel.
 *
 * Alur yang dimaksudkan: unduh dulu arsip Excel bulan tsb, baru hapus.
 * Karena itu setiap halaman admin menaruh kedua tombol bersebelahan, dan
 * dialog konfirmasinya mengingatkan untuk mengunduh lebih dulu.
 *
 * Dua aturan yang dijaga:
 *   1. Berkas foto ikut dihapus dari disk, bukan hanya barisnya — kalau
 *      tidak, kuota hosting tetap penuh oleh foto yatim tanpa pemiliknya.
 *   2. Foto yang dipakai bersama (satu KTP untuk lima kamar segrup, atau
 *      grup yang melintasi batas bulan) hanya dihapus bila TIDAK ada baris
 *      tersisa yang masih menunjuknya.
 */
class PurgeController extends Controller
{
    /** Booking (kamar) yang check-in-nya jatuh pada bulan tsb. */
    public function bookings(Request $request): RedirectResponse
    {
        [$awal, $akhir, $label] = $this->rentang($request);

        $target = Booking::whereBetween('check_in', [$awal, $akhir->copy()->endOfDay()])->get();

        $jumlah = DB::transaction(function () use ($target) {
            $kolomFoto = ['foto_ktp', 'foto_kartu', 'foto_pelunasan', 'foto_bayar_awal', 'foto_bayar_tambahan', 'foto_pembayaran_late', 'foto_kerusakan'];
            $ids       = $target->pluck('id');

            // Kumpulkan berkas kandidat sebelum barisnya lenyap.
            $kandidat = collect($kolomFoto)
                ->flatMap(fn ($k) => $target->pluck($k))
                ->filter()
                ->unique()
                ->values();

            Booking::whereIn('id', $ids)->delete();

            // Hapus berkas hanya bila tak ada booking tersisa yang memakainya
            // (grup yang melintasi batas bulan tetap butuh fotonya).
            foreach ($kandidat as $path) {
                $masihDipakai = Booking::where(function ($q) use ($path, $kolomFoto) {
                    foreach ($kolomFoto as $k) {
                        $q->orWhere($k, $path);
                    }
                })->exists();

                if (! $masihDipakai) {
                    Storage::disk('local')->delete($path);
                }
            }

            return $target->count();
        });

        return back()->with('success', "Data kamar {$label} dihapus: {$jumlah} baris booking beserta fotonya.");
    }

    /** Absensi + jadwal pada bulan tsb, berikut kedua fotonya. */
    public function absensi(Request $request): RedirectResponse
    {
        [$awal, $akhir, $label] = $this->rentang($request);

        $jadwal = Jadwal::with('absensi')
            ->whereBetween('tanggal', [$awal, $akhir->copy()->endOfDay()])
            ->get();

        $nAbsen = 0;
        DB::transaction(function () use ($jadwal, &$nAbsen) {
            foreach ($jadwal as $j) {
                if ($j->absensi) {
                    foreach (['foto', 'foto_kartu'] as $k) {
                        if ($j->absensi->{$k}) {
                            Storage::disk('local')->delete($j->absensi->{$k});
                        }
                    }
                    $j->absensi->delete();
                    $nAbsen++;
                }
                $j->delete();
            }
        });

        return back()->with('success', "Data absensi {$label} dihapus: {$jadwal->count()} jadwal, {$nAbsen} absensi beserta fotonya.");
    }

    /** Tiket laporan yang DIBUAT pada bulan tsb, berikut lampirannya. */
    public function reports(Request $request): RedirectResponse
    {
        [$awal, $akhir, $label] = $this->rentang($request);

        $target = Report::whereBetween('created_at', [$awal, $akhir->endOfDay()])->get();

        DB::transaction(function () use ($target) {
            foreach ($target as $r) {
                if ($r->foto) {
                    Storage::disk('local')->delete($r->foto);
                }
                // Booking yang menaut tiket ini dilepas tautannya, bukan ikut terhapus.
                Booking::where('report_id', $r->id)->update(['report_id' => null]);
                $r->delete();
            }
        });

        return back()->with('success', "Laporan {$label} dihapus: {$target->count()} tiket beserta lampirannya.");
    }

    /** Invoice yang diterbitkan pada bulan tsb (rincian ikut lewat cascade). */
    public function invoices(Request $request): RedirectResponse
    {
        [$awal, $akhir, $label] = $this->rentang($request);

        $jumlah = 0;
        DB::transaction(function () use ($awal, $akhir, &$jumlah) {
            $target = Invoice::whereBetween('created_at', [$awal, $akhir->endOfDay()])->get();
            $jumlah = $target->count();
            foreach ($target as $inv) {
                $inv->delete();
            }
        });

        return back()->with('success', "Invoice {$label} dihapus: {$jumlah} dokumen.");
    }

    /** Log perubahan stok pada bulan tsb. */
    public function stockLogs(Request $request): RedirectResponse
    {
        [$awal, $akhir, $label] = $this->rentang($request);

        $jumlah = StockLog::whereBetween('created_at', [$awal, $akhir->endOfDay()])->delete();

        return back()->with('success', "Log stok {$label} dihapus: {$jumlah} baris.");
    }

    // ------------------------------------------------------------------

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string} [awal, akhir, label]
     */
    private function rentang(Request $request): array
    {
        $bulan = (string) $request->input('bulan', '');

        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan), 422, 'Format bulan tidak valid.');

        $awal = Carbon::createFromFormat('Y-m-d', $bulan . '-01')->startOfDay();

        return [$awal, $awal->copy()->endOfMonth(), $awal->translatedFormat('F Y')];
    }
}
