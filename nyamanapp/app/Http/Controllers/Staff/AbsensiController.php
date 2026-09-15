<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Jadwal;
use App\Support\FotoKompresor;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Absensi harian karyawan dengan DUA bukti foto: selfie dan laci kartu kunci.
 *
 * Status ditentukan otomatis: absen setelah jam mulai shift = telat.
 * Perbandingan memakai waktu SERVER, bukan waktu perangkat, supaya jam
 * ponsel yang dimundurkan tidak bisa dipakai menghindari status telat.
 *
 * Foto kartu adalah inti pengendalian kunci fisik. Setiap pergantian shift
 * meninggalkan satu foto bertanggal berisi kartu yang tersisa di laci, dan
 * jumlah itu harus bisa dijelaskan oleh data booking: kartu yang tidak ada
 * di laci tetapi kamarnya tercatat kosong adalah selisih yang perlu dijawab.
 *
 * Keduanya wajib — absen tidak bisa disimpan hanya dengan selfie, supaya
 * hitungan kartu tidak pelan-pelan ditinggalkan saat sedang sibuk.
 */
class AbsensiController extends Controller
{
    /**
     * Daftar shift pada satu tanggal beserta status absensinya.
     */
    public function index(Request $request)
    {
        $tanggal = $this->tanggalDari($request->query('tanggal'), now());

        return view('staff.absensi.index', [
            'tanggal' => $tanggal,
            'shift'   => $this->shiftHari($tanggal),
        ]);
    }

    /**
     * Halaman ambil foto untuk satu shift.
     */
    public function create(Jadwal $jadwal)
    {
        $jadwal->load(['karyawan', 'absensi']);

        return view('staff.absensi.form', [
            'jadwal'  => $jadwal,
            'absensi' => $jadwal->absensi,
            'telat'   => $jadwal->menitTelat(now()),
        ]);
    }

    /**
     * Simpan absensi: foto dimampatkan, status dihitung dari waktu server.
     */
    public function store(Request $request, Jadwal $jadwal): RedirectResponse
    {
        // Yang menahan absen ganda di basis data adalah indeks unik
        // (karyawan_id, tanggal) — BUKAN jadwal_id. Memeriksa lewat relasi
        // jadwal saja meloloskan satu kasus yang sering terjadi: jadwal hari
        // itu disusun ulang, absensi lama kehilangan jadwal_id-nya, lalu
        // orang yang sama absen sekali lagi dan basis data menolaknya dengan
        // galat 500 di layar staff.
        //
        // Karena itu pemeriksaannya memakai kunci yang sama dengan indeksnya.
        $sudahAda = Absensi::where('karyawan_id', $jadwal->karyawan_id)
            ->whereDate('tanggal', $jadwal->tanggal->toDateString())
            ->first();

        if ($sudahAda !== null) {
            // Absensi yatim (jadwalnya sempat dihapus) ditautkan ulang, bukan
            // ditolak begitu saja — kehadirannya memang sudah tercatat.
            if ($sudahAda->jadwal_id === null) {
                $sudahAda->forceFill(['jadwal_id' => $jadwal->id])->save();
            }

            return redirect()
                ->route('staff.absensi.index', ['tanggal' => $jadwal->tanggal->toDateString()])
                ->withErrors(['foto' => $jadwal->karyawan?->nama . ' sudah absen pada '
                    . $sudahAda->waktu_absen->format('H:i') . ' hari itu.']);
        }

        $request->validate([
            'foto'       => 'required|image|mimes:jpg,jpeg,png|max:8192',
            'foto_kartu' => 'required|image|mimes:jpg,jpeg,png|max:8192',
        ], [
            'foto.required'       => 'Foto selfie wajib diambil sebagai bukti kehadiran.',
            'foto.image'          => 'Berkas selfie harus berupa gambar.',
            'foto.mimes'          => 'Foto selfie harus berformat JPG atau PNG.',
            'foto.max'            => 'Ukuran foto selfie maksimal 8 MB.',
            'foto_kartu.required' => 'Foto kartu kunci wajib diambil — potret laci kartu apa adanya saat serah terima shift.',
            'foto_kartu.image'    => 'Berkas foto kartu harus berupa gambar.',
            'foto_kartu.mimes'    => 'Foto kartu harus berformat JPG atau PNG.',
            'foto_kartu.max'      => 'Ukuran foto kartu maksimal 8 MB.',
        ]);

        $sekarang = now();
        $telat    = $jadwal->menitTelat($sekarang);

        // Penjaga terakhir. Dua orang yang menekan simpan pada detik yang sama
        // tetap bisa lolos pemeriksaan di atas; yang kalah harus melihat pesan
        // yang bisa dibaca, bukan halaman galat.
        try {
            $absensi = Absensi::create([
                'karyawan_id' => $jadwal->karyawan_id,
                'jadwal_id'   => $jadwal->id,
                'tanggal'     => $jadwal->tanggal,
                'waktu_absen' => $sekarang,
                'status'      => $telat > 0 ? Absensi::TELAT : Absensi::HADIR,
                'menit_telat' => $telat,
                'foto'        => $this->simpanFoto($request->file('foto')),
                'foto_kartu'  => $this->simpanFoto($request->file('foto_kartu')),
                'created_by'  => Auth::id(),
            ]);
        } catch (QueryException $e) {
            return redirect()
                ->route('staff.absensi.index', ['tanggal' => $jadwal->tanggal->toDateString()])
                ->withErrors(['foto' => $jadwal->karyawan?->nama
                    . ' sudah tercatat absen hari itu. Muat ulang halaman untuk melihat datanya.']);
        }

        return redirect()
            ->route('staff.absensi.index', ['tanggal' => $jadwal->tanggal->toDateString()])
            ->with('success', $jadwal->karyawan?->nama . ' absen pukul '
                . $sekarang->format('H:i') . ' — ' . ($absensi->telat() ? $absensi->labelTelat() : 'tepat waktu') . '.');
    }

    // ------------------------------------------------------------------

    /**
     * Simpan satu unggahan ke disk PRIVAT setelah dimampatkan.
     * Bila GD tidak tersedia di server, berkas asli dipakai apa adanya.
     */
    private function simpanFoto(\Illuminate\Http\UploadedFile $berkas): string
    {
        $biner = FotoKompresor::kompres($berkas);

        if ($biner === null) {
            return $berkas->store('absensi', 'local');
        }

        $path = 'absensi/' . Str::random(40) . '.jpg';
        Storage::disk('local')->put($path, $biner);

        return $path;
    }

    private function tanggalDari(?string $nilai, Carbon $bawaan): Carbon
    {
        return (is_string($nilai) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) && strtotime($nilai))
            ? Carbon::createFromFormat('Y-m-d', $nilai)->startOfDay()
            : $bawaan->copy()->startOfDay();
    }

    /**
     * Shift pada tanggal tsb, terurut jam mulai, lengkap dgn absensinya.
     */
    private function shiftHari(Carbon $tanggal)
    {
        return Jadwal::with(['karyawan', 'absensi'])
            ->tanggal($tanggal)
            ->get()
            ->sortBy('jam_mulai')
            ->values();
    }
}
