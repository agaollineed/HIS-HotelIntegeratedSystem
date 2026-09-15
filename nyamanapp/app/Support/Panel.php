<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Penentu rute panel: admin atau staff.
 *
 * Halaman check-in, check-out, dan formulir booking dipakai oleh KEDUA peran,
 * tetapi rutenya terpisah karena wewenangnya berbeda (`role:admin` vs
 * `role:staff`). Menyalin view-nya menjadi dua akan melahirkan dua salinan
 * aturan SOP yang bisa saling menyimpang diam-diam — yang justru paling
 * berbahaya pada aplikasi yang tugasnya mengawasi.
 *
 * Karena itu view-nya tetap satu, dan nama rutenya yang menyesuaikan diri.
 *
 * Superadmin memakai panel admin: wewenangnya lebih luas, tetapi halamannya
 * sama — yang berbeda hanya tombol yang boleh muncul di dalamnya.
 */
class Panel
{
    /** 'admin.' atau 'staff.' sesuai peran yang sedang masuk. */
    public static function awalan(): string
    {
        return Auth::user()?->isAdmin() ? 'admin.' : 'staff.';
    }

    /**
     * route() dengan awalan peran yang benar.
     *
     *   Panel::rute('rooms.checkin', $booking)
     *   -> route('admin.rooms.checkin', $booking) bila yang membuka admin
     */
    public static function rute(string $nama, mixed $parameter = []): string
    {
        return route(self::awalan() . $nama, $parameter);
    }

    /** Nama rute lengkap tanpa membangun URL-nya. */
    public static function nama(string $nama): string
    {
        return self::awalan() . $nama;
    }
}
