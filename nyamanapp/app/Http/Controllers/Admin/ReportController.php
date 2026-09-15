<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReportsExport;
use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Semua tiket dari seluruh staff, terbaru dulu.
     * Filter tab: Semua / Open / Selesai (?status=open|selesai).
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $tab = in_array($status, ['open', 'selesai'], true) ? $status : 'semua';

        $reports = Report::with('user')
            ->when($tab !== 'semua', fn ($q) => $q->where('status', $tab))
            ->latest()
            ->get();

        $counts = [
            'semua'   => Report::count(),
            'open'    => Report::where('status', 'open')->count(),
            'selesai' => Report::where('status', 'selesai')->count(),
        ];

        return view('admin.reports.index', compact('reports', 'tab', 'counts'));
    }

    /**
     * Unduh tiket sebagai Excel, mengikuti tab filter yang sedang aktif.
     */
    public function export(Request $request)
    {
        $status = $request->query('status');
        $filter = in_array($status, ['open', 'selesai'], true) ? $status : '';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new ReportsExport($filter),
            'laporan-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    /**
     * Ubah status tiket: "Tandai Selesai" <-> "Buka Kembali".
     */
    public function updateStatus(Report $report): RedirectResponse
    {
        $report->update([
            'status' => $report->status === 'open' ? 'selesai' : 'open',
        ]);

        $pesan = $report->status === 'selesai'
            ? 'Tiket "' . $report->judul . '" ditandai selesai.'
            : 'Tiket "' . $report->judul . '" dibuka kembali.';

        return back()->with('success', $pesan);
    }
}
