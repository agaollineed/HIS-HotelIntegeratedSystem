<?php

namespace App\Exports;

use App\Models\StockLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockLogsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    /**
     * @param  string|null  $from  Tanggal awal (Y-m-d), opsional.
     * @param  string|null  $to    Tanggal akhir (Y-m-d), opsional.
     */
    public function __construct(private ?string $from = null, private ?string $to = null)
    {
    }

    public function collection()
    {
        return StockLog::query()
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->latest()
            ->get();
    }

    public function headings(): array
    {
        return ['Waktu', 'Item', 'Aksi', 'Detail Perubahan', 'Selisih', 'Oleh', 'Role'];
    }

    public function map($log): array
    {
        $labels = [
            'create'    => 'Item Baru',
            'update'    => 'Ubah',
            'increment' => 'Tambah',
            'decrement' => 'Kurang',
            'delete'    => 'Hapus',
        ];

        return [
            optional($log->created_at)->translatedFormat('d M Y H:i'),
            $log->nama,
            $labels[$log->action] ?? $log->action,
            $log->keterangan,
            is_null($log->selisih) ? '' : $log->selisih,
            $log->by_name,
            $log->by_role,
        ];
    }
}
