<?php

use App\Http\Controllers\Admin\AbsensiController as AdminAbsensiController;
use App\Http\Controllers\Admin\AkunController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\PurgeController;
use App\Http\Controllers\Admin\TamuController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\KeuanganController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\RevenueController;
use App\Http\Controllers\Admin\PerubahanController as AdminPerubahanController;
use App\Http\Controllers\Admin\RoomController as AdminRoomController;
use App\Http\Controllers\Admin\StockController as AdminStockController;
use App\Http\Controllers\Admin\StockLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KtpController;
use App\Http\Controllers\Staff\AbsensiController;
use App\Http\Controllers\Staff\InvoiceController;
use App\Http\Controllers\Staff\JadwalController;
use App\Http\Controllers\Staff\BookingController;
use App\Http\Controllers\Staff\PerubahanController as StaffPerubahanController;
use App\Http\Controllers\Staff\ReportController as StaffReportController;
use App\Http\Controllers\Staff\StockController as StaffStockController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Landing page
|--------------------------------------------------------------------------
| Kalau sudah login, langsung diarahkan ke dashboard.
*/
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome');
})->name('welcome');

Route::middleware('auth')->group(function () {

    // Dashboard sungguhan: kartu fitur sesuai role (bukan redirect).
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Foto KTP (data pribadi) — disajikan dari disk PRIVAT, wajib login.
    Route::get('/ktp/{booking}', [KtpController::class, 'show'])->name('ktp.show');
    // Foto kartu kunci: bukti serah terima (check-out) & hitungan laci (absensi).
    Route::get('/kartu/{booking}', [KtpController::class, 'kartu'])->name('kartu.show');
    Route::get('/bayar/{booking}', [KtpController::class, 'pelunasan'])->name('bayar.show');
    Route::get('/bayar-awal/{booking}', [KtpController::class, 'bayarAwal'])->name('bayar.awal');
    Route::get('/bayar-late/{booking}', [KtpController::class, 'pembayaranLate'])->name('bayar.late');
    Route::get('/kerusakan/{booking}', [KtpController::class, 'kerusakan'])->name('kerusakan.show');
    Route::get('/laporan/{report}/foto', [KtpController::class, 'laporan'])->name('laporan.foto');
    Route::get('/absensi/{absensi}/foto', [KtpController::class, 'absensi'])->name('absensi.foto');
    Route::get('/absensi/{absensi}/kartu', [KtpController::class, 'absensiKartu'])->name('absensi.kartu');

    // Halaman Log stok (riwayat perubahan) — KHUSUS admin.
    Route::middleware('role:admin')->group(function () {
        Route::get('/log', [StockLogController::class, 'index'])->name('log');
        Route::get('/log/export', [StockLogController::class, 'export'])->name('log.export');
    });

    // ----- ADMIN -----
    Route::middleware('role:admin')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            // Stok (CRUD penuh + export Excel)
            Route::get('stocks/export', [AdminStockController::class, 'export'])->name('stocks.export');

            // Tambah/kurangi JUMLAH stok — formulir dan controller yang sama
            // persis dengan milik staff, supaya setiap pergerakan jumlah
            // melewati jalur yang tercatat di log stok.
            Route::get('stocks/jumlah', [StaffStockController::class, 'index'])->name('stocks.jumlah');
            Route::post('stocks/jumlah', [StaffStockController::class, 'store'])->name('stocks.jumlah.store');

            Route::resource('stocks', AdminStockController::class)->except(['show']);

            // Kamar — WEWENANG PENUH.
            //
            // Admin bisa mengerjakan seluruh langkah yang bisa dikerjakan staff
            // (membuat booking, check-in, check-out) TANPA batas waktu, karena
            // koreksi memang tugasnya. Yang TIDAK dilonggarkan adalah syarat
            // fotonya: check-in tetap meminta KTP, check-out tetap meminta foto
            // kartu kunci dan kondisi kamar. Wewenang menentukan SIAPA yang
            // boleh, bukan APA yang boleh dilewati.
            Route::get('rooms/export', [AdminRoomController::class, 'export'])->name('rooms.export');
            Route::get('rooms/available', [AdminBookingController::class, 'available'])->name('rooms.available');
            Route::get('rooms/create', [AdminBookingController::class, 'create'])->name('rooms.create');
            Route::post('rooms', [AdminBookingController::class, 'store'])->name('rooms.store');
            Route::get('rooms/{booking}/checkin', [AdminBookingController::class, 'checkin'])->name('rooms.checkin');
            Route::post('rooms/{booking}/checkin', [AdminBookingController::class, 'checkinStore'])->name('rooms.checkin.store');
            Route::get('rooms/{booking}/checkout', [AdminBookingController::class, 'checkout'])->name('rooms.checkout');
            Route::post('rooms/{booking}/late', [AdminBookingController::class, 'tandaiLate'])->name('rooms.late');
            Route::post('rooms/{booking}/checkout', [AdminBookingController::class, 'checkoutStore'])->name('rooms.checkout.store');
            // Keputusan atas pengajuan perubahan dari staff.
            Route::post('perubahan/{permintaan}/setujui', [AdminPerubahanController::class, 'setujui'])->name('perubahan.setujui');
            Route::post('perubahan/{permintaan}/tolak', [AdminPerubahanController::class, 'tolak'])->name('perubahan.tolak');

            Route::get('rooms/{booking}/edit', [AdminBookingController::class, 'edit'])->name('rooms.edit');
            Route::put('rooms/{booking}', [AdminBookingController::class, 'update'])->name('rooms.update');
            // Menghapus booking = menghapus jejak. Admin mengawasi staff,
            // superadmin mengawasi admin — alat penghapus jejak tidak boleh
            // berada di tangan yang jejaknya diawasi.
            Route::delete('rooms/{booking}', [AdminBookingController::class, 'destroy'])
                ->middleware('role:superadmin')
                ->name('rooms.destroy');
            Route::get('rooms', [AdminRoomController::class, 'index'])->name('rooms.index');
            Route::get('rooms/day/{tanggal}', [AdminRoomController::class, 'day'])
                ->where('tanggal', '\d{4}-\d{2}-\d{2}')
                ->name('rooms.day');

            // Pendapatan (grafik harian di dashboard)
            Route::get('revenue/month', [RevenueController::class, 'month'])->name('revenue.month');
            Route::get('revenue/day/{tanggal}', [RevenueController::class, 'day'])
                ->where('tanggal', '\d{4}-\d{2}-\d{2}')
                ->name('revenue.day');

            // Absensi (lihat saja): selfie + foto kartu tiap shift
            Route::get('absensi/export', [AdminAbsensiController::class, 'export'])->name('absensi.export');
            Route::get('absensi', [AdminAbsensiController::class, 'index'])->name('absensi.index');

            // Hapus data per bulan — SUPERADMIN. Purge booking pada dasarnya
            // hapus booking secara borongan; melarang yang satuan tapi
            // membiarkan yang borongan tidak menutup celah apa pun.
            Route::middleware('role:superadmin')->group(function () {
                Route::post('purge/bookings', [PurgeController::class, 'bookings'])->name('purge.bookings');
                Route::post('purge/absensi', [PurgeController::class, 'absensi'])->name('purge.absensi');
                Route::post('purge/reports', [PurgeController::class, 'reports'])->name('purge.reports');
                Route::post('purge/invoices', [PurgeController::class, 'invoices'])->name('purge.invoices');
                Route::post('purge/stock-logs', [PurgeController::class, 'stockLogs'])->name('purge.stocklogs');
            });

            // Kelola akun — SUPERADMIN. Yang memegang pembuatan akun dan
            // penggantian kata sandi bisa masuk sebagai siapa pun, lalu
            // bekerja atas nama orang lain.
            Route::middleware('role:superadmin')->group(function () {
                Route::get('akun', [AkunController::class, 'index'])->name('akun.index');
                Route::post('akun', [AkunController::class, 'store'])->name('akun.store');
                Route::post('akun/{user}/password', [AkunController::class, 'password'])->name('akun.password');
                Route::post('akun/{user}/toggle', [AkunController::class, 'toggle'])->name('akun.toggle');
                Route::post('akun/{user}/role', [AkunController::class, 'role'])->name('akun.role');
            });

            // ---------- Keuangan: pemasukan & pengeluaran nyata ----------
            // Angka setoran yang benar-benar diterima pemilik, dicatat manual
            // per bulan. Hanya superadmin — ini bukan data operasional, ini
            // pembanding untuk MENGUJI data operasional.
            Route::middleware('role:superadmin')->group(function () {
                Route::get('keuangan', [KeuanganController::class, 'index'])->name('keuangan.index');
                Route::post('keuangan', [KeuanganController::class, 'store'])->name('keuangan.store');
                Route::delete('keuangan/{keuangan}', [KeuanganController::class, 'destroy'])->name('keuangan.destroy');
                Route::get('keuangan/data', [KeuanganController::class, 'data'])->name('keuangan.data');
                Route::get('keuangan/export', [KeuanganController::class, 'export'])->name('keuangan.export');
            });

            // Riwayat tamu (profil per nomor telepon)
            Route::get('tamu', [TamuController::class, 'index'])->name('tamu.index');
            Route::get('tamu/detail', [TamuController::class, 'show'])->name('tamu.show');

            // Invoice — OVERVIEW saja: ringkasan, daftar, dan cetak.
            Route::get('invoices/export', [AdminInvoiceController::class, 'export'])->name('invoices.export');
            // Admin juga berdiri di meja depan: kamar yang terjual malam ini
            // tetap perlu nota. Formulir & controller-nya sama dengan staff.
            Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
            Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
            Route::get('invoices/{invoice}', [AdminInvoiceController::class, 'show'])->name('invoices.show');
            Route::get('invoices', [AdminInvoiceController::class, 'index'])->name('invoices.index');

            // Laporan (tiket dari staff)
            Route::get('reports/export', [AdminReportController::class, 'export'])->name('reports.export');
            Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
            Route::patch('reports/{report}/status', [AdminReportController::class, 'updateStatus'])->name('reports.status');
        });

    // ----- STAFF -----
    // Dibuka juga untuk ADMIN (dan otomatis superadmin): admin punya seluruh
    // kemampuan staff, persis. Halaman yang dipakai sama, bukan salinan —
    // supaya SOP-nya tidak bisa bercabang diam-diam.
    Route::middleware('role:staff,admin')
        ->prefix('staff')
        ->name('staff.')
        ->group(function () {
            // Stok (form multi-baris tambah/kurangi)
            Route::get('stocks', [StaffStockController::class, 'index'])->name('stocks.index');
            Route::post('stocks', [StaffStockController::class, 'store'])->name('stocks.store');

            // Jadwal shift (disusun H-1) + kelola karyawan
            Route::get('jadwal', [JadwalController::class, 'index'])->name('jadwal.index');
            Route::post('jadwal', [JadwalController::class, 'store'])->name('jadwal.store');
            Route::post('karyawan', [JadwalController::class, 'tambahKaryawan'])->name('karyawan.store');
            Route::patch('karyawan/{karyawan}', [JadwalController::class, 'toggleKaryawan'])->name('karyawan.toggle');

            // Absensi harian dengan foto selfie
            Route::get('absensi', [AbsensiController::class, 'index'])->name('absensi.index');
            Route::get('absensi/{jadwal}', [AbsensiController::class, 'create'])->name('absensi.create');
            Route::post('absensi/{jadwal}', [AbsensiController::class, 'store'])->name('absensi.store');

            // Kamar (CRUD booking + kalender + cek ketersediaan + check-in tamu)
            Route::get('rooms/available', [BookingController::class, 'available'])->name('rooms.available');
            Route::get('rooms/day/{tanggal}', [BookingController::class, 'day'])
                ->where('tanggal', '\d{4}-\d{2}-\d{2}')
                ->name('rooms.day');
            // Staff mengajukan perubahan; yang memutuskan admin. Admin tidak
            // memakai rute ini — ia sudah punya formulir ubah langsung.
            Route::get('rooms/{booking}/perubahan', [StaffPerubahanController::class, 'create'])->name('rooms.perubahan');
            Route::post('rooms/{booking}/perubahan', [StaffPerubahanController::class, 'store'])->name('rooms.perubahan.store');

            Route::get('rooms/{booking}/checkin', [BookingController::class, 'checkin'])->name('rooms.checkin');
            Route::post('rooms/{booking}/checkin', [BookingController::class, 'checkinStore'])->name('rooms.checkin.store');
            Route::get('rooms/{booking}/checkout', [BookingController::class, 'checkout'])->name('rooms.checkout');
            Route::post('rooms/{booking}/late', [BookingController::class, 'tandaiLate'])->name('rooms.late');
            Route::post('rooms/{booking}/checkout', [BookingController::class, 'checkoutStore'])->name('rooms.checkout.store');
            // Staff hanya bisa MEMBUAT dan MELIHAT pesanan.
            // Ubah & hapus dipindah ke sisi admin (lihat blok admin di atas).
            Route::resource('rooms', BookingController::class)
                ->only(['index', 'create', 'store'])
                ->parameters(['rooms' => 'booking']);

            // Invoice / billing tamu
            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
            Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
            Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

            // Laporan (kirim tiket + daftar tiket sendiri)
            Route::get('reports', [StaffReportController::class, 'index'])->name('reports.index');
            Route::post('reports', [StaffReportController::class, 'store'])->name('reports.store');
        });
});

require __DIR__.'/auth.php';
