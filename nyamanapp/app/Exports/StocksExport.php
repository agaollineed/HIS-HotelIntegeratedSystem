<?php

namespace App\Exports;

use App\Models\Stock;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StocksExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    /**
     * @param  string  $q  Kata kunci pencarian (opsional) supaya
     *                     hasil export mengikuti filter di tabel.
     */
    public function __construct(private string $q = '')
    {
    }

    public function collection()
    {
        return Stock::query()
            ->when($this->q !== '', fn ($query) => $query->where('nama', 'like', '%' . $this->q . '%'))
            ->orderBy('nama')
            ->get();
    }

    public function headings(): array
    {
        return ['Nama Stok', 'Jumlah', 'Harga', 'Last Update', 'Diubah Oleh'];
    }

    public function map($stock): array
    {
        return [
            $stock->nama,
            $stock->jumlah,
            $stock->harga,
            optional($stock->updated_at)->translatedFormat('d M Y H:i'),
            $stock->updated_by,
        ];
    }
}
