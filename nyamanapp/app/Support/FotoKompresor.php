<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Pemampat foto untuk semua unggahan (KTP tamu & selfie absensi).
 *
 * Foto dari kamera ponsel biasanya 3–8 MB (4000×3000 piksel) — jauh melebihi
 * kebutuhan untuk membaca sebuah KTP atau mengenali wajah, dan cepat
 * membengkakkan penyimpanan serta memperlambat halaman. Kelas ini menormalkan
 * setiap unggahan menjadi JPEG dengan sisi terpanjang maksimal 1600 px.
 *
 * Efek samping yang disengaja: proses encode ulang MENGHAPUS metadata EXIF,
 * termasuk koordinat GPS yang sering disisipkan kamera ponsel — data lokasi
 * atau karyawan tidak ada urusannya dengan berkas ini.
 *
 * Bila ekstensi GD tidak tersedia di server, berkas asli dipakai apa adanya
 * (fungsionalitas tetap jalan, hanya tanpa penghematan).
 */
class FotoKompresor
{
    /** Sisi terpanjang hasil akhir (piksel). Cukup untuk membaca NIK / mengenali wajah. */
    public const MAKS_SISI = 1600;

    /** Mutu JPEG 0–100. 82 = tetap tajam, ukuran jauh lebih kecil. */
    public const MUTU = 82;

    /**
     * Kembalikan biner JPEG hasil pemampatan, atau null bila tidak bisa
     * diproses (pemanggil harus memakai berkas asli sebagai cadangan).
     */
    public static function kompres(UploadedFile $file): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            return null;
        }

        $info = @getimagesize($path);

        if ($info === false) {
            return null;
        }

        [$lebar, $tinggi] = $info;
        $tipe = $info[2];

        $sumber = match ($tipe) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            default        => null,
        };

        if (! $sumber) {
            return null;
        }

        // Kamera ponsel menyimpan orientasi di EXIF, bukan pada piksel;
        // tanpa koreksi ini foto potret bisa tampil miring setelah diproses.
        if ($tipe === IMAGETYPE_JPEG) {
            $sumber = self::koreksiOrientasi($sumber, $path);
            $lebar  = imagesx($sumber);
            $tinggi = imagesy($sumber);
        }

        $skala = min(1, self::MAKS_SISI / max($lebar, $tinggi));
        $lebarBaru  = max(1, (int) round($lebar * $skala));
        $tinggiBaru = max(1, (int) round($tinggi * $skala));

        $tujuan = imagecreatetruecolor($lebarBaru, $tinggiBaru);

        // PNG bisa transparan; JPEG tidak. Ratakan ke putih agar tidak hitam.
        $putih = imagecolorallocate($tujuan, 255, 255, 255);
        imagefilledrectangle($tujuan, 0, 0, $lebarBaru, $tinggiBaru, $putih);
        imagecopyresampled($tujuan, $sumber, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebar, $tinggi);

        ob_start();
        $berhasil = imagejpeg($tujuan, null, self::MUTU);
        $biner = ob_get_clean();

        imagedestroy($sumber);
        imagedestroy($tujuan);

        return ($berhasil && $biner !== '' && $biner !== false) ? $biner : null;
    }

    /**
     * Putar gambar sesuai tag Orientation di EXIF.
     *
     * @param  \GdImage  $gambar
     * @return \GdImage
     */
    private static function koreksiOrientasi($gambar, string $path)
    {
        if (! function_exists('exif_read_data')) {
            return $gambar;
        }

        $exif = @exif_read_data($path);

        if ($exif === false || ! isset($exif['Orientation'])) {
            return $gambar;
        }

        $derajat = match ((int) $exif['Orientation']) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($derajat === 0) {
            return $gambar;
        }

        $diputar = imagerotate($gambar, $derajat, 0);

        if ($diputar === false) {
            return $gambar;
        }

        imagedestroy($gambar);

        return $diputar;
    }
}
