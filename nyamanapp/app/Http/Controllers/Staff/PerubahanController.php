<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Pengajuan perubahan booking oleh staff.
 *
 * Staff tidak mengubah booking secara langsung. Kamar, tanggal, dan nama tamu
 * adalah jejak yang dipakai untuk mengawasi — kalau bisa disunting sendiri
 * oleh yang menjualnya, jejak itu berhenti membuktikan apa pun. Karena itu
 * staff mengajukan, admin memutuskan.
 *
 * SATU permintaan membawa SATU jenis perubahan. Admin dengan demikian selalu
 * menyetujui satu hal yang jelas, bukan sekumpulan perubahan yang harus
 * diterima atau ditolak sekaligus.
 */
class PerubahanController extends Controller
{
    /** Formulir pengajuan untuk satu baris booking. */
    public function create(Booking $booking): View|RedirectResponse
    {
        if ($menunggu = $this->permintaanTertunda($booking)) {
            return redirect()
                ->route('staff.rooms.index', ['tanggal' => $booking->check_in->toDateString()])
                ->with('error', 'Kamar ' . ($booking->room?->nomor ?? '—')
                    . ' sudah punya pengajuan "' . $menunggu->labelJenis()
                    . '" yang belum diputus admin. Tunggu keputusannya dulu.');
        }

        if ($booking->isCheckedOut()) {
            return redirect()
                ->route('staff.rooms.index', ['tanggal' => $booking->check_in->toDateString()])
                ->with('error', 'Booking yang sudah check-out tidak bisa diubah lagi.');
        }

        return view('staff.rooms.perubahan', [
            'booking'     => $booking,
            'jenisList'   => BookingRequest::JENIS,
            'roomsByTipe' => Room::orderBy('nomor')->get()->groupBy('tipe'),
            'riwayat'     => $booking->permintaan()->with(['pengaju', 'pemutus'])->latest()->limit(5)->get(),
        ]);
    }

    /** Simpan pengajuan. Booking-nya sendiri belum berubah sama sekali. */
    public function store(Request $request, Booking $booking): RedirectResponse
    {
        if ($this->permintaanTertunda($booking)) {
            return back()->withInput()->withErrors([
                'jenis' => 'Masih ada pengajuan untuk kamar ini yang belum diputus admin.',
            ]);
        }

        $data = $request->validate([
            'jenis'      => 'required|in:' . implode(',', array_keys(BookingRequest::JENIS)),
            'alasan'     => 'required|string|min:5|max:500',
            'room_id'    => 'nullable|exists:rooms,id',
            'nama_tamu'  => 'nullable|string|max:120',
            'telepon'    => 'nullable|string|max:30',
            'check_in'   => 'nullable|date|after_or_equal:' . now()->subDays(7)->toDateString(),
            'durasi'     => 'nullable|integer|min:1|max:30',
        ], [
            'jenis.required'  => 'Pilih dulu jenis perubahannya.',
            'alasan.required' => 'Tuliskan alasan perubahan — ini yang dibaca admin saat memutuskan.',
            'alasan.min'      => 'Alasan terlalu singkat. Jelaskan sedikit lebih lengkap.',
            'durasi.min'      => 'Lama menginap minimal 1 malam.',
            'durasi.max'      => 'Lama menginap maksimal 30 malam.',
        ]);

        // Hanya kolom milik jenis terpilih yang diisi; sisanya null. Ini yang
        // membuat satu permintaan benar-benar berisi satu perubahan.
        $usulan = match ($data['jenis']) {
            BookingRequest::PINDAH_KAMAR => ['room_id_baru' => $data['room_id'] ?? null],
            BookingRequest::PERPANJANG   => ['durasi_baru' => $data['durasi'] ?? null],
            BookingRequest::UBAH_TANGGAL => ['check_in_baru' => $data['check_in'] ?? null],
            BookingRequest::UBAH_TAMU    => [
                'nama_tamu_baru' => $data['nama_tamu'] ?? null,
                'telepon_baru'   => $data['telepon'] ?? null,
            ],
            default => [],
        };

        if ($galat = $this->galatUsulan($data['jenis'], $usulan, $booking)) {
            return back()->withInput()->withErrors($galat);
        }

        BookingRequest::create($usulan + [
            'booking_id'  => $booking->id,
            'jenis'       => $data['jenis'],
            'alasan'      => $data['alasan'],
            'status'      => BookingRequest::MENUNGGU,
            'diajukan_by' => Auth::id(),
        ]);

        return redirect()
            ->route('staff.rooms.index', [
                'tanggal' => $booking->check_in->toDateString(),
                'bulan'   => $booking->check_in->format('Y-m'),
            ])
            ->with('success', 'Pengajuan "' . (BookingRequest::JENIS[$data['jenis']] ?? 'perubahan')
                . '" untuk kamar ' . ($booking->room?->nomor ?? '—')
                . ' terkirim ke admin. Booking belum berubah sampai disetujui.');
    }

    /**
     * Usulan yang tidak masuk akal ditolak sejak di meja depan, supaya admin
     * tidak dibebani memeriksa hal yang sudah jelas keliru.
     *
     * Ketersediaan kamar sengaja TIDAK diperiksa di sini secara final — ia
     * diperiksa ulang saat admin menyetujui, karena kamar bisa terisi di
     * antara pengajuan dan keputusan.
     *
     * @return array<string, string>|null
     */
    private function galatUsulan(string $jenis, array $usulan, Booking $booking): ?array
    {
        return match ($jenis) {
            BookingRequest::PINDAH_KAMAR => match (true) {
                ($usulan['room_id_baru'] ?? null) === null => ['room_id' => 'Pilih kamar tujuannya.'],
                (int) $usulan['room_id_baru'] === (int) $booking->room_id
                    => ['room_id' => 'Kamar tujuan sama dengan kamar sekarang.'],
                default => null,
            },
            BookingRequest::PERPANJANG => match (true) {
                ($usulan['durasi_baru'] ?? null) === null => ['durasi' => 'Isi lama menginap yang baru.'],
                (int) $usulan['durasi_baru'] === (int) $booking->durasi
                    => ['durasi' => 'Lama menginap sama dengan yang sekarang.'],
                default => null,
            },
            BookingRequest::UBAH_TANGGAL => ($usulan['check_in_baru'] ?? null) === null
                ? ['check_in' => 'Isi tanggal check-in yang baru.']
                : null,
            BookingRequest::UBAH_TAMU => (($usulan['nama_tamu_baru'] ?? null) === null
                && ($usulan['telepon_baru'] ?? null) === null)
                ? ['nama_tamu' => 'Isi nama atau telepon yang baru.']
                : null,
            default => null,
        };
    }

    /** Pengajuan yang masih menunggu untuk booking ini, bila ada. */
    private function permintaanTertunda(Booking $booking): ?BookingRequest
    {
        return $booking->permintaan()->menunggu()->latest()->first();
    }
}
