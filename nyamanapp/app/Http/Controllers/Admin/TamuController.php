<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Riwayat tamu — profil tamu berulang, dikenali dari NAMA.
 *
 * ── Kenapa nama, bukan nomor telepon ──
 * Nomor telepon terlihat seperti kunci yang lebih rapi, tapi di meja depan ia
 * bukan milik orangnya: satu rombongan dipesan dengan satu nomor panitia, satu
 * tamu memberi nomor istrinya pada kunjungan berikutnya, dan nomor yang tidak
 * ditanya diisi seadanya. Hasilnya profil yang pecah — tamu yang sama muncul
 * sebagai tiga orang berbeda — sekaligus profil yang menempel: tiga orang
 * berbeda tergabung menjadi satu karena memakai nomor kantor yang sama.
 *
 * Nama memang berubah ejaannya, jadi ia dinormalkan lebih dulu: huruf kecil
 * semua, spasi berlebih dirapikan, gelar dan sapaan di depan dibuang. Yang
 * DITAMPILKAN tetap ejaan pada kunjungan terakhir, karena itu yang paling
 * mungkin dikenali saat tamunya datang lagi.
 *
 * Satu "kunjungan" = satu grup pesanan (beberapa kamar dalam satu pesanan
 * tetap dihitung sekali), supaya tamu rombongan tidak tampak seperti pelanggan
 * yang datang lima kali.
 */
class TamuController extends Controller
{
    /**
     * Sapaan dan gelar yang dibuang saat menormalkan nama.
     *
     * Hanya yang berada DI DEPAN nama, dan hanya bila masih ada sisa nama
     * setelahnya — supaya tamu yang benar-benar bernama "Haji" tidak menguap.
     */
    private const SAPAAN = [
        'bapak', 'bpk', 'pak', 'ibu', 'bu', 'mas', 'mba', 'mbak',
        'sdr', 'sdri', 'tn', 'ny', 'nn', 'haji', 'hj', 'dr', 'drs', 'ir',
    ];

    public function index(Request $request)
    {
        $cari = trim((string) $request->query('cari', ''));

        $profil = $this->kunjungan($cari)
            ->groupBy(fn ($k) => $k->kunci)
            ->map(function (Collection $stays) {
                $urut = $stays->sortByDesc('check_in')->values();

                // Nomor telepon tetap ditampilkan — sekadar cara menghubungi,
                // bukan lagi penentu identitas. Yang dipakai nomor terakhir.
                return (object) [
                    'kunci'     => $urut->first()->kunci,
                    'nama'      => $urut->first()->nama,
                    'telepon'   => $urut->first()->telepon,
                    'kunjungan' => $urut->count(),
                    'kamar'     => (int) $urut->sum('kamar'),
                    'malam'     => (int) $urut->sum('malam'),
                    'terakhir'  => $urut->first()->check_in,
                ];
            })
            ->sortByDesc(fn ($p) => [$p->kunjungan, $p->terakhir])
            ->values();

        return view('admin.tamu.index', compact('profil', 'cari'));
    }

