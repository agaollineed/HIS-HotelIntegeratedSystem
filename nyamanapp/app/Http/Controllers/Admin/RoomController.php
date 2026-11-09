<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesRoomCalendar;
use App\Exports\BookingsExport;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RoomController extends Controller
{
    use HandlesRoomCalendar;

    /**
     * Panel kamar admin (read-only), sederhana:
     * kalender -> angka Terisi/Kosong tanggal terpilih -> daftar entri
     * kamar terisi (klik utk detail lengkap: tamu, telepon, tanggal,
     * waktu check-in, kamar segrup, foto KTP, penginput).
     */
    public function index(Request $request)
    {
        [$month, $selected] = $this->monthAndSelected($request);
        [$cari, $hasilCari] = $this->hasilPencarian($request);

        // Pengajuan dari staff ditaruh di halaman ini, bukan di menu terpisah:
        // keputusannya butuh gambaran hunian hari itu, dan gambaran itu ada di
        // sini. Menu tersendiri hanya akan membuatnya terlewat.
        $permintaan = BookingRequest::menunggu()
            ->with(['booking.room', 'roomBaru', 'pengaju'])
            ->oldest()
            ->get();

        return view('admin.rooms.index', [
            'cari'          => $cari,
            'hasilCari'     => $hasilCari,
            'month'         => $month,
            'selected'      => $selected,
            'permintaan'    => $permintaan,
            'alarm'         => \App\Support\AlarmCheckout::kumpulkan(),
            'terisiPerHari' => $this->terisiPerHari($month),
            'prevMonth'     => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth'     => $month->copy()->addMonth()->format('Y-m'),
        ] + $this->dayData($selected));
    }

    /**
     * Unduh data hunian bulan yang sedang dilihat sebagai Excel.
     */
    public function export(Request $request)
    {
        [$month] = $this->monthAndSelected($request);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new BookingsExport($month),
            'hunian-' . $month->format('Y-m') . '.xlsx'
        );
    }

    /**
     * Panel detail satu tanggal (dipakai kalender via fetch).
     */
    public function day(string $tanggal)
    {
        $selected = Carbon::createFromFormat('Y-m-d', $tanggal)->startOfDay();

        return view('admin.rooms._day', ['selected' => $selected] + $this->dayData($selected));
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    /**
     * Data panel tanggal: entri kamar terisi malam itu (urut nomor kamar)
     * dgn daftar kamar segrup, plus hitungan terisi/kosong.
     */
    private function dayData(Carbon $tanggal): array
    {
        $totalKamar = Room::count();

        $menginap = $this->bookingsOccupying($tanggal);

        // Nomor kamar segrup utk detail (booking lama tanpa grup = dirinya sendiri).
        $perGrup = $menginap->groupBy(fn ($b) => $b->grup ?? ('single-' . $b->id));

        // Tagihan berlaku untuk SATU PESANAN — seluruh kamarnya, termasuk
        // kamar yang tidak menginap malam ini (kamar dengan tanggal berbeda,
        // atau kamar hasil pindah per hari). Dulu hanya kamar malam ini yang
        // dijumlahkan, sehingga tagihan di panel lebih kecil dari yang
        // sebenarnya. Dimuat sekali di sini, bukan per baris di dalam view.
        $kodeGrup = $menginap->pluck('grup')->filter()->unique()->values();
        $semuaAnggota = $kodeGrup->isEmpty()
            ? collect()
            : Booking::with('room')->whereIn('grup', $kodeGrup)->get()->groupBy('grup');

        foreach ($menginap as $b) {
            $anggota = $b->grup ? $semuaAnggota->get($b->grup, collect([$b])) : collect([$b]);

            $b->grup_rooms = $anggota
                ->sortBy(fn ($m) => $m->check_in->format('Ymd') . sprintf('%06d', (int) preg_replace('/\D/', '', (string) $m->room?->nomor)))
                ->map(fn ($m) => $m->room?->nomor)
                ->filter()
                ->unique()
                ->implode(', ');

            $b->grup_total   = Booking::totalPesananDari($anggota);
            $b->grup_tagihan = $b->tagihanPesanan($b->grup_total);
            $b->grup_semua   = $anggota;
        }

        $kamarTerisi = $menginap->pluck('room_id')->unique()->count();

        // Status kedatangan hari itu. Yang dicari admin saat membuka panel
        // adalah: dari sekian kamar terisi, berapa tamunya sudah benar-benar
        // datang — bukan sekadar berapa kamar yang terjual.
        $sudahCi = $menginap->filter(fn ($b) => $b->isCheckedIn())->count();
        $keluar  = $menginap->filter(fn ($b) => $b->isCheckedOut())->count();

        return [
            'entries'    => $menginap,
            // Satu kotak per PESANAN, bukan per kamar. Tamu yang memesan 101
            // dan 103 adalah satu tamu dengan satu tagihan; menampilkannya
            // sebagai dua kartu membuat panel terbaca seperti dua kedatangan
            // dan angka tagihannya seolah dua kali lipat.
            //
            // Urutan di dalam grup memakai nomor kamar, dan urutan antar-grup
            // memakai nomor kamar terkecilnya — supaya susunannya tetap sama
            // setiap kali panel dibuka.
            'dayGroups'  => $perGrup
                ->map(fn ($g) => $g
                    ->sortBy(fn ($b) => (int) preg_replace('/\D/', '', (string) $b->room?->nomor))
                    ->values())
                ->sortBy(fn ($g) => (int) preg_replace('/\D/', '', (string) $g->first()->room?->nomor))
                ->values(),
            'terisi'     => $kamarTerisi,
            'kosong'     => max(0, $totalKamar - $kamarTerisi),
            'totalKamar' => $totalKamar,
            'sudahCi'    => $sudahCi,
            'belumCi'    => max(0, $menginap->count() - $sudahCi),
            'sudahCo'    => $keluar,
        ];
    }
}
