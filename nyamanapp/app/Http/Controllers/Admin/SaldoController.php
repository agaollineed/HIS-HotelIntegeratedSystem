<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaldoHarian;
use App\Support\CsvKeuangan;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Live report saldo rekening — admin dan superadmin.
 *
 * Isiannya sengaja hanya dua: tanggal dan saldo. Grafik saldo harian dan
 * perbandingan setiap tanggal 5 dihitung dari deret itu, lalu disegarkan
 * otomatis di halaman tanpa perlu memuat ulang.
 */
class SaldoController extends Controller
{
    public function index(): View
    {
        $riwayat = SaldoHarian::with('pencatat')
            ->orderByDesc('tanggal')
            ->limit(31)
            ->get();

        $hariIni = SaldoHarian::whereDate('tanggal', now()->toDateString())->first();

        return view('admin.keuangan.saldo', [
            'riwayat'  => $riwayat,
            'hariIni'  => $hariIni,
            'ringkas'  => $this->ringkasan(),
            'tglHariIni' => now()->toDateString(),
        ]);
    }

    /** Data grafik (JSON) — dipanggil berkala supaya halaman tetap live. */
    public function data(): JsonResponse
    {
        return response()->json([
            'harian'     => SaldoHarian::deret(),
            'lima'       => SaldoHarian::perTanggalLima(),
            'ringkas'    => $this->ringkasan(),
            'diperbarui' => now()->translatedFormat('H:i:s'),
        ]);
    }

    /**
     * Catat saldo satu tanggal. Tanggal yang sudah ada diperbarui — satu
     * tanggal hanya punya satu saldo.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'saldo'   => ['required', 'integer', 'min:0', 'max:9999999999999'],
        ], [
            'tanggal.required'        => 'Pilih tanggal saldo.',
            'tanggal.date_format'     => 'Format tanggal tidak valid.',
            'tanggal.before_or_equal' => 'Saldo hanya bisa dicatat untuk hari ini atau tanggal yang sudah lewat.',
            'saldo.required'          => 'Isi saldo rekening.',
            'saldo.integer'           => 'Saldo harus berupa angka rupiah tanpa titik.',
            'saldo.min'               => 'Saldo tidak boleh negatif.',
            'saldo.max'               => 'Saldo terlalu besar — periksa lagi angkanya.',
        ]);

        $tanggal = Carbon::createFromFormat('Y-m-d', $data['tanggal'])->startOfDay();

        // whereDate, bukan pencocokan string: kolom date ditulis Eloquent
        // sebagai '2026-11-09 00:00:00', sehingga '2026-11-09' tidak akan cocok.
        $ada = SaldoHarian::whereDate('tanggal', $tanggal->toDateString())->first();

        if ($ada !== null) {
            $ada->update(['saldo' => (int) $data['saldo'], 'dicatat_by' => Auth::id()]);
        } else {
            SaldoHarian::create(['tanggal' => $tanggal, 'saldo' => (int) $data['saldo'], 'dicatat_by' => Auth::id()]);
        }

        return redirect()->route('admin.keuangan.saldo')
            ->with('success', 'Saldo ' . $tanggal->translatedFormat('j F Y') . ' '
                . ($ada !== null ? 'diperbarui' : 'tersimpan') . ': ' . SaldoHarian::rupiah((int) $data['saldo']) . '.');
    }

    /** Unduh seluruh saldo sebagai CSV — sekaligus contoh format untuk impor. */
    public function exportCsv()
    {
        return CsvKeuangan::unduh(
            'saldo-harian-' . now()->format('Ymd') . '.csv',
            ['tanggal', 'saldo'],
            SaldoHarian::orderBy('tanggal')->get()->map(fn (SaldoHarian $s) => [$s->tanggal->toDateString(), $s->saldo])
        );
    }

