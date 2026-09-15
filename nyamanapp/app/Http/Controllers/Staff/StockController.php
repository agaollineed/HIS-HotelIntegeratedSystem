<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockController extends Controller
{
    /**
     * Form staff: pilih stok, tambah/kurangi jumlah, simpan.
     * Sekaligus menampilkan tabel seluruh stok terkini (read-only).
     */
    public function index()
    {
        $stocks = Stock::orderBy('nama')->get();

        return view('staff.stocks.index', [
            'stocks' => $stocks,
            // Halaman ini dipakai staff DAN admin. Alamat simpannya berbeda
            // tetapi formulir dan controller-nya satu — tidak ada salinan
            // kedua yang bisa menyimpang aturannya.
            'aksiSimpan' => $this->ruteSimpan(),
        ]);
    }

    /** Alamat POST sesuai panel yang sedang dibuka. */
    private function ruteSimpan(): string
    {
        return Auth::user()->isAdmin()
            ? route('admin.stocks.jumlah.store')
            : route('staff.stocks.store');
    }

    /** Halaman tujuan setelah simpan. */
    private function ruteKembali(): string
    {
        return Auth::user()->isAdmin()
            ? route('admin.stocks.jumlah')
            : route('staff.stocks.index');
    }

    /**
     * Terapkan perubahan stok dari staff.
     * mode "add"      -> jumlah = jumlah lama + input
     * mode "subtract" -> jumlah = jumlah lama - input (minimum 0)
     * Kolom `jumlah` + `last update` di panel admin ikut berubah otomatis.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.stock_id' => 'required|exists:stocks,id',
            'items.*.mode'     => 'required|in:add,subtract',
            'items.*.jumlah'   => 'required|integer|min:1',
        ], [
            'items.required'            => 'Tambahkan minimal satu item.',
            'items.*.stock_id.required' => 'Pilih nama stok terlebih dahulu.',
            'items.*.stock_id.exists'   => 'Nama stok tidak dikenal. Pilih dari daftar.',
            'items.*.mode.required'     => 'Pilih aksi tambah atau kurangi.',
            'items.*.mode.in'           => 'Aksi perubahan tidak valid.',
            'items.*.jumlah.required'   => 'Isi jumlah perubahan.',
            'items.*.jumlah.min'        => 'Jumlah perubahan minimal 1.',
        ]);

        $by = Auth::user()->name . ' (' . Auth::user()->role . ')';
        $count = 0;

        foreach ($data['items'] as $item) {
            $stock = Stock::find($item['stock_id']);
            if (! $stock) {
                continue;
            }

            $amount = (int) $item['jumlah'];
            $before = $stock->jumlah;

            if ($item['mode'] === 'add') {
                $stock->jumlah = $before + $amount;
            } else {
                // Kurangi, tidak boleh di bawah 0.
                $stock->jumlah = max(0, $before - $amount);
            }

            $stock->updated_by = $by;
            $stock->save(); // otomatis memperbarui updated_at (last update)

            // Catat ke log (perubahan nyata bisa berbeda bila dibatasi 0).
            $after   = $stock->jumlah;
            $selisih = $after - $before;
            $aksi    = $item['mode'] === 'add' ? 'Menambah' : 'Mengurangi';
            StockLog::rekam(
                $stock,
                $item['mode'] === 'add' ? 'increment' : 'decrement',
                $aksi . ' stok: ' . $before . ' → ' . $after . ' (' . sprintf('%+d', $selisih) . ')',
                $selisih
            );

            $count++;
        }

        return redirect()
            ->to($this->ruteKembali())
            ->with('success', $count . ' perubahan stok berhasil disimpan.');
    }
}
