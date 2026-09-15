<?php

namespace App\Http\Controllers\Admin;

use App\Exports\KeuanganExport;
use App\Http\Controllers\Controller;
use App\Models\Keuangan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Keuangan bulanan — khusus superadmin.
 *
 * Halaman ini menyandingkan dua angka yang sengaja berasal dari sumber
 * berbeda:
 *
 *   pendapatan tercatat   dihitung dari booking di aplikasi
 *   pemasukan nyata       diketik pemilik dari rekening & buku kas
 *
 * Yang dicari bukan salah satunya, melainkan SELISIHNYA. Bulan dengan
 * pendapatan tercatat Rp 40 juta tetapi setoran Rp 34 juta adalah pertanyaan
 * yang layak ditanyakan; bulan yang keduanya cocok adalah bulan yang tidak
 * perlu dipikirkan lagi.
 *
 * Karena itu angka nyata TIDAK boleh diisi otomatis dari booking. Begitu
 * diisi otomatis, selisihnya selalu nol dan halaman ini berhenti berguna.
 */
class KeuanganController extends Controller
{
    public function index(Request $request)
    {
        $tahun = $this->tahunDari($request->query('tahun'));

        $baris = Keuangan::with('pencatat')
            ->orderByDesc('periode')
            ->limit(120)
            ->get();

        // Bulan yang BELUM dicatat — supaya yang terlewat terlihat, bukan
        // hanya yang sudah diisi. Dibatasi sampai bulan berjalan: bulan yang
        // belum lewat memang belum bisa punya setoran.
        $sudah   = $baris->map(fn (Keuangan $k) => $k->kunciPeriode())->all();
        $belum   = collect();
        $jalan   = now()->startOfMonth();
        $mundur  = $jalan->copy()->subMonths(11);

        for ($d = $mundur->copy(); $d->lte($jalan); $d->addMonth()) {
            if (! in_array($d->format('Y-m'), $sudah, true)) {
                $belum->push($d->copy());
            }
        }

        return view('admin.keuangan.index', [
            'baris'   => $baris,
            'belum'   => $belum->sortByDesc(fn ($d) => $d->format('Y-m'))->values(),
            'tahun'   => $tahun,
            'bulanIni' => now()->format('Y-m'),
            'ringkas' => [
                'pemasukan'   => (int) $baris->sum('pemasukan'),
                'pengeluaran' => (int) $baris->sum('pengeluaran'),
                'laba'        => (int) $baris->sum(fn (Keuangan $k) => $k->laba()),
                'bulan'       => $baris->count(),
            ],
        ]);
    }

    /**
     * Data grafik (JSON): setoran nyata vs pendapatan tercatat, per bulan.
     */
    public function data(Request $request): JsonResponse
    {
        $baris = Keuangan::orderBy('periode')->get();

        $hasil = $baris->map(fn (Keuangan $k) => [
            'periode'   => $k->kunciPeriode(),
            'label'     => $k->periode->translatedFormat('M Y'),
            'pemasukan' => $k->pemasukan,
            'pengeluaran' => $k->pengeluaran,
            'laba'      => $k->laba(),
            'tercatat'  => $k->pendapatanTercatat(),
            'selisih'   => $k->selisih(),
        ])->values();

        return response()->json([
            'bulan'      => $hasil,
            'diperbarui' => now()->translatedFormat('H:i:s'),
        ]);
    }

    /**
     * Simpan atau perbarui satu bulan.
     *
     * updateOrCreate, bukan create: satu bulan hanya boleh punya satu baris.
     * Mengoreksi angka April adalah hal yang wajar terjadi (nota menyusul,
     * transfer terlambat masuk), dan koreksi itu tidak boleh melahirkan baris
     * kedua yang diam-diam ikut terjumlah.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Aturan ditulis sebagai ARRAY, bukan string berpipa: tanda | di
            // dalam pola regex akan dibaca sebagai pemisah aturan, dan polanya
            // terpotong menjadi regex yang tidak sah.
            'periode'     => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'pemasukan'   => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'pengeluaran' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            'catatan'     => ['nullable', 'string', 'max:1000'],
        ], [
            'periode.required'    => 'Pilih bulan yang dicatat.',
            'periode.regex'       => 'Format bulan tidak valid.',
            'pemasukan.required'  => 'Isi pemasukan nyata bulan tersebut.',
            'pemasukan.integer'   => 'Pemasukan harus berupa angka rupiah tanpa titik.',
            'pemasukan.min'       => 'Pemasukan tidak boleh negatif.',
            'pengeluaran.integer' => 'Pengeluaran harus berupa angka rupiah tanpa titik.',
            'pengeluaran.min'     => 'Pengeluaran tidak boleh negatif.',
            'catatan.max'         => 'Catatan maksimal 1000 karakter.',
        ]);

        $periode = Keuangan::periodeDari($data['periode']);

        if ($periode === null) {
            return back()->withInput()->withErrors(['periode' => 'Format bulan tidak valid.']);
        }

        // Bulan yang belum lewat belum bisa punya setoran final. Dibiarkan
        // lewat akan menghasilkan grafik yang turun tajam di ujung kanan
        // setiap bulan, lalu naik lagi — pola palsu yang membingungkan.
        if ($periode->greaterThan(now()->startOfMonth())) {
            return back()->withInput()->withErrors([
                'periode' => 'Bulan tersebut belum berjalan. Catat setelah bulannya lewat.',
            ]);
        }

        // Pencarian barisnya memakai whereDate, BUKAN pencocokan string.
        //
        // Kolomnya bertipe date tetapi Eloquent menuliskannya sebagai
        // '2026-08-01 00:00:00'. Membandingkannya dengan '2026-08-01' tidak
        // akan pernah cocok, sehingga updateOrCreate selalu menganggap
        // bulannya baru — lalu ditolak indeks unik dan koreksi angka yang
        // paling wajar terjadi (nota menyusul, transfer terlambat masuk)
        // berakhir sebagai galat. whereDate membandingkan bagian tanggalnya
        // saja dan berlaku sama di SQLite maupun MySQL.
        $isi = [
            'pemasukan'   => (int) $data['pemasukan'],
            'pengeluaran' => (int) ($data['pengeluaran'] ?? 0),
            'catatan'     => $data['catatan'] ?? null,
            'dicatat_by'  => Auth::id(),
        ];

        $ada  = Keuangan::whereDate('periode', $periode->toDateString())->first();
        $baru = $ada === null;

        if ($ada !== null) {
            $ada->update($isi);
        } else {
            Keuangan::create($isi + ['periode' => $periode]);
        }

        return redirect()->route('admin.keuangan.index')
            ->with('success', 'Keuangan ' . $periode->translatedFormat('F Y')
                . ($baru ? ' berhasil dicatat.' : ' berhasil diperbarui.'));
    }

    public function destroy(Keuangan $keuangan): RedirectResponse
    {
        $label = $keuangan->labelPeriode();
        $keuangan->delete();

        return redirect()->route('admin.keuangan.index')
            ->with('success', 'Catatan keuangan ' . $label . ' dihapus.');
    }

    public function export()
    {
        return Excel::download(
            new KeuanganExport(),
            'keuangan-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    // ------------------------------------------------------------------

    private function tahunDari(?string $nilai): int
    {
        return (is_string($nilai) && preg_match('/^\d{4}$/', $nilai))
            ? (int) $nilai
            : (int) now()->format('Y');
    }
}
