<?php

namespace App\Http\Controllers\Admin;

use App\Exports\InvoicesExport;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Invoice untuk admin: OVERVIEW saja.
 *
 * Admin tidak membuat invoice — yang membuat adalah staff di meja depan,
 * saat tamu ada di hadapannya. Yang dibutuhkan admin adalah gambaran
 * menyeluruh: berapa yang terbit bulan ini, berapa nilainya, dan mana yang
 * belum lunas. Karena itu halaman ini hanya menampilkan ringkasan, daftar,
 * dan tautan cetak — tanpa tombol buat atau ubah.
 */
class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $bulan = $this->bulanDari($request->query('bulan'));

        $invoices = Invoice::with(['creator', 'items'])
            ->whereBetween('created_at', [
                $bulan->copy()->startOfMonth(),
                $bulan->copy()->endOfMonth()->endOfDay(),
            ])
            ->latest()
            ->get();

        return view('admin.invoices.index', [
            'bulan'     => $bulan,
            'invoices'  => $invoices,
            'prevBulan' => $bulan->copy()->subMonth()->format('Y-m'),
            'nextBulan' => $bulan->copy()->addMonth()->format('Y-m'),
            'ringkas'   => [
                'jumlah'      => $invoices->count(),
                'nilai'       => (int) $invoices->sum('total'),
                'lunas'       => $invoices->where('lunas', true)->count(),
                'nilaiLunas'  => (int) $invoices->where('lunas', true)->sum('total'),
                'belum'       => $invoices->where('lunas', false)->count(),
                'nilaiBelum'  => (int) $invoices->where('lunas', false)->sum('total'),
            ],
        ]);
    }

    /** Tampilan cetak — sama persis dengan milik staff. */
    public function show(Invoice $invoice)
    {
        $invoice->load('items');

        return view('invoices.print', compact('invoice'));
    }

    public function export(Request $request)
    {
        $bulan = $this->bulanDari($request->query('bulan'));

        return Excel::download(
            new InvoicesExport($bulan),
            'invoice-' . $bulan->format('Y-m') . '.xlsx'
        );
    }

    // ------------------------------------------------------------------

    private function bulanDari(?string $nilai): Carbon
    {
        return (is_string($nilai) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $nilai))
            ? Carbon::createFromFormat('Y-m-d', $nilai . '-01')->startOfDay()
            : now()->startOfMonth()->startOfDay();
    }
}
