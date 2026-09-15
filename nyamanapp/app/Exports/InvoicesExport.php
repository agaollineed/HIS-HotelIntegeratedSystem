<?php

namespace App\Exports;

use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export invoice satu bulan. Angka dikirim sebagai bilangan (bukan teks
 * "Rp ...") supaya bisa langsung dijumlahkan di Excel.
 */
class InvoicesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private Carbon $bulan)
    {
    }

    public function collection()
    {
        return Invoice::with(['creator', 'items'])
            ->whereBetween('created_at', [
                $this->bulan->copy()->startOfMonth(),
                $this->bulan->copy()->endOfMonth()->endOfDay(),
            ])
            ->orderBy('nomor')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Nomor', 'Tanggal', 'Nama Tamu', 'Rincian Kamar', 'Biaya Tambahan',
            'Masuk', 'Keluar', 'Malam', 'Total',
            'Sarapan', 'Early Check-in', 'Status Bayar', 'Catatan', 'Dibuat Oleh',
        ];
    }

    /** Ringkas baris rincian jadi satu sel: "Kamar King (101, 102) = Rp ...". */
    private function rincian($inv, string $jenis): string
    {
        return $inv->items
            ->where('jenis', $jenis)
            ->map(function ($i) {
                $ket = $i->keterangan ? ' (' . $i->keterangan . ')' : '';

                return $i->uraian . $ket . ' = ' . number_format($i->subtotal, 0, ',', '.');
            })
            ->implode('; ');
    }

    public function map($inv): array
    {
        return [
            $inv->nomor,
            $inv->created_at?->format('d/m/Y H:i'),
            $inv->nama_tamu,
            $this->rincian($inv, 'kamar') ?: $inv->tipe_kamar,
            $this->rincian($inv, 'biaya') ?: '—',
            $inv->tanggal_masuk?->format('d/m/Y') ?? '—',
            $inv->tanggal_keluar?->format('d/m/Y') ?? '—',
            $inv->items->where('jenis', 'kamar')->max('malam') ?? $inv->malam,
            $inv->total,
            $inv->breakfast ? 'Ya' : 'Tidak',
            $inv->early_checkin ? 'Ya' : 'Tidak',
            $inv->lunas ? 'Lunas' : 'Belum Lunas',
            $inv->catatan ?? '—',
            $inv->creator?->name ?? '—',
        ];
    }
}