    /**
     * Impor saldo lama dari CSV: kolom tanggal dan saldo.
     *
     * Semua-atau-tidak-sama-sekali: bila satu baris saja tidak sah, tidak ada
     * yang disimpan dan seluruh baris yang perlu diperbaiki ditampilkan.
     * Tanggal yang sudah ada diperbarui, sama seperti mengisi formulir.
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

        ['peta' => $peta, 'adaJudul' => $judul] = CsvKeuangan::petaKolom($baris[0]['sel'], [
            'tanggal' => ['tanggal', 'tgl', 'date'],
            'saldo'   => ['saldo', 'saldorekening', 'balance', 'nominal', 'jumlah'],
        ], ['tanggal', 'saldo']);

        // Judul yang tidak dikenali tetap dilewati bila sel tanggalnya bukan tanggal.
        if ($judul || CsvKeuangan::tanggal(CsvKeuangan::sel($baris[0]['sel'], $peta, 'tanggal')) === null) {
            array_shift($baris);
        }
        $urutan = CsvKeuangan::urutanTanggal(array_map(fn ($b) => CsvKeuangan::sel($b['sel'], $peta, 'tanggal'), $baris));
        if (count($baris) > CsvKeuangan::MAKS_BARIS) {
            return $this->galatImpor(['Maksimal ' . CsvKeuangan::MAKS_BARIS . ' baris sekali impor.']);
        }

        $galat = [];
        $data  = [];
        foreach ($baris as ['no' => $no, 'sel' => $sel]) {
            $tgl   = CsvKeuangan::tanggal(CsvKeuangan::sel($sel, $peta, 'tanggal'), $urutan);
            $saldo = CsvKeuangan::angka(CsvKeuangan::sel($sel, $peta, 'saldo'));

            if ($tgl === null) {
                $galat[] = "Baris $no: tanggal \"" . CsvKeuangan::sel($sel, $peta, 'tanggal') . '" tidak dikenali.';
            } elseif ($tgl->isFuture()) {
                $galat[] = "Baris $no: tanggal {$tgl->format('d/m/Y')} belum lewat.";
            } elseif ($saldo === null || $saldo > 9999999999999) {
                $galat[] = "Baris $no: saldo \"" . CsvKeuangan::sel($sel, $peta, 'saldo') . '" bukan angka rupiah yang sah.';
            } elseif (isset($data[$tgl->toDateString()])) {
                $galat[] = "Baris $no: tanggal {$tgl->format('d/m/Y')} sudah ada di baris {$data[$tgl->toDateString()]['no']}.";
            } else {
                $data[$tgl->toDateString()] = ['no' => $no, 'saldo' => $saldo];
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
            foreach ($data as $tanggal => $d) {
                $ada = SaldoHarian::whereDate('tanggal', $tanggal)->first();
                if ($ada) {
                    $ada->update(['saldo' => $d['saldo'], 'dicatat_by' => Auth::id()]);
                    $ubah++;
                } else {
                    SaldoHarian::create(['tanggal' => $tanggal, 'saldo' => $d['saldo'], 'dicatat_by' => Auth::id()]);
                    $baru++;
                }
            }
        });

        // Angka yang janggal dibanding hari-hari sekitarnya: tetap disimpan,
        // tetapi ditunjukkan supaya salah ketik di spreadsheet ketahuan.
        ksort($data);
        $peringatan = array_map(fn ($j) => \Illuminate\Support\Carbon::parse($j['tanggal'])->translatedFormat('j M Y') . ': '
            . SaldoHarian::rupiah($j['saldo']) . ' — saldo hari-hari sekitarnya ±' . SaldoHarian::rupiah($j['sekitar']) . '.',
            CsvKeuangan::janggal(array_map(fn ($d) => $d['saldo'], $data)));

        return redirect()->route('admin.keuangan.saldo')
            ->with('peringatan_impor', $peringatan)
            ->with('success', "Impor selesai: $baru tanggal baru" . ($ubah ? ", $ubah tanggal diperbarui" : '') . '.'
                . ($peringatan ? ' Ada ' . count($peringatan) . ' angka yang jauh berbeda dari hari sekitarnya — lihat panel Import.' : ''));
    }

    /** @param list<string> $galat */
    private function galatImpor(array $galat): RedirectResponse
    {
        return redirect()->route('admin.keuangan.saldo')
            ->with('galat_impor', $galat)
            ->with('error', 'Impor dibatalkan — tidak ada data yang disimpan. Perbaiki ' . count($galat) . ' masalah di bawah lalu unggah ulang.');
    }

    public function destroy(SaldoHarian $saldo): RedirectResponse
    {
        abort_unless(Auth::user()->bolehHapusKeuangan(), 403);

        $label = $saldo->tanggal->translatedFormat('j F Y');
        $saldo->delete();

        return redirect()->route('admin.keuangan.saldo')->with('success', 'Saldo ' . $label . ' dihapus.');
    }

    /**
     * Angka ringkas di atas halaman: saldo terakhir, perubahannya dari
     * catatan sebelumnya, dan dari tanggal 5 bulan ini.
     *
     * @return array<string, mixed>
     */
    private function ringkasan(): array
    {
        $dua      = SaldoHarian::orderByDesc('tanggal')->limit(2)->get();
        $terakhir = $dua->first();
        $sebelum  = $dua->get(1);

        $lima = collect(SaldoHarian::perTanggalLima())->last();
        $limaIni = $lima && $lima['bulan'] === now()->format('Y-m') ? $lima : null;

        return [
            'saldo'          => $terakhir?->saldo,
            'tanggal'        => $terakhir?->tanggal->translatedFormat('j M Y'),
            'perubahan'      => ($terakhir && $sebelum) ? $terakhir->saldo - $sebelum->saldo : null,
            'tanggalSebelum' => $sebelum?->tanggal->translatedFormat('j M'),
            'vsLima'         => ($terakhir && $limaIni && $limaIni['saldo'] !== null) ? $terakhir->saldo - $limaIni['saldo'] : null,
            'hariIniTercatat' => $terakhir !== null && $terakhir->tanggal->isToday(),
        ];
    }
}
