<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BukuBesar;
use App\Models\BukuBesarAwal;
use App\Support\BukuBesarLaporan;
use App\Support\CsvKeuangan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Buku besar — salinan lembar "SALDO BANK" di Excel: uang masuk, uang keluar,
 * dan saldo akhir untuk BNI, BRI, dan totalnya, satu baris per hari.
 *
 * Admin dan superadmin mengisi dan mengoreksi; menghapus hanya superadmin.
 */
class BukuBesarController extends Controller
{
    /** Batas angka satu sel (Rp 9,9 triliun) — penjaga salah ketik, bukan aturan usaha. */
    private const MAKS = 9999999999999;

    public function index(Request $request): View
    {
        $pertama = BukuBesarLaporan::bulanPertama();
        $ini     = now()->startOfMonth();
        $bulan   = CsvKeuangan::bulan((string) $request->query('bulan')) ?? $ini;
        $bulan   = $bulan->max($pertama)->min($ini->max($pertama));

        return view('admin.keuangan.buku-besar', [
            'bulan'    => $bulan,
            'laporan'  => BukuBesarLaporan::bulan($bulan),
            'terkini'  => BukuBesarLaporan::terkini(),
            'awal'     => BukuBesarAwal::aktif(),
            'sebelum'  => $bulan->gt($pertama) ? $bulan->copy()->subMonth()->format('Y-m') : null,
            'sesudah'  => $bulan->lt($ini) ? $bulan->copy()->addMonth()->format('Y-m') : null,
            'hariIni'  => now()->toDateString(),
        ]);
    }

    /**
     * Catat satu hari. Mengisi tanggal yang sudah ada MENGGANTI angka hari itu
     * (seperti mengubah sel di Excel); mengosongkan semuanya menghapus hari itu.
     */
    public function store(Request $request): RedirectResponse
    {
        $awal = BukuBesarAwal::aktif();
        $uang = ['nullable', 'integer', 'min:0', 'max:' . self::MAKS];

        $data = $request->validate([
            'tanggal'    => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:' . $awal->tanggal->toDateString()],
            'masuk_bni'  => $uang,
            'masuk_bri'  => $uang,
            'keluar_bni' => $uang,
            'keluar_bri' => $uang,
            'keterangan' => ['nullable', 'string', 'max:500'],
        ], [
            'tanggal.required'        => 'Pilih tanggal.',
            'tanggal.before_or_equal' => 'Tanggal belum lewat.',
            'tanggal.after'           => 'Tanggal harus sesudah saldo awal (' . $awal->tanggal->translatedFormat('j F Y') . ').',
            '*.integer'               => 'Isi angka rupiah tanpa titik.',
            '*.min'                   => 'Angka tidak boleh negatif.',
        ]);

        $tanggal = Carbon::createFromFormat('Y-m-d', $data['tanggal'])->startOfDay();
        $isi = [
            'masuk_bni'  => (int) ($data['masuk_bni'] ?? 0),
            'masuk_bri'  => (int) ($data['masuk_bri'] ?? 0),
            'keluar_bni' => (int) ($data['keluar_bni'] ?? 0),
            'keluar_bri' => (int) ($data['keluar_bri'] ?? 0),
            'keterangan' => trim((string) ($data['keterangan'] ?? '')) ?: null,
        ];

        $ada = BukuBesar::whereDate('tanggal', $tanggal->toDateString())->first();

        if (array_sum(array_slice($isi, 0, 4)) === 0 && $isi['keterangan'] === null) {
            $ada?->delete();
            $pesan = 'Buku besar ' . $tanggal->translatedFormat('j F Y') . ' dikosongkan.';
        } else {
            $ada ? $ada->update($isi + ['dicatat_by' => Auth::id()])
                 : BukuBesar::create($isi + ['tanggal' => $tanggal, 'dicatat_by' => Auth::id()]);
            $pesan = 'Buku besar ' . $tanggal->translatedFormat('j F Y') . ($ada ? ' diperbarui.' : ' tersimpan.');
        }

        $saldo = BukuBesarLaporan::saldoHarian($tanggal, $tanggal)[$tanggal->toDateString()];

        return redirect()->route('admin.keuangan.buku-besar', ['bulan' => $tanggal->format('Y-m')])
            ->with('success', $pesan . ' Saldo akhir hari itu: BNI ' . CsvKeuangan::rupiah($saldo[0])
                . ', BRI ' . CsvKeuangan::rupiah($saldo[1]) . ', total ' . CsvKeuangan::rupiah($saldo[0] + $saldo[1]) . '.');
    }

