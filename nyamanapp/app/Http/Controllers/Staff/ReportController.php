<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Support\FotoKompresor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /**
     * Form kirim tiket + daftar tiket milik staff sendiri (terbaru dulu).
     */
    public function index()
    {
        $reports = Report::where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('staff.reports.index', compact('reports'));
    }

    /**
     * Simpan tiket laporan baru untuk admin.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'judul' => 'required|string|max:255',
            'isi'   => 'required|string|max:5000',
            'foto'  => 'nullable|image|mimes:jpg,jpeg,png|max:8192',
        ], [
            'judul.required' => 'Judul laporan wajib diisi.',
            'isi.required'   => 'Isi laporan wajib diisi.',
            'isi.max'        => 'Isi laporan maksimal 5000 karakter.',
            'foto.image'     => 'Lampiran harus berupa gambar.',
            'foto.mimes'     => 'Lampiran harus berformat JPG atau PNG.',
            'foto.max'       => 'Ukuran lampiran maksimal 8 MB.',
        ]);

        $path = null;
        if ($request->hasFile('foto')) {
            $berkas = $request->file('foto');
            $biner  = FotoKompresor::kompres($berkas);

            if ($biner !== null) {
                $path = 'laporan/' . Str::random(40) . '.jpg';
                Storage::disk('local')->put($path, $biner);
            } else {
                $path = $berkas->store('laporan', 'local');
            }
        }

        Report::create([
            'user_id' => Auth::id(),
            'judul'   => $data['judul'],
            'isi'     => $data['isi'],
            'foto'    => $path,
            'status'  => 'open',
        ]);

        return redirect()
            ->route('staff.reports.index')
            ->with('success', 'Laporan "' . $data['judul'] . '" berhasil dikirim ke admin.');
    }
}
