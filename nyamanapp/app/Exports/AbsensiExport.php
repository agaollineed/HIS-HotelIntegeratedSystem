<?php

namespace App\Exports;

use App\Models\Jadwal;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export kehadiran satu bulan.
 *
 * Foto tidak ikut (berkas gambar tidak bisa dibawa ke dalam sel), tetapi
 * kolom keterangannya menunjukkan ada tidaknya masing-masing bukti —
 * termasuk foto kartu, sehingga shift yang melewatkan hitungan kunci
 * langsung terlihat saat disaring di Excel.
 */
class AbsensiExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private Carbon $bulan)
    {
    }

    public function collection()
    {
        return Jadwal::with(['karyawan', 'absensi'])
            ->whereBetween('tanggal', [
                $this->bulan->copy()->startOfMonth()->toDateString(),
                $this->bulan->copy()->endOfMonth()->toDateString(),
            ])
            ->get()
            ->sortBy([
                fn ($a, $b) => $a->tanggal <=> $b->tanggal,
                fn ($a, $b) => $a->jam_mulai <=> $b->jam_mulai,
            ])
            ->values();
    }

    public function headings(): array
    {
        return ['Tanggal', 'Karyawan', 'Shift', 'Status', 'Jam Absen', 'Telat (menit)', 'Foto Selfie', 'Foto Kartu'];
    }

    public function map($j): array
    {
        $a = $j->absensi;

        return [
            $j->tanggal->format('d/m/Y'),
            $j->karyawan?->nama ?? '—',
            $j->labelJam(),
            $a === null ? 'Belum absen' : ($a->telat() ? 'Telat' : 'Hadir'),
            $a?->waktu_absen?->format('H:i') ?? '—',
            $a?->menit_telat ?? 0,
            $a && $a->foto ? 'Ada' : 'Tidak ada',
            $a && $a->foto_kartu ? 'Ada' : 'Tidak ada',
        ];
    }
}