    /** Saldo awal sebelum pencatatan pertama. */
    public function simpanAwal(Request $request): RedirectResponse
    {
        $pertama = BukuBesar::query()->orderBy('tanggal')->value('tanggal');

        $data = $request->validate([
            'tanggal' => array_filter(['required', 'date_format:Y-m-d', $pertama ? 'before:' . Carbon::parse($pertama)->toDateString() : null]),
            'bni'     => ['required', 'integer', 'min:-' . self::MAKS, 'max:' . self::MAKS],
            'bri'     => ['required', 'integer', 'min:-' . self::MAKS, 'max:' . self::MAKS],
        ], [
            'tanggal.before' => 'Tanggal saldo awal harus sebelum catatan pertama buku besar.',
            '*.integer'      => 'Isi angka rupiah tanpa titik.',
        ]);

        BukuBesarAwal::simpan($data['tanggal'], (int) $data['bni'], (int) $data['bri']);

        return redirect()->route('admin.keuangan.buku-besar')->with('success', 'Saldo awal buku besar diperbarui. Semua saldo akhir ikut dihitung ulang.');
    }

    public function destroy(BukuBesar $buku): RedirectResponse
    {
        abort_unless(Auth::user()->bolehHapusKeuangan(), 403);

        $tanggal = $buku->tanggal;
        $buku->delete();

        return redirect()->route('admin.keuangan.buku-besar', ['bulan' => $tanggal->format('Y-m')])
            ->with('success', 'Catatan ' . $tanggal->translatedFormat('j F Y') . ' dihapus.');
    }

    /**
     * Unduh CSV dengan susunan kolom yang sama dengan lembar Excel:
     * TGL | UANG MASUK (BNI, BRI, TOTAL) | UANG KELUAR (…) | SALDO AKHIR (…) | KETERANGAN.
     * Berkas ini bisa langsung diimpor ulang.
     */
    public function exportCsv()
    {
        $awal   = BukuBesarAwal::aktif();
        $baris  = [
            ['TGL', 'UANG MASUK', '', '', '', 'UANG KELUAR', '', '', '', 'SALDO AKHIR', '', '', '', 'KETERANGAN'],
            ['', 'BNI', 'BRI', 'TOTAL', '', 'BNI', 'BRI', 'TOTAL', '', 'BNI', 'BRI', 'TOTAL', '', ''],
            ['SALDO AWAL ' . $awal->tanggal->toDateString(), '', '', '', '', '', '', '', '', $awal->bni, $awal->bri, $awal->total(), '', ''],
        ];

        $akhir = now()->startOfDay()->max(Carbon::parse(BukuBesar::query()->max('tanggal') ?? now()));
        for ($b = BukuBesarLaporan::bulanPertama(); $b->lte($akhir); $b->addMonth()) {
            foreach (BukuBesarLaporan::bulan($b)['baris'] as $r) {
                $baris[] = [
                    $r['tanggal']->toDateString(),
                    $r['masuk_bni'], $r['masuk_bri'], $r['masuk_bni'] + $r['masuk_bri'], '',
                    $r['keluar_bni'], $r['keluar_bri'], $r['keluar_bni'] + $r['keluar_bri'], '',
                    $r['saldo_bni'], $r['saldo_bri'], $r['saldo_bni'] + $r['saldo_bri'], '',
                    $r['keterangan'] ?? '',
                ];
            }
        }

        $judul = array_shift($baris);

        return CsvKeuangan::unduh('buku-besar-' . now()->format('Ymd') . '.csv', $judul, $baris);
    }

