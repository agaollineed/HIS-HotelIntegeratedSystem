<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PajakBulanan;
use App\Support\CsvKeuangan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pajak bulanan: nominal dan status bayar.
 *
 * Tanggal bayar diisi otomatis saat dicentang:
 *   - bulan November 2026 dan sesudahnya: tanggal hari dicentang;
 *   - bulan-bulan sebelumnya: tanggal 5 bulan berikutnya.
 */
class PajakController extends Controller
{
    public function index(): View
    {
        $baris = PajakBulanan::with('pencatat')->orderByDesc('periode')->get();

        return view('admin.keuangan.pajak', [
            'baris'    => $baris,
            'bulanIni' => now()->format('Y-m'),
            'ringkas'  => [
                'total'  => (int) $baris->sum('nominal'),
                'lunas'  => (int) $baris->filter->dibayar()->sum('nominal'),
                'belum'  => (int) $baris->reject->dibayar()->sum('nominal'),
                'nBelum' => $baris->reject->dibayar()->count(),
            ],
        ]);
    }

    /** Catat atau perbarui satu bulan. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'periode' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'nominal' => ['required', 'integer', 'min:0', 'max:9999999999999'],
            'dibayar' => ['nullable', 'boolean'],
        ], [
            'periode.required' => 'Pilih bulan pajak.',
            'periode.regex'    => 'Format bulan tidak valid.',
            'nominal.required' => 'Isi nominal pajak.',
            'nominal.integer'  => 'Nominal harus berupa angka rupiah tanpa titik.',
            'nominal.min'      => 'Nominal tidak boleh negatif.',
        ]);

        $periode = Carbon::createFromFormat('Y-m-d', $data['periode'] . '-01')->startOfDay();
        if ($periode->gt(now()->startOfMonth())) {
            return back()->withInput()->withErrors(['periode' => 'Bulan tersebut belum berjalan.']);
        }

        $ada     = PajakBulanan::whereDate('periode', $periode->toDateString())->first();
        $dibayar = $request->boolean('dibayar');
        // Yang sudah dibayar tetap memakai tanggal bayarnya yang lama.
        $tanggal = $dibayar ? ($ada?->tanggal_bayar ?? PajakBulanan::tanggalBayarUntuk($periode)) : null;

        $isi = ['nominal' => (int) $data['nominal'], 'tanggal_bayar' => $tanggal, 'dicatat_by' => Auth::id()];
        $ada ? $ada->update($isi) : PajakBulanan::create($isi + ['periode' => $periode]);

        return redirect()->route('admin.keuangan.pajak')
            ->with('success', 'Pajak ' . $periode->translatedFormat('F Y') . ' ' . ($ada ? 'diperbarui' : 'tercatat') . ': '
                . CsvKeuangan::rupiah((int) $data['nominal'])
                . ($tanggal ? ', dibayar ' . $tanggal->translatedFormat('j F Y') . '.' : ', belum dibayar.'));
    }

    /** Centang / batalkan status dibayar. */
    public function bayar(PajakBulanan $pajak): RedirectResponse
    {
        $tanggal = $pajak->dibayar() ? null : PajakBulanan::tanggalBayarUntuk($pajak->periode);
        $pajak->update(['tanggal_bayar' => $tanggal, 'dicatat_by' => Auth::id()]);

        return redirect()->route('admin.keuangan.pajak')
            ->with('success', 'Pajak ' . $pajak->labelPeriode() . ($tanggal
                ? ' ditandai dibayar ' . $tanggal->translatedFormat('j F Y') . '.'
                : ' ditandai belum dibayar.'));
    }

    public function destroy(PajakBulanan $pajak): RedirectResponse
    {
        abort_unless(Auth::user()->bolehHapusKeuangan(), 403);

        $label = $pajak->labelPeriode();
        $pajak->delete();

        return redirect()->route('admin.keuangan.pajak')->with('success', 'Pajak ' . $label . ' dihapus.');
    }

    public function exportCsv()
    {
        return CsvKeuangan::unduh(
            'pajak-' . now()->format('Ymd') . '.csv',
            ['bulan', 'nominal', 'dibayar', 'tanggal_bayar'],
            PajakBulanan::orderBy('periode')->get()->map(fn (PajakBulanan $p) => [
                $p->kunciPeriode(), $p->nominal, $p->dibayar() ? 'ya' : 'tidak', $p->tanggal_bayar?->toDateString() ?? '',
            ])
        );
    }

