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

        // Siapa menempati kamar mana pada masa menginap booking ini, di luar
        // baris ini sendiri (kamar lain dalam pesanan yang sama tetap dihitung
        // terisi). Daftar ini dipakai untuk pindah SELURUH masa menginap:
        // satu penghuni berarti tukar kamar saat disetujui. Untuk pindah
        // mulai malam tertentu, formulir mengambil ulang ketersediaannya.
        $penghuni = Booking::query()
            ->menahanKamar()
            ->where('id', '!=', $booking->id)
            ->where('check_in', '<', $booking->check_out)
            ->where('check_out', '>', $booking->check_in)
            ->orderBy('check_in')
            ->get(['room_id', 'nama_tamu'])
            ->groupBy('room_id')
            ->map(fn ($g) => $g->pluck('nama_tamu')->values()->all());

        // Pilihan "pindah mulai malam": setiap malam dalam masa menginap.
        $tanggalPindah = [];
        for ($d = $booking->check_in->copy()->startOfDay(); $d->lt($booking->check_out); $d->addDay()) {
            $tanggalPindah[] = $d->copy();
        }

        return view('staff.rooms.perubahan', [
            'booking'       => $booking,
            // Pindah kamar tidak lagi diajukan: staff mengerjakannya langsung
            // lewat halaman Pindah Kamar. Jenis lain tetap diputus admin.
            'jenisList'     => self::jenisPengajuan(),
            'roomsByTipe'   => Room::orderBy('nomor')->get()->groupBy('tipe'),
            'penghuni'      => $penghuni,
            'tanggalPindah' => $tanggalPindah,
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
            'jenis'      => 'required|in:' . implode(',', array_keys(self::jenisPengajuan())),
            'alasan'     => 'required|string|min:5|max:500',
            'room_id'    => 'nullable|exists:rooms,id',
            'pindah_mulai' => 'nullable|date_format:Y-m-d',
            'nama_tamu'  => 'nullable|string|max:120',
            'telepon'    => 'nullable|string|max:30',
            'check_in'   => 'nullable|date|after_or_equal:' . now()->subDays(7)->toDateString(),
            'durasi'     => 'nullable|integer|min:1|max:30',
        ], [
            'jenis.required'  => 'Pilih dulu jenis perubahannya.',
            'jenis.in'        => 'Pindah kamar kini dikerjakan langsung lewat tombol Pindah Kamar, tanpa pengajuan.',
            'alasan.required' => 'Tuliskan alasan perubahan — ini yang dibaca admin saat memutuskan.',
            'alasan.min'      => 'Alasan terlalu singkat. Jelaskan sedikit lebih lengkap.',
            'durasi.min'      => 'Lama menginap minimal 1 malam.',
            'durasi.max'      => 'Lama menginap maksimal 30 malam.',
        ]);

        // Hanya kolom milik jenis terpilih yang diisi; sisanya null. Ini yang
        // membuat satu permintaan benar-benar berisi satu perubahan.
        $usulan = match ($data['jenis']) {
            BookingRequest::PINDAH_KAMAR => [
                'room_id_baru' => $data['room_id'] ?? null,
                // Kosong atau sama dengan check-in = seluruh masa menginap.
                'pindah_mulai' => isset($data['pindah_mulai']) && $data['pindah_mulai'] > $booking->check_in->toDateString()
                    ? $data['pindah_mulai']
                    : null,
            ],
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
                ($usulan['pindah_mulai'] ?? null) !== null
                    && $usulan['pindah_mulai'] >= $booking->check_out->toDateString()
                    => ['pindah_mulai' => 'Tanggal mulai pindah harus sebelum tanggal check-out.'],
                default => null,
            },
            BookingRequest::PERPANJANG, BookingRequest::UBAH_TANGGAL
                => $this->galatPesananDipecah($booking) ?? $this->galatTanggal($jenis, $usulan, $booking),
            default => $this->galatTanggal($jenis, $usulan, $booking),
        };
    }

    /**
     * Pesanan yang sudah dipecah karena pindah per hari tidak bisa diubah
     * tanggalnya sebagai satu kesatuan — sambungan antar kamarnya putus.
     *
     * @return array<string, string>|null
     */
    private function galatPesananDipecah(Booking $booking): ?array
    {
        $dipecah = $booking->groupMembers()->with('pindahKe')->get()
            ->contains(fn (Booking $m) => $m->isPindahan() || $m->pindahKe !== null);

        return $dipecah
            ? ['jenis' => 'Pesanan ini sudah dipecah karena pindah kamar per hari, jadi tanggalnya diubah admin '
                . 'per kamar. Sampaikan permintaan tamu ke admin.']
            : null;
    }

    /** @return array<string, string>|null */
    private function galatTanggal(string $jenis, array $usulan, Booking $booking): ?array
    {
        return match ($jenis) {
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

    /**
     * Jenis perubahan yang masih lewat persetujuan admin.
     *
     * @return array<string, string>
     */
    private static function jenisPengajuan(): array
    {
        return array_diff_key(BookingRequest::JENIS, [BookingRequest::PINDAH_KAMAR => true]);
    }

    /** Pengajuan yang masih menunggu untuk booking ini, bila ada. */
    private function permintaanTertunda(Booking $booking): ?BookingRequest
    {
        return $booking->permintaan()->menunggu()->latest()->first();
    }
}