    /**
     * Detail satu tamu: seluruh kunjungannya, terbaru dulu.
     */
    public function show(Request $request)
    {
        $nama = trim((string) $request->query('nama', ''));
        abort_if($nama === '', 404);

        $kunci = self::normalkan($nama);
        abort_if($kunci === '', 404);

        // Penyaringan akhir dilakukan di PHP: normalisasinya membuang sapaan
        // dan merapikan spasi, yang tidak bisa ditulis sebagai satu ekspresi
        // SQL yang benar di MySQL maupun SQLite sekaligus.
        //
        // LIKE di bawah hanya mempersempit baris yang diangkut, dan ia memakai
        // SATU KATA TERPANJANG dari kunci — bukan potongan awalnya. Potongan
        // awal ikut membawa spasi hasil normalisasi, sehingga nama yang aslinya
        // ditulis "Budi-Santoso" atau "Budi.Santoso" tidak akan cocok dengan
        // "budi santoso" dan tamunya menghilang dari pencarian.
        $kata  = explode(' ', $kunci);
        usort($kata, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $kasar = $kata[0] ?? $kunci;

        $bookings = Booking::with(['room', 'creator'])
            ->where('nama_tamu', 'like', '%' . $kasar . '%')
            ->get()
            ->filter(fn (Booking $b) => self::normalkan((string) $b->nama_tamu) === $kunci)
            ->values();

        abort_if($bookings->isEmpty(), 404);

        // Kelompokkan per kunjungan (grup pesanan), kamar terurut.
        $stays = $bookings
            ->groupBy(fn (Booking $b) => $b->grup ?? ('single-' . $b->id))
            ->map(fn ($grup) => $grup
                ->sortBy(fn ($b) => (int) preg_replace('/\D/', '', (string) $b->room?->nomor))
                ->values())
            ->sortByDesc(fn ($grup) => $grup->first()->check_in)
            ->values();

        $terbaru = $stays->first()->first();

        return view('admin.tamu.show', [
            'nama'    => $terbaru->nama_tamu,
            'kunci'   => $kunci,
            // Seluruh nomor yang pernah dipakai nama ini. Ditampilkan apa
            // adanya: kalau ada tiga nomor berbeda, itu justru informasi.
            'telepon' => $bookings->pluck('telepon')->filter()->unique()->values(),
            'stays'   => $stays,
            'ringkas' => [
                'kunjungan' => $stays->count(),
                'kamar'     => $bookings->count(),
                // Malam = rentang kunjungan, dari check-in pertama sampai
                // check-out terakhir. Durasi terpanjang satu kamar tidak cukup
                // sejak tamu bisa pindah kamar per hari: 1 malam di 202 lalu
                // 1 malam di 203 tetap 2 malam menginap.
                'malam'     => (int) $stays->sum(fn ($grup) => self::rentangMalam($grup)),
            ],
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * Lama satu kunjungan dalam malam: check-in pertama sampai check-out
     * terakhir di antara kamar-kamarnya.
     */
    private static function rentangMalam(Collection $grup): int
    {
        $awal  = $grup->min(fn ($b) => $b->check_in?->copy()->startOfDay());
        $akhir = $grup->max(fn ($b) => $b->check_out?->copy()->startOfDay());

        return ($awal && $akhir) ? max(1, (int) $awal->diffInDays($akhir)) : (int) $grup->max('durasi');
    }

    /**
     * Kunci penyamaan nama: huruf kecil, tanpa gelar/sapaan di depan,
     * tanpa tanda baca, spasi tunggal.
     *
     *   "Bpk. Rizky  Pratama" -> "rizky pratama"
     *   "RIZKY PRATAMA"       -> "rizky pratama"
     */
    public static function normalkan(string $nama): string
    {
        $n = mb_strtolower(trim($nama));
        $n = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $n);
        $n = trim((string) preg_replace('/\s+/', ' ', (string) $n));

        if ($n === '') {
            return '';
        }

        $bagian = explode(' ', $n);

        // Buang sapaan berlapis ("bapak haji ..."), tapi sisakan minimal satu
        // kata — nama yang habis dibuang justru menggabungkan orang-orang yang
        // tidak berhubungan menjadi satu profil kosong.
        while (count($bagian) > 1 && in_array($bagian[0], self::SAPAAN, true)) {
            array_shift($bagian);
        }

        return implode(' ', $bagian);
    }

    /**
     * Satu baris per kunjungan: (nama, grup) -> jumlah kamar, malam, tanggal.
     *
     * Digabungkan di PHP, bukan di SQL.
     *
     * Versi sebelumnya memakai COALESCE(grup, 'single-' || id) langsung di
     * query. Operator || adalah penyambung teks di SQLite tetapi OR LOGIKA di
     * MySQL — di produksi ekspresi itu bernilai 1 untuk setiap baris, sehingga
     * SEMUA booking tanpa grup menyatu menjadi satu "kunjungan" raksasa. Bug
     * yang tidak pernah terlihat selama dikembangkan di SQLite.
     */
    private function kunjungan(string $cari): Collection
    {
        return Booking::query()
            ->when($cari !== '', fn ($q) => $q->where(function ($w) use ($cari) {
                $w->where('nama_tamu', 'like', '%' . $cari . '%')
                  ->orWhere('telepon', 'like', '%' . $cari . '%');
            }))
            ->orderByDesc('check_in')
            ->limit(3000)
            ->get(['id', 'grup', 'nama_tamu', 'telepon', 'durasi', 'check_in', 'check_out'])
            ->groupBy(fn (Booking $b) => $b->grup ?? ('single-' . $b->id))
            ->map(function (Collection $grup) {
                $utama = $grup->sortByDesc('check_in')->first();

                return (object) [
                    'kunci'    => self::normalkan((string) $utama->nama_tamu),
                    'nama'     => (string) $utama->nama_tamu,
                    'telepon'  => (string) $utama->telepon,
                    'kamar'    => $grup->count(),
                    'malam'    => self::rentangMalam($grup),
                    'check_in' => $utama->check_in->toDateString(),
                ];
            })
            ->filter(fn ($k) => $k->kunci !== '')
            ->values();
    }
}