    /**
     * Impor: bulan, nominal, dibayar (ya/tidak), tanggal_bayar (opsional).
     * Bila dibayar tanpa tanggal, tanggalnya mengikuti aturan yang sama dengan
     * mencentang di aplikasi. Semua-atau-tidak-sama-sekali.
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
            'bulan'   => ['bulan', 'periode', 'month'],
            'nominal' => ['nominal', 'pajak', 'jumlah', 'amount'],
            'dibayar' => ['dibayar', 'status', 'lunas', 'bayar', 'paid'],
            'tanggal' => ['tanggalbayar', 'tglbayar', 'tanggal', 'paidat'],
        ], ['bulan', 'nominal', 'dibayar', 'tanggal']);

        if ($judul || CsvKeuangan::bulan(CsvKeuangan::sel($baris[0]['sel'], $peta, 'bulan')) === null) {
            array_shift($baris);
        }

        $galat = [];
        $data  = [];
        foreach ($baris as ['no' => $no, 'sel' => $sel]) {
            $bln  = CsvKeuangan::bulan(CsvKeuangan::sel($sel, $peta, 'bulan'));
            $nom  = CsvKeuangan::angka(CsvKeuangan::sel($sel, $peta, 'nominal'));
            $stat = strtolower(trim(CsvKeuangan::sel($sel, $peta, 'dibayar')));
            $tTks = trim(CsvKeuangan::sel($sel, $peta, 'tanggal'));
            $tgl  = $tTks === '' ? null : CsvKeuangan::tanggal($tTks);
            $bayar = in_array($stat, ['ya', 'y', 'sudah', 'lunas', '1', 'true', 'v', '✓', 'x', 'dibayar', 'yes'], true) || ($stat === '' && $tgl !== null);

            if ($bln === null) {
                $galat[] = "Baris $no: bulan \"" . CsvKeuangan::sel($sel, $peta, 'bulan') . '" tidak dikenali.';
            } elseif ($bln->gt(now()->startOfMonth())) {
                $galat[] = "Baris $no: " . $bln->translatedFormat('F Y') . ' belum berjalan.';
            } elseif ($nom === null) {
                $galat[] = "Baris $no: nominal \"" . CsvKeuangan::sel($sel, $peta, 'nominal') . '" bukan angka rupiah yang sah.';
            } elseif ($tTks !== '' && $tgl === null) {
                $galat[] = "Baris $no: tanggal bayar \"$tTks\" tidak dikenali.";
            } elseif (isset($data[$bln->format('Y-m')])) {
                $galat[] = "Baris $no: " . $bln->translatedFormat('F Y') . " sudah ada di baris {$data[$bln->format('Y-m')]['no']}.";
            } else {
                $data[$bln->format('Y-m')] = ['no' => $no, 'periode' => $bln, 'nominal' => $nom,
                    'tanggal_bayar' => $bayar ? ($tgl ?? PajakBulanan::tanggalBayarUntuk($bln)) : null];
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
                $isi = ['nominal' => $d['nominal'], 'tanggal_bayar' => $d['tanggal_bayar'], 'dicatat_by' => Auth::id()];
                $ada = PajakBulanan::whereDate('periode', $d['periode']->toDateString())->first();
                $ada ? $ada->update($isi) : PajakBulanan::create($isi + ['periode' => $d['periode']]);
                $ada ? $ubah++ : $baru++;
            }
        });

        return redirect()->route('admin.keuangan.pajak')
            ->with('success', "Impor selesai: $baru bulan baru" . ($ubah ? ", $ubah bulan diperbarui" : '') . '.');
    }

    /** @param list<string> $galat */
    private function galatImpor(array $galat): RedirectResponse
    {
        return redirect()->route('admin.keuangan.pajak')
            ->with('galat_impor', $galat)
            ->with('error', 'Impor dibatalkan — tidak ada data yang disimpan. Perbaiki ' . count($galat) . ' masalah di bawah lalu unggah ulang.');
    }
}
