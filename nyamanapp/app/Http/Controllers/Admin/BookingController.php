<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Staff\BookingController as StaffBookingController;

/**
 * Ubah & hapus booking — WEWENANG ADMIN.
 *
 * Staff kini hanya bisa membuat dan membaca pesanan. Mengubah tanggal,
 * memindah kamar, atau menghapus booking mengubah jejak yang sudah tercatat,
 * dan jejak itulah alat pengawasan — karena itu koreksinya lewat pengawas.
 *
 * Seluruh logika (validasi, cek bentrok, pembersihan foto, grup) diwarisi
 * dari controller staff; yang berbeda hanya halaman tujuan dan view-nya,
 * supaya tidak ada dua salinan aturan yang bisa saling menyimpang.
 */
class BookingController extends StaffBookingController
{
    protected function ruteIndex(): string
    {
        return 'admin.rooms.index';
    }

    protected function viewEdit(): string
    {
        return 'admin.rooms.edit';
    }
}
