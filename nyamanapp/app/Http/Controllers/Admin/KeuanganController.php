<?php

namespace App\Http\Controllers\Admin;

use App\Exports\KeuanganExport;
use App\Http\Controllers\Controller;
use App\Models\Keuangan;
use App\Support\CsvKeuangan;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Keuntungan bulanan — admin dan superadmin (hapus hanya superadmin).
 *
 * Diisi omset dan pengeluaran per bulan; keuntungan dan bonus karyawan
 * (Keuangan::PERSEN_BONUS dari keuntungan) dihitung otomatis.
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

        // Bulan yang disorot di kartu atas: bulan berjalan bila sudah diisi,
        // kalau belum bulan terakhir yang tercatat.
        $sorot = $baris->first(fn (Keuangan $k) => $k->kunciPeriode() === now()->format('Y-m')) ?? $baris->first();

        return view('admin.keuangan.index', [
            'baris'   => $baris,
            'sorot'   => $sorot,
            'tahun'   => $tahun,
            'bulanIni' => now()->format('Y-m'),
            'ringkas' => [
                'pemasukan'   => (int) $baris->sum('pemasukan'),
                'pengeluaran' => (int) $baris->sum('pengeluaran'),
                'laba'        => (int) $baris->sum(fn (Keuangan $k) => $k->laba()),
                'bonus'       => (int) $baris->sum(fn (Keuangan $k) => $k->bonusKaryawan()),
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
            'bonus'     => $k->bonusKaryawan(),
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
            'pemasukan.required'  => 'Isi omset bulan tersebut.',
            'pemasukan.integer'   => 'Omset harus berupa angka rupiah tanpa titik.',
            'pemasukan.min'       => 'Omset tidak boleh negatif.',
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
            ->with('success', 'Keuntungan ' . $periode->translatedFormat('F Y')
                . ($baru ? ' berhasil dicatat' : ' berhasil diperbarui') . ': '
                . Keuangan::rupiah($isi['pemasukan'] - $isi['pengeluaran']) . ', bonus karyawan '
                . Keuangan::rupiah(Keuangan::bonusDari($isi['pemasukan'] - $isi['pengeluaran'])) . '.');
    }

    public function destroy(Keuangan $keuangan): RedirectResponse
    {
        abort_unless(Auth::user()->bolehHapusKeuangan(), 403);

        $label = $keuangan->labelPeriode();
        $keuangan->delete();

        return redirect()->route('admin.keuangan.index')
            ->with('success', 'Catatan keuangan ' . $label . ' dihapus.');
    }

    /** Unduh seluruh bulan sebagai CSV — sekaligus contoh format untuk impor. */
    public function exportCsv()
    {
        return CsvKeuangan::unduh(
            'keuntungan-' . now()->format('Ymd') . '.csv',
            ['bulan', 'omset', 'pengeluaran', 'keuntungan', 'bonus_karyawan', 'catatan'],
            Keuangan::orderBy('periode')->get()->map(fn (Keuangan $k) => [
                $k->kunciPeriode(), $k->pemasukan, $k->pengeluaran, $k->keuntungan(), $k->bonusKaryawan(),
                preg_replace('/\s+/', ' ', (string) $k->catatan),
            ])
        );
    }

    /**
     * Impor bulan-bulan lama dari CSV: bulan, omset, pengeluaran, catatan
     * (opsional). Kolom keuntungan & bonus di berkas diabaikan — keduanya
     * selalu dihitung ulang dari omset dan pengeluaran.
     *
     * Semua-atau-tidak-sama-sekali, seperti impor saldo.
     */
    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate(
            ['berkas' => ['required', 'file', 'max:2048', 'mimes:csv,txt']],
            ['berkas.required' => 'Pilih berkas CSV.', 'berkas.mimes' => 'Berkas harus berformat CSV.', 'berkas.max' => 'Ukuran berkas maksimal 2 MB.']
        );

        try {
            $baris = CsvKeuangan::baca($request->file('berkas'));
        } catch (\Throwable $e) {
            report($e);
            return $this->galatImpor(['Berkas tidak bisa dibaca. Simpan ulang dari Excel/Sheets sebagai CSV lalu coba lagi.']);
        }
        if ($baris === []) {
            return $this->galatImpor(['Berkas kosong.']);
        }

        ['peta' => $peta, 'adaJudul' => $judul, 'dariJudul' => $dariJudul] = CsvKeuangan::petaKolom($baris[0]['sel'], [
            'bulan'       => ['bulan', 'periode', 'month'],
            'omset'       => ['omset', 'omzet', 'pemasukan', 'pendapatan', 'revenue'],
            'pengeluaran' => ['pengeluaran', 'biaya', 'expense', 'expenses'],
            'catatan'     => ['catatan', 'keterangan', 'note', 'notes'],
        ], ['bulan', 'omset', 'pengeluaran', 'catatan']);

        if ($judul || CsvKeuangan::bulan(CsvKeuangan::sel($baris[0]['sel'], $peta, 'bulan')) === null) {
            array_shift($baris);
        }
        if (count($baris) > CsvKeuangan::MAKS_BARIS) {
            return $this->galatImpor(['Maksimal ' . CsvKeuangan::MAKS_BARIS . ' baris sekali impor.']);
        }

        $galat = [];
        $data  = [];
        $bulanIni = now()->startOfMonth();
        foreach ($baris as ['no' => $no, 'sel' => $sel]) {
            $bln    = CsvKeuangan::bulan(CsvKeuangan::sel($sel, $peta, 'bulan'));
            $omset  = CsvKeuangan::angka(CsvKeuangan::sel($sel, $peta, 'omset'));
            $keluarTeks = CsvKeuangan::sel($sel, $peta, 'pengeluaran');
            $keluar = $keluarTeks === '' ? 0 : CsvKeuangan::angka($keluarTeks);

            if ($bln === null) {
                $galat[] = "Baris $no: bulan \"" . CsvKeuangan::sel($sel, $peta, 'bulan') . '" tidak dikenali (contoh: 2026-04 atau April 2026).';
            } elseif ($bln->greaterThan($bulanIni)) {
                $galat[] = "Baris $no: " . $bln->translatedFormat('F Y') . ' belum berjalan.';
            } elseif ($omset === null) {
                $galat[] = "Baris $no: omset \"" . CsvKeuangan::sel($sel, $peta, 'omset') . '" bukan angka rupiah yang sah.';
            } elseif ($keluar === null) {
                $galat[] = "Baris $no: pengeluaran \"" . $keluarTeks . '" bukan angka rupiah yang sah.';
            } elseif (isset($data[$bln->format('Y-m')])) {
                $galat[] = "Baris $no: " . $bln->translatedFormat('F Y') . " sudah ada di baris {$data[$bln->format('Y-m')]['no']}.";
            } else {
                $data[$bln->format('Y-m')] = [
                    'no' => $no, 'periode' => $bln, 'pemasukan' => $omset, 'pengeluaran' => $keluar,
                    // Catatan dipakai bila kolomnya berjudul "catatan", atau selnya
                    // berisi. Kolom kosong di ujung (sisa Excel) tidak boleh
                    // menghapus catatan yang sudah ada.
                    'catatan' => in_array('catatan', $dariJudul, true) || CsvKeuangan::sel($sel, $peta, 'catatan') !== ''
                        ? (mb_substr(CsvKeuangan::sel($sel, $peta, 'catatan'), 0, 1000) ?: null)
                        : false,
                ];
            }
        }

        if ($galat !== []) {
            return $this->galatImpor($galat);
        }
        if ($data === []) {
            return $this->galatImpor(['Tidak ada baris data di berkas.']);
        }

        $baru = 0; $ubah = 0;
        DB::transaction(function () use ($data, &$baru, &$ubah) {
            foreach ($data as $d) {
                $isi = ['pemasukan' => $d['pemasukan'], 'pengeluaran' => $d['pengeluaran'], 'dicatat_by' => Auth::id()];
                // Tanpa kolom catatan di berkas, catatan yang sudah ada tidak dihapus.
                if ($d['catatan'] !== false) {
                    $isi['catatan'] = $d['catatan'];
                }
                $ada = Keuangan::whereDate('periode', $d['periode']->toDateString())->first();
                if ($ada) {
                    $ada->update($isi);
                    $ubah++;
                } else {
                    Keuangan::create($isi + ['periode' => $d['periode']]);
                    $baru++;
                }
            }
        });

        return redirect()->route('admin.keuangan.index')
            ->with('success', "Impor selesai: $baru bulan baru" . ($ubah ? ", $ubah bulan diperbarui" : '') . '.');
    }

    /** @param list<string> $galat */
    private function galatImpor(array $galat): RedirectResponse
    {
        return redirect()->route('admin.keuangan.index')
            ->with('galat_impor', $galat)
            ->with('error', 'Impor dibatalkan — tidak ada data yang disimpan. Perbaiki ' . count($galat) . ' masalah di bawah lalu unggah ulang.');
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