    /**
     * Impor dari CSV lembar Excel "SALDO BANK" (atau hasil Export CSV di atas).
     *
     * - Baris saldo awal dikenali (baris tanpa tanggal dengan angka di kolom
     *   SALDO AWAL, atau baris "SALDO AWAL" hasil ekspor).
     * - Berkas berlaku untuk tanggal-tanggal yang dimuatnya: hari yang di
     *   berkas kosong ikut dikosongkan di aplikasi.
     * - Semua-atau-tidak-sama-sekali; kolom saldo akhir di berkas dipakai untuk
     *   mencocokkan hitungan, dan selisihnya ditampilkan sebagai peringatan.
     */
    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate(
            ['berkas' => ['required', 'file', 'max:4096', 'mimes:csv,txt']],
            ['berkas.required' => 'Pilih berkas CSV.', 'berkas.mimes' => 'Berkas harus berformat CSV.', 'berkas.max' => 'Ukuran berkas maksimal 4 MB.']
        );

        try {
            $baris = CsvKeuangan::baca($request->file('berkas'));
        } catch (\Throwable $e) {
            report($e);
            return $this->galatImpor(['Berkas tidak bisa dibaca. Simpan ulang dari Excel/Sheets sebagai CSV lalu coba lagi.']);
        }

        $urutan  = CsvKeuangan::urutanTanggal(array_map(fn ($b) => $b['sel'][0] ?? '', $baris));
        $galat   = [];
        $hari    = [];      // Y-m-d => ['no', masuk/keluar, keterangan]
        $cek     = [];      // Y-m-d => [no, saldo bni, saldo bri] dari berkas
        $awalBaru = null;
        $besok   = now()->addDay()->startOfDay();

        foreach ($baris as ['no' => $no, 'sel' => $sel]) {
            $c0 = trim($sel[0] ?? '');

            // Saldo awal: baris "SALDO AWAL" hasil ekspor, atau baris tanpa
            // tanggal dengan angka di kolom SALDO AWAL lembar Excel.
            if (preg_match('/^saldo\s*awal/i', $c0)) {
                $awalBaru = [$this->uang($sel[9] ?? ''), $this->uang($sel[10] ?? ''),
                    CsvKeuangan::tanggal(trim(preg_replace('/^saldo\s*awal/i', '', $c0)))];
                continue;
            }
            if ($c0 === '' && $hari === [] && $awalBaru === null && preg_match('/^[\d.,\s]+$/', trim($sel[13] ?? ''))) {
                $awalBaru = [$this->uang($sel[13] ?? ''), $this->uang($sel[14] ?? ''), null];
                continue;
            }

            $tgl = CsvKeuangan::tanggal($c0, $urutan);
            if ($tgl === null) {
                if (preg_match('/^\d{1,2}[-\/. ]/', $c0)) {
                    $galat[] = "Baris $no: tanggal \"$c0\" tidak dikenali.";
                }
                continue;   // judul, baris kosong, dsb.
            }

            $angka = [];
            foreach (['masuk_bni' => 1, 'masuk_bri' => 2, 'keluar_bni' => 5, 'keluar_bri' => 6] as $k => $i) {
                $angka[$k] = $this->uang($sel[$i] ?? '');
                if ($angka[$k] === null) {
                    $galat[] = "Baris $no ($c0): \"" . ($sel[$i] ?? '') . '" bukan angka rupiah yang sah.';
                }
            }
            if (in_array(null, $angka, true)) {
                continue;
            }

            $ket = trim($sel[13] ?? '');
            $ket = ($ket !== '' && ! preg_match('/^[\d.,\s\-]+$/', $ket)) ? mb_substr($ket, 0, 500) : null;
            $gerak = array_sum($angka) > 0;

            if (isset($hari[$tgl->toDateString()])) {
                $galat[] = "Baris $no: tanggal " . $tgl->format('d/m/Y') . " sudah ada di baris {$hari[$tgl->toDateString()]['no']}.";
                continue;
            }
            if ($tgl->gte($besok)) {
                if ($gerak || $ket) {
                    $galat[] = "Baris $no: tanggal " . $tgl->format('d/m/Y') . ' belum lewat.';
                }
                continue;   // baris templat hari-hari ke depan
            }

            $hari[$tgl->toDateString()] = ['no' => $no, 'isi' => $angka + ['keterangan' => $ket], 'gerak' => $gerak || $ket];

            $sb = $this->uang($sel[9] ?? '');
            $sr = $this->uang($sel[10] ?? '');
            if (trim($sel[9] ?? '') !== '' && $sb !== null && $sr !== null) {
                $cek[$tgl->toDateString()] = [$no, $sb, $sr];
            }
        }

