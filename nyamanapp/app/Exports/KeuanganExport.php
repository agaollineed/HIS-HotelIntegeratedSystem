<?php

namespace App\Exports;

use App\Models\Keuangan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export keuangan bulanan.
 *
 * Kolom "Pendapatan Tercatat" dan "Selisih" ikut diekspor: yang dicari
 * pemilik saat membuka berkas ini setahun kemudian bukan berapa uangnya,
 * melainkan bulan mana yang setorannya menyimpang dari catatan kamar.
 */
class KeuanganExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function collection()
    {
        return Keuangan::with('pencatat')->orderBy('periode')->get();
    }

    public function headings(): array
    {
        return [
            'Bulan', 'Pemasukan Nyata', 'Pengeluaran', 'Laba Bersih',
            'Pendapatan Tercatat', 'Selisih', 'Catatan', 'Dicatat Oleh', 'Terakhir Diubah',
        ];
    }

    public function map($k): array
    {
        return [
            $k->periode->format('Y-m'),
            $k->pemasukan,
            $k->pengeluaran,
            $k->laba(),
            $k->pendapatanTercatat(),
            $k->selisih(),
            $k->catatan ?? '—',
            $k->pencatat?->name ?? '—',
            $k->updated_at?->format('d/m/Y H:i') ?? '—',
        ];
    }
}
