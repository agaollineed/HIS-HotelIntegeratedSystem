<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Invoice / tagihan tamu — dibuat staff, dicetak untuk diserahkan.
 *
 * Satu invoice bisa memuat beberapa baris kamar (tipe berbeda sekalipun)
 * plus baris biaya tambahan seperti late check-out dan early check-in.
 */
class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $cari = trim((string) $request->query('cari', ''));

        $invoices = Invoice::with(['creator', 'items'])
            ->when($cari !== '', fn ($q) => $q->where(function ($w) use ($cari) {
                $w->where('nama_tamu', 'like', '%' . $cari . '%')
                  ->orWhere('nomor', 'like', '%' . $cari . '%');
            }))
            ->latest()
            ->limit(200)
            ->get();

        return view('staff.invoices.index', compact('invoices', 'cari'));
    }

    public function create(Request $request)
    {
        $booking = $request->filled('booking')
            ? Booking::with('room')->find((int) $request->query('booking'))
            : null;

        return view('staff.invoices.create', [
            'nomor' => Invoice::nomorBerikutnya(),
            'awal'  => $booking ? $this->dariBooking($booking) : $this->kosong(),
            'tarif' => Room::HARGA,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_tamu'      => 'required|string|max:255',
            'tanggal_masuk'  => 'nullable|date',
            'tanggal_keluar' => 'nullable|date',
            'lunas'          => 'required|in:0,1',
            'catatan'        => 'nullable|string|max:1000',
            'booking_id'     => 'nullable|integer',

            // Minimal satu baris kamar.
            'kamar'                    => 'required|array|min:1',
            'kamar.*.uraian'           => 'required|string|max:60',
            'kamar.*.keterangan'       => 'nullable|string|max:60',
            'kamar.*.jumlah_kamar'     => 'required|integer|min:1|max:99',
            'kamar.*.malam'            => 'required|integer|min:1|max:365',
            'kamar.*.harga'            => 'required|integer|min:0|max:100000000',

            // Biaya tambahan bersifat opsional.
            'biaya'              => 'nullable|array',
            'biaya.*.uraian'     => 'nullable|string|max:80',
            'biaya.*.keterangan' => 'nullable|string|max:60',
            'biaya.*.harga'      => 'nullable|integer|min:0|max:100000000',
        ], [
            'nama_tamu.required'          => 'Nama tamu wajib diisi.',
            'kamar.required'              => 'Isi minimal satu baris kamar.',
            'kamar.min'                   => 'Isi minimal satu baris kamar.',
            'kamar.*.uraian.required'     => 'Tipe kamar wajib diisi.',
            'kamar.*.jumlah_kamar.required' => 'Jumlah kamar wajib diisi.',
            'kamar.*.jumlah_kamar.min'    => 'Jumlah kamar minimal 1.',
            'kamar.*.malam.required'      => 'Jumlah malam wajib diisi.',
            'kamar.*.malam.min'           => 'Jumlah malam minimal 1.',
            'kamar.*.harga.required'      => 'Harga per malam wajib diisi.',
        ]);

        $baris = $this->susunBaris($data);
        $total = array_sum(array_column($baris, 'subtotal'));

        // Baris pertama disalin ke kolom ringkasan supaya daftar dan export
        // tetap bisa menampilkan invoice tanpa memuat seluruh rinciannya.
        $utama = $baris[0];

        $invoice = DB::transaction(function () use ($data, $baris, $total, $utama) {
            $invoice = Invoice::create([
                'nomor'           => Invoice::nomorBerikutnya(),
                'booking_id'      => $data['booking_id'] ?? null,
                'nama_tamu'       => $data['nama_tamu'],
                'tipe_kamar'      => $utama['uraian'],
                'nomor_kamar'     => $utama['keterangan'],
                'malam'           => $utama['malam'],
                'harga_per_malam' => $utama['harga'],
                'total'           => $total,
                'tanggal_masuk'   => $data['tanggal_masuk'] ?? null,
                'tanggal_keluar'  => $data['tanggal_keluar'] ?? null,
                // Keterangan sarapan/early kini terbaca dari BARIS TAGIHANNYA
                // sendiri (harga kamar & baris biaya) — chip terpisah dihapus.
                'breakfast'       => false,
                'early_checkin'   => false,
                'lunas'           => (bool) $data['lunas'],
                'catatan'         => $data['catatan'] ?? null,
                'created_by'      => Auth::id(),
            ]);

            foreach ($baris as $i => $b) {
                $invoice->items()->create($b + ['urutan' => $i]);
            }

            return $invoice;
        });

        return redirect()
            ->to(\App\Support\Panel::rute('invoices.show', $invoice))
            ->with('success', 'Invoice ' . $invoice->nomor . ' dibuat — tekan Cetak untuk mencetak.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('items');

        return view('invoices.print', compact('invoice'));
    }

    // ------------------------------------------------------------------

    /**
     * Ubah masukan formulir menjadi baris siap simpan.
     *
     * Semua subtotal DIHITUNG DI SINI, tidak diambil dari kiriman peramban —
     * angka yang tercetak pada dokumen resmi tidak boleh bisa disetel dari
     * sisi klien.
     */
    private function susunBaris(array $data): array
    {
        $baris = [];

        foreach ($data['kamar'] as $k) {
            $jumlah = max(1, (int) $k['jumlah_kamar']);
            $malam  = max(1, (int) $k['malam']);
            $harga  = max(0, (int) $k['harga']);

            $baris[] = [
                'jenis'        => InvoiceItem::KAMAR,
                'uraian'       => trim($k['uraian']),
                'keterangan'   => trim((string) ($k['keterangan'] ?? '')) ?: null,
                'jumlah_kamar' => $jumlah,
                'malam'        => $malam,
                'harga'        => $harga,
                'subtotal'     => $jumlah * $malam * $harga,
            ];
        }

        foreach ($data['biaya'] ?? [] as $b) {
            $uraian = trim((string) ($b['uraian'] ?? ''));
            $harga  = max(0, (int) ($b['harga'] ?? 0));

            // Baris kosong yang ditinggalkan staff diabaikan diam-diam.
            if ($uraian === '' || $harga === 0) {
                continue;
            }

            $baris[] = [
                'jenis'        => InvoiceItem::BIAYA,
                'uraian'       => $uraian,
                'keterangan'   => trim((string) ($b['keterangan'] ?? '')) ?: null,
                'jumlah_kamar' => 1,
                'malam'        => 1,
                'harga'        => $harga,
                'subtotal'     => $harga,
            ];
        }

        return $baris;
    }

    /**
     * Isian awal dari sebuah booking: satu baris per tipe kamar, plus baris
     * biaya yang sudah pasti muncul (early check-in / late check-out) dengan
     * harga kosong agar staff mengisinya sesuai kesepakatan.
     */
    private function dariBooking(Booking $booking): array
    {
        $anggota = $booking->groupMembers()->with('room')->get();

        $kamar = $anggota
            ->filter(fn ($b) => $b->room !== null)
            ->groupBy(fn ($b) => $b->room->tipe)
            ->map(function ($grup) use ($booking) {
                $contoh = $grup->first();

                return [
                    'uraian'       => 'Kamar ' . $contoh->room->labelTipe()
                        . ($booking->breakfast ? '' : ' (tanpa breakfast)'),
                    'keterangan'   => $grup
                        ->sortBy(fn ($b) => (int) preg_replace('/\D/', '', (string) $b->room->nomor))
                        ->map(fn ($b) => $b->room->nomor)
                        ->implode(', '),
                    'jumlah_kamar' => $grup->count(),
                    'malam'        => max(1, (int) $booking->durasi),
                    // Tarif BERLAKU: 300rb/450rb dengan sarapan, 250rb tanpa
                    // sarapan (King & Twin; family selalu dengan sarapan).
                    'harga'        => $contoh->hargaMalam(),
                ];
            })
            ->values()
            ->all();

        if ($kamar === []) {
            $kamar = $this->kosong()['kamar'];
        }

        $biaya = [];

        // Early check-in (sebelum 12:30): 1 malam penuh per kamar yang masuk awal.
        $biayaEarly = $anggota->sum(fn ($b) => $b->biayaEarlyCheckin());
        if ($biayaEarly > 0) {
            $biaya[] = [
                'uraian'     => 'Biaya early check-in (sebelum ' . Booking::JAM_EARLY_BERBAYAR . ')',
                'keterangan' => Booking::labelDurasi((int) $booking->checkin_menit_awal),
                'harga'      => $biayaEarly,
            ];
        }

        // Late check-out: 50% harga malam sampai 17:00, lewat itu 100%.
        // Yang sudah tercatat memakai waktu check-out; yang masih berjalan
        // memakai waktu SEKARANG sebagai pratinjau tagihan.
        $biayaLate = $anggota->sum(fn ($b) => $b->isCheckedOut()
            ? $b->biayaLateCheckout()
            : ($b->lateBerjalan() ? $b->biayaLateCheckout(now()) : 0));

        $menitTelat = $booking->checkoutTelat()
            ? (int) $booking->checkout_menit
            : ($booking->lateBerjalan() ? (int) $booking->batasCheckout()->diffInMinutes(now()) : 0);

        // Lewat 17.00 tagihannya naik jadi 1 malam penuh, jadi uraiannya pun
        // menyebut "over late" supaya nominal di invoice bisa dijelaskan.
        $overLate = $booking->isCheckedOut()
            ? $booking->overLateCheckout()
            : ($booking->lateBerjalan() && $booking->overLatePada(now()));

        if ($biayaLate > 0) {
            $biaya[] = [
                'uraian'     => $overLate
                    ? 'Biaya over late check-out (lewat ' . Booking::JAM_LATE_SETENGAH . ')'
                    : 'Biaya late check-out',
                'keterangan' => Booking::labelDurasi($menitTelat),
                'harga'      => $biayaLate,
            ];
        }

        return [
            'booking_id'     => $booking->id,
            'nama_tamu'      => $booking->nama_tamu,
            'kamar'          => $kamar,
            'biaya'          => $biaya,
            'tanggal_masuk'  => $booking->check_in?->toDateString(),
            'tanggal_keluar' => $booking->check_out?->toDateString(),
            'breakfast'      => (bool) $booking->breakfast,
            'early_checkin'  => $booking->earlyCheckin(),
            'lunas'          => $booking->lunas(),
            // Diskon bukan baris tagihan melainkan potongan yang sudah
            // diperhitungkan, jadi ia tidak boleh ikut menambah total. Ia
            // dituliskan sebagai keterangan supaya tamu bisa mencocokkan
            // sendiri, dan staff tidak perlu mengetiknya ulang dari ingatan.
            'catatan'        => $booking->diskonPesanan() > 0
                ? $booking->labelDiskon() . ' sudah diperhitungkan dari total kamar '
                  . Booking::rupiah($booking->totalPesanan()) . '.'
                : null,
        ];
    }

    private function kosong(): array
    {
        return [
            'booking_id'     => null,
            'nama_tamu'      => '',
            'kamar'          => [[
                'uraian'       => 'Kamar King',
                'keterangan'   => '',
                'jumlah_kamar' => 1,
                'malam'        => 1,
                'harga'        => Room::HARGA['king'],
            ]],
            'biaya'          => [],
            'tanggal_masuk'  => Carbon::now()->toDateString(),
            'tanggal_keluar' => Carbon::now()->addDay()->toDateString(),
            'breakfast'      => false,
            'early_checkin'  => false,
            'lunas'          => false,
            'catatan'        => null,
        ];
    }
}
