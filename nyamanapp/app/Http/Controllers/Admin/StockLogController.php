<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StockLogsExport;
use App\Http\Controllers\Controller;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class StockLogController extends Controller
{
    /**
     * Halaman Log — riwayat perubahan stok (khusus admin).
     * Bisa disaring berdasarkan rentang tanggal (?from=&to=).
     */
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);

        $logs = StockLog::query()
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->take(300)
            ->get();

        return view('log.index', compact('logs', 'from', 'to'));
    }

    /**
     * Unduh log sebagai Excel (.xlsx) untuk rentang tanggal tertentu.
     */
    public function export(Request $request)
    {
        [$from, $to] = $this->range($request);

        $suffix = ($from ?? 'awal') . '_sd_' . ($to ?? 'akhir');

        return Excel::download(
            new StockLogsExport($from, $to),
            'log-stok-' . $suffix . '.xlsx'
        );
    }

    /**
     * Ambil & normalkan rentang tanggal dari request menjadi format Y-m-d.
     * Nilai yang tidak valid diabaikan (null).
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function range(Request $request): array
    {
        return [
            $this->normalize($request->query('from')),
            $this->normalize($request->query('to')),
        ];
    }

    private function normalize(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        $ts = strtotime($date);

        return $ts ? date('Y-m-d', $ts) : null;
    }
}
