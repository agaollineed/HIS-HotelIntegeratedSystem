<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StocksExport;
use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class StockController extends Controller
{
    /**
     * Tabel stok (READ) — kolom: nama, jumlah, harga, last update.
     * Semua baris dirender; penyaringan dilakukan langsung di browser
     * (live filter) sehingga baris yang tidak cocok otomatis tersembunyi.
     */
    public function index()
    {
        $stocks = Stock::orderBy('nama')->get();

        return view('admin.stocks.index', compact('stocks'));
    }

    /**
     * Unduh seluruh tabel stok sebagai file Excel (.xlsx).
     * Mengikuti filter pencarian bila parameter ?q= diberikan.
     */
    public function export(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        return Excel::download(
            new StocksExport($q),
            'stok-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    /**
     * Form tambah stok.
     */
    public function create()
    {
        return view('admin.stocks.create');
    }

    /**
     * Simpan stok baru (CREATE).
     */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['updated_by'] = Auth::user()->name . ' (' . Auth::user()->role . ')';

        // Admin membuat ITEM-nya, bukan isinya. Jumlah awal selalu nol dan
        // bergerak lewat formulir tambah/kurangi yang meninggalkan selisih di
        // log stok — satu-satunya jalur yang bisa diaudit.
        if (! Auth::user()->bolehSetJumlahStok()) {
            $data['jumlah'] = 0;
        }

        $stock = Stock::create($data);

        StockLog::rekam(
            $stock,
            'create',
            'Item baru dibuat — jumlah ' . $stock->jumlah . ', harga Rp' . number_format($stock->harga, 0, ',', '.'),
            $stock->jumlah > 0 ? $stock->jumlah : null
        );

        return redirect()
            ->route('admin.stocks.index')
            ->with('success', 'Stok "' . $data['nama'] . '" berhasil ditambahkan.');
    }

    /**
     * Form ubah stok.
     */
    public function edit(Stock $stock)
    {
        return view('admin.stocks.edit', compact('stock'));
    }

    /**
     * Simpan perubahan stok (UPDATE).
     */
    public function update(Request $request, Stock $stock)
    {
        $data = $this->validated($request);

        // Jumlah tidak ikut berubah lewat formulir ini kecuali superadmin.
        if (! Auth::user()->bolehSetJumlahStok()) {
            unset($data['jumlah']);
        }

        $data['updated_by'] = Auth::user()->name . ' (' . Auth::user()->role . ')';

        // Simpan nilai lama untuk dibandingkan.
        $before = [
            'nama'   => $stock->nama,
            'jumlah' => $stock->jumlah,
            'harga'  => $stock->harga,
        ];

        $stock->update($data);

        // Susun detail perubahan per kolom.
        $perubahan = [];
        $selisih   = null;

        if ($before['nama'] !== $stock->nama) {
            $perubahan[] = 'Nama: "' . $before['nama'] . '" → "' . $stock->nama . '"';
        }
        if ($before['jumlah'] !== $stock->jumlah) {
            $selisih = $stock->jumlah - $before['jumlah'];
            $perubahan[] = 'Jumlah: ' . $before['jumlah'] . ' → ' . $stock->jumlah . ' (' . sprintf('%+d', $selisih) . ')';
        }
        if ($before['harga'] !== $stock->harga) {
            $perubahan[] = 'Harga: Rp' . number_format($before['harga'], 0, ',', '.') . ' → Rp' . number_format($stock->harga, 0, ',', '.');
        }

        if (! empty($perubahan)) {
            StockLog::rekam($stock, 'update', implode(' · ', $perubahan), $selisih);
        }

        return redirect()
            ->route('admin.stocks.index')
            ->with('success', 'Stok "' . $stock->nama . '" berhasil diperbarui.');
    }

    /**
     * Hapus stok (DELETE).
     */
    public function destroy(Stock $stock)
    {
        $nama = $stock->nama;

        StockLog::rekam(
            $stock,
            'delete',
            'Item dihapus — jumlah terakhir ' . $stock->jumlah . ', harga Rp' . number_format($stock->harga, 0, ',', '.'),
            null
        );

        $stock->delete();

        return redirect()
            ->route('admin.stocks.index')
            ->with('success', 'Stok "' . $nama . '" berhasil dihapus.');
    }

    /**
     * Aturan validasi bersama untuk store & update.
     */
    private function validated(Request $request): array
    {
        // Kolom jumlah hanya ada di formulir superadmin, jadi ia tidak boleh
        // WAJIB untuk peran lain — kalau tidak, admin tidak akan pernah bisa
        // menyimpan formulirnya.
        $bolehJumlah = Auth::user()->bolehSetJumlahStok();

        return $request->validate([
            'nama'   => 'required|string|max:255',
            'jumlah' => ($bolehJumlah ? 'required' : 'nullable') . '|integer|min:0',
            'harga'  => 'required|integer|min:0',
        ], [
            'nama.required'   => 'Nama stok wajib diisi.',
            'jumlah.required' => 'Jumlah wajib diisi.',
            'jumlah.min'      => 'Jumlah tidak boleh negatif.',
            'harga.required'  => 'Harga wajib diisi.',
            'harga.min'       => 'Harga tidak boleh negatif.',
        ]);
    }
}
