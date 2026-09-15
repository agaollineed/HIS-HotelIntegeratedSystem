<?php

namespace App\Exports;

use App\Models\Report;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export seluruh tiket laporan, termasuk tiket kerusakan yang dibuat
 * otomatis saat check-out.
 */
class ReportsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private string $status = '')
    {
    }

    public function collection()
    {
        return Report::with('user')
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->get();
    }

    public function headings(): array
    {
        return ['Tiket', 'Tanggal', 'Pengirim', 'Judul', 'Isi', 'Status'];
    }

    public function map($r): array
    {
        return [
            '#' . $r->id,
            $r->created_at?->format('d/m/Y H:i'),
            $r->user?->name ?? '—',
            $r->judul,
            $r->isi,
            $r->status === 'selesai' ? 'Selesai' : 'Open',
        ];
    }
}
