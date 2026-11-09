<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Baca & tulis CSV untuk fitur Keuangan (saldo harian dan keuntungan bulanan).
 *
 * Data lama biasanya datang dari Excel atau Google Sheets dengan bentuk yang
 * berbeda-beda, jadi pembacaannya sengaja longgar:
 *   - pemisah koma atau titik koma (Excel berbahasa Indonesia memakai ;)
 *   - baris judul boleh ada boleh tidak; bila ada, kolom dikenali dari namanya
 *   - tanggal 2026-04-05, 05/04/2026, 5-4-2026 (hari dulu, gaya Indonesia), 01-Apr-26
 *   - bulan 2026-04, 04/2026, "April 2026", "Apr 2026"
 *   - angka 25000000, 25.000.000, "Rp 25.000.000", 25,000,000
 */
final class CsvKeuangan
{
    /** Batas baris sekali impor — cukup untuk belasan tahun saldo harian. */
    public const MAKS_BARIS = 6000;

    private const BULAN = [
        'jan' => 1, 'januari' => 1, 'january' => 1,
        'feb' => 2, 'februari' => 2, 'february' => 2, 'peb' => 2, 'pebruari' => 2,
        'mar' => 3, 'maret' => 3, 'march' => 3,
        'apr' => 4, 'april' => 4,
        'mei' => 5, 'may' => 5,
        'jun' => 6, 'juni' => 6, 'june' => 6,
        'jul' => 7, 'juli' => 7, 'july' => 7,
        'agu' => 8, 'agt' => 8, 'agus' => 8, 'agust' => 8, 'agustus' => 8, 'aug' => 8, 'august' => 8,
        'sep' => 9, 'sept' => 9, 'september' => 9,
        'okt' => 10, 'oktober' => 10, 'oct' => 10, 'october' => 10,
        'nov' => 11, 'nop' => 11, 'november' => 11, 'nopember' => 11,
        'des' => 12, 'desember' => 12, 'dec' => 12, 'december' => 12,
    ];

    /**
     * Baca berkas menjadi baris-baris sel. Baris kosong dilewati; nomor baris
     * asli ikut disimpan supaya pesan galat menunjuk baris yang benar.
     *
     * @return list<array{no: int, sel: list<string>}>
     */
    public static function baca(UploadedFile $berkas): array
    {
        $isi = (string) file_get_contents($berkas->getRealPath());
        $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi);           // BOM UTF-8 dari Excel
        $isi = str_replace(["\r\n", "\r"], "\n", $isi);

        $baris = explode("\n", $isi);
        $contoh = '';
        foreach ($baris as $b) {
            if (trim($b) !== '' && ! preg_match('/^sep=/i', trim($b))) { $contoh = $b; break; }
        }
        $pemisah = substr_count($contoh, ';') > substr_count($contoh, ',') ? ';' : ',';

        $hasil = [];
        foreach ($baris as $i => $b) {
            if (trim($b) === '' || preg_match('/^sep=/i', trim($b))) {
                continue;
            }
            $sel = array_map(fn ($s) => trim((string) $s), str_getcsv($b, $pemisah, '"', ''));
            if (implode('', $sel) === '') {
                continue;
            }
            $hasil[] = ['no' => $i + 1, 'sel' => $sel];
        }

