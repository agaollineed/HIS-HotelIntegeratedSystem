<?php

namespace App\Exports;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export data hunian satu bulan, satu baris per kamar.
 *
 * Sengaja memuat seluruh jejak waktu dan pembayaran — bukan sekadar daftar
 * tamu — supaya pemilik bisa merekonsiliasi sendiri di Excel: mencari kamar
 * yang check-out-nya lebih awal, yang telat, atau yang tidak lunas.
 */
class BookingsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private Carbon $bulan)
    {
    }

    public function collection()
    {
        $awal  = $this->bulan->copy()->startOfMonth();
        $akhir = $this->bulan->copy()->endOfMonth();

        $bookings = Booking::with(['room', 'creator', 'checkoutBy'])
            ->whereDate('check_in', '<=', $akhir->toDateString())
            ->whereDate('check_out', '>', $awal->toDateString())
            ->get();

        // Diskon dicatat per PESANAN, bukan per kamar. Kalau nominalnya ditulis
        // di setiap baris kamar, siapa pun yang menjumlahkan kolomnya di Excel
        // akan mendapat angka berlipat. Karena itu nilainya hanya ditulis
        // sekali, pada baris kamar pertama tiap pesanan.
        foreach ($bookings->groupBy(fn (Booking $b) => $b->grup ?? ('single-' . $b->id)) as $anggota) {
            $total = Booking::totalPesananDari($anggota);
            $utama = $anggota->sortBy('id')->first();

            foreach ($anggota as $b) {
                $b->grup_total   = $total;
                $b->grup_tagihan = $b->tagihanPesanan($total);
                $b->grup_utama   = $b->id === $utama->id;
            }
        }

        return $bookings
            ->sortBy([
                fn ($a, $b) => $a->check_in <=> $b->check_in,
                fn ($a, $b) => (int) preg_replace('/\D/', '', (string) $a->room?->nomor)
                    <=> (int) preg_replace('/\D/', '', (string) $b->room?->nomor),
            ])
            ->values();
    }

    public function headings(): array
    {
        return [
            'Kamar', 'Tipe', 'Nama Tamu', 'Telepon', 'Asal Pesanan', 'Status Bayar',
            'Total Kamar', 'Diskon', 'Diskon %', 'Tagihan Pesanan',
            'Breakfast', 'Check-in', 'Check-out', 'Malam',
            'Waktu Check-in', 'Early Check-in', 'Waktu Check-out', 'Status Check-out',
            'Selisih', 'Kondisi Kamar', 'Tiket', 'Catatan', 'Diinput Oleh', 'Check-out Oleh',
        ];
    }

    public function map($b): array
    {
        return [
            $b->room?->nomor ?? '—',
            $b->room?->labelTipe() ?? '—',
            $b->nama_tamu,
            $b->telepon,
            $b->labelSumber(),
            $b->labelBayar(),
            // Tiga kolom uang di bawah ini milik PESANAN, jadi hanya baris
            // kamar pertama yang mengisinya — supaya kolomnya aman dijumlah.
            $b->grup_utama ? $b->grup_total : '—',
            $b->grup_utama ? $b->diskonPesanan() : '—',
            $b->grup_utama && $b->diskonPersen() > 0 ? $b->diskonPersen() . '%' : '—',
            $b->grup_utama ? $b->grup_tagihan : '—',
            $b->breakfast ? 'Ya' : 'Tidak',
            $b->check_in->format('d/m/Y'),
            $b->check_out->format('d/m/Y'),
            $b->durasi,
            $b->checked_in_at?->format('d/m/Y H:i') ?? '—',
            $b->earlyCheckin() ? Booking::labelDurasi($b->checkin_menit_awal) : '—',
            $b->checked_out_at?->format('d/m/Y H:i') ?? '—',
            $b->isCheckedOut() ? ucfirst((string) $b->checkout_status) : 'Belum check-out',
            $b->checkout_menit > 0 ? Booking::labelDurasi($b->checkout_menit) : '—',
            $b->isCheckedOut() ? $b->labelKondisi() : '—',
            $b->report_id ? '#' . $b->report_id : '—',
            $b->checkout_catatan ?? '—',
            $b->creator?->name ?? '—',
            $b->checkoutBy?->name ?? '—',
        ];
    }
}