        if ($galat !== []) {
            return $this->galatImpor($galat);
        }
        if ($hari === []) {
            return $this->galatImpor(['Tidak ada baris bertanggal di berkas.']);
        }

        ksort($hari);
        $pertama = Carbon::parse(array_key_first($hari));
        $tglAwal = $awalBaru[2] ?? ($awalBaru ? $pertama->copy()->subDay() : BukuBesarAwal::aktif()->tanggal);

        if ($pertama->lte($tglAwal)) {
            return $this->galatImpor(['Tanggal ' . $pertama->format('d/m/Y') . ' tidak boleh sebelum atau sama dengan tanggal saldo awal (' . $tglAwal->format('d/m/Y') . ').']);
        }

        $baru = 0; $ubah = 0; $kosong = 0;
        DB::transaction(function () use ($hari, $awalBaru, $tglAwal, &$baru, &$ubah, &$kosong) {
            if ($awalBaru !== null) {
                BukuBesarAwal::simpan($tglAwal->toDateString(), (int) ($awalBaru[0] ?? 0), (int) ($awalBaru[1] ?? 0));
            }

            foreach ($hari as $tanggal => $h) {
                $ada = BukuBesar::whereDate('tanggal', $tanggal)->first();
                if (! $h['gerak']) {
                    // Hari yang kosong di berkas: kosong juga di aplikasi.
                    if ($ada) { $ada->delete(); $kosong++; }
                    continue;
                }
                if ($ada) {
                    $ada->update($h['isi'] + ['dicatat_by' => Auth::id()]);
                    $ubah++;
                } else {
                    BukuBesar::create($h['isi'] + ['tanggal' => $tanggal, 'dicatat_by' => Auth::id()]);
                    $baru++;
                }
            }
        });

        // Cocokkan saldo akhir di berkas dengan hitungan aplikasi.
        $peringatan = [];
        if ($cek !== []) {
            $hitung = BukuBesarLaporan::saldoHarian(Carbon::parse(array_key_first($cek))->min($pertama), Carbon::parse(array_key_last($cek)));
            foreach ($cek as $tanggal => [$no, $sb, $sr]) {
                [$hb, $hr] = $hitung[$tanggal] ?? [null, null];
                if ($hb !== $sb || $hr !== $sr) {
                    $peringatan[] = "Baris $no (" . Carbon::parse($tanggal)->translatedFormat('j M Y') . '): saldo di berkas BNI '
                        . CsvKeuangan::rupiah($sb) . ' / BRI ' . CsvKeuangan::rupiah($sr) . ', hitungan aplikasi BNI '
                        . CsvKeuangan::rupiah((int) $hb) . ' / BRI ' . CsvKeuangan::rupiah((int) $hr) . '.';
                }
            }
        }

        $akhir = BukuBesarLaporan::terkini();

        return redirect()->route('admin.keuangan.buku-besar', ['bulan' => Carbon::parse(array_key_last($hari))->format('Y-m')])
            ->with('peringatan_impor', $peringatan)
            ->with('judul_peringatan', 'Sudah tersimpan, tapi saldo akhir di berkas berbeda dengan hitungan aplikasi:')
            ->with('success', "Impor selesai: $baru hari baru" . ($ubah ? ", $ubah hari diperbarui" : '') . ($kosong ? ", $kosong hari dikosongkan" : '')
                . ($awalBaru ? ', saldo awal diperbarui' : '') . '. Saldo terkini: ' . CsvKeuangan::rupiah($akhir['total'])
                . ($peringatan ? '. Ada ' . count($peringatan) . ' saldo yang tidak cocok — lihat panel Import.' : '. Semua saldo akhir di berkas cocok.'));
    }

    /** Rupiah dari sel; sel kosong atau "-" = 0, bukan angka = null. */
    private function uang(string $v): ?int
    {
        $v = trim($v);

        return ($v === '' || $v === '-') ? 0 : CsvKeuangan::angka($v);
    }

    /** @param list<string> $galat */
    private function galatImpor(array $galat): RedirectResponse
    {
        return redirect()->route('admin.keuangan.buku-besar')
            ->with('galat_impor', $galat)
            ->with('error', 'Impor dibatalkan — tidak ada data yang disimpan. Perbaiki ' . count($galat) . ' masalah di bawah lalu unggah ulang.');
    }
}