        return $hasil;
    }

    /**
     * Petakan kolom dari baris judul (bila ada).
     *
     * @param  array<string, list<string>>  $kenal  nama kunci => sinonim judul kolom
     * @param  list<string>  $urutan  urutan kolom bila tidak ada baris judul
     * @return array{peta: array<string, int>, adaJudul: bool, dariJudul: list<string>}
     */
    public static function petaKolom(array $selPertama, array $kenal, array $urutan): array
    {
        $peta = [];
        foreach ($selPertama as $i => $judul) {
            $j = strtolower(preg_replace('/[^a-z]/i', '', $judul));
            if ($j === '') {
                continue;
            }
            foreach ($kenal as $kunci => $sinonim) {
                if (isset($peta[$kunci]) || in_array($i, $peta, true)) {
                    continue;
                }
                foreach ($sinonim as $s) {
                    // Salah ketik satu huruf ("Tanngal") tetap dikenali untuk nama panjang.
                    if ($j === $s || (strlen($s) >= 5 && levenshtein($j, $s) <= 1)) {
                        $peta[$kunci] = $i;
                        continue 2;
                    }
                }
            }
        }

        $adaJudul = $peta !== [];
        $dariJudul = array_keys($peta);

        // Kolom yang tidak dikenali dari judulnya diambil menurut urutan bakunya,
        // asalkan posisi itu belum dipakai kolom lain.
        foreach ($urutan as $i => $kunci) {
            if (! isset($peta[$kunci]) && ! in_array($i, $peta, true)) {
                $peta[$kunci] = $i;
            }
        }

        return ['peta' => $peta, 'adaJudul' => $adaJudul, 'dariJudul' => $dariJudul];
    }

    /** Isi sel untuk kolom $kunci; string kosong bila kolomnya tidak ada. */
    public static function sel(array $sel, array $peta, string $kunci): string
    {
        return isset($peta[$kunci]) ? (string) ($sel[$peta[$kunci]] ?? '') : '';
    }

    /**
     * Urutan tanggal berangka di berkas: 'dmy' (1/4/2026 = 1 April, gaya
     * Indonesia) atau 'mdy' (4/1/2026 = 1 April, gaya Amerika dari sebagian
     * ekspor Google Sheets). Ditentukan dari SELURUH berkas: bila ada bagian
     * pertama di atas 12, pasti hari dulu; bila bagian kedua di atas 12, pasti
     * bulan dulu. Bila tidak bisa dipastikan, dipakai gaya Indonesia.
     *
     * @param  iterable<string>  $nilai
     */
    public static function urutanTanggal(iterable $nilai): string
    {
        $mdy = false;
        foreach ($nilai as $v) {
            if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.]\d{4}/', trim((string) $v), $m)) {
                if ((int) $m[1] > 12) {
                    return 'dmy';
                }
                if ((int) $m[2] > 12) {
                    $mdy = true;
                }
            }
        }

        return $mdy ? 'mdy' : 'dmy';
    }

    /**
     * Saldo yang jauh menyimpang dari hari-hari di sekitarnya — biasanya salah
     * ketik satu digit (556 juta di antara 55–61 juta). Hanya peringatan;
     * datanya tetap disimpan apa adanya.
     *
     * @param  array<string, int>  $deret  tanggal (Y-m-d) => saldo, urut tanggal
     * @return list<array{tanggal: string, saldo: int, sekitar: int}>
     */
    public static function janggal(array $deret, int $jendela = 5, float $rasio = 3.0): array
    {
        $tgl = array_keys($deret);
        $val = array_values($deret);
        $hasil = [];

        foreach ($val as $i => $v) {
            $tetangga = array_merge(
                array_slice($val, max(0, $i - $jendela), min($jendela, $i)),
                array_slice($val, $i + 1, $jendela)
            );
            if (count($tetangga) < 4) {
                continue;
            }
            sort($tetangga);
            $n = count($tetangga);
            $median = $n % 2 ? $tetangga[intdiv($n, 2)] : intdiv($tetangga[$n / 2 - 1] + $tetangga[$n / 2], 2);
            if ($median <= 0) {
                continue;
            }
            if ($v >= $median * $rasio || $v * $rasio <= $median) {
                $hasil[] = ['tanggal' => $tgl[$i], 'saldo' => $v, 'sekitar' => $median];
            }
        }

        return $hasil;
    }

    /** Rupiah dari teks; null bila bukan angka. Desimal sen dibuang. */
    public static function angka(?string $v): ?int
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^\d+(\.\d+)?e\+?\d+$/i', $v)) {          // 2.5E+07 dari Excel
            return (int) round((float) $v);
        }
        if (str_contains($v, '-')) {
            return null;                                              // negatif tidak berlaku di sini
        }
        $s = preg_replace('/[^\d.,]/', '', $v);
        if ($s === '' || ! preg_match('/\d/', $s)) {
            return null;
        }
        $s = preg_replace('/[.,]\d{1,2}$/', '', $s);                  // ,00 atau .50 di belakang
        $s = str_replace(['.', ','], '', $s);

        return $s === '' ? null : (int) $s;
    }

    /** Tanggal dari teks; null bila tidak dikenali. $urutan: 'dmy' atau 'mdy'. */
    public static function tanggal(?string $v, string $urutan = 'dmy'): ?Carbon
    {
        $v = trim(preg_replace('/[ T]\d{1,2}:\d{2}.*$/', '', trim((string) $v)));

        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $v, $m)) {
            [$y, $b, $h] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $v, $m)) {
            [$h, $b, $y] = $urutan === 'mdy'
                ? [(int) $m[2], (int) $m[1], (int) $m[3]]
                : [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\s-]+([a-z]+)\.?[\s-]+(\d{4}|\d{2})$/i', $v, $m) && isset(self::BULAN[strtolower($m[2])])) {
            // "5 Apr 2026", "01-Apr-26", "01-Agust-26" (format tanggal Excel Indonesia)
            $y = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
            [$h, $b] = [(int) $m[1], self::BULAN[strtolower($m[2])]];
        } else {
            return null;
        }

        return checkdate($b, $h, $y) ? Carbon::create($y, $b, $h)->startOfDay() : null;
    }

    /** Bulan (tanggal 1) dari teks; null bila tidak dikenali. */
    public static function bulan(?string $v): ?Carbon
    {
        $v = trim((string) $v);

        if (preg_match('/^(\d{4})[-\/.](\d{1,2})$/', $v, $m)) {
            [$y, $b] = [(int) $m[1], (int) $m[2]];
        } elseif (preg_match('/^(\d{1,2})[-\/.](\d{4})$/', $v, $m)) {
            [$b, $y] = [(int) $m[1], (int) $m[2]];
        } elseif (preg_match('/^([a-z]+)\.?\s*[-\/ ]\s*(\d{4})$/i', $v, $m) && isset(self::BULAN[strtolower($m[1])])) {
            [$b, $y] = [self::BULAN[strtolower($m[1])], (int) $m[2]];
        } elseif ($t = self::tanggal($v)) {
            return $t->copy()->startOfMonth();
        } else {
            return null;
        }

        return ($b >= 1 && $b <= 12) ? Carbon::create($y, $b, 1)->startOfDay() : null;
    }

    public static function rupiah(int $n): string
    {
        return ($n < 0 ? '-Rp ' : 'Rp ') . number_format(abs($n), 0, ',', '.');
    }

    /**
     * Unduhan CSV (koma, UTF-8 dengan BOM supaya Excel membaca huruf dengan benar).
     *
     * @param  list<string>  $judul
     * @param  iterable<list<string|int|null>>  $baris
     */
    public static function unduh(string $nama, array $judul, iterable $baris): StreamedResponse
    {
        return response()->streamDownload(function () use ($judul, $baris) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $judul, ',', '"', '');
            foreach ($baris as $b) {
                fputcsv($out, $b, ',', '"', '');
            }
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
