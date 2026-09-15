<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keluarkan sesi milik akun yang sudah dinonaktifkan.
 *
 * Pemeriksaan saat login saja tidak cukup: staff yang dinonaktifkan siang
 * hari masih memegang sesi yang dibuat paginya. Middleware ini menutup celah
 * itu — begitu akun dinonaktifkan, permintaan berikutnya langsung terputus,
 * bukan menunggu sesinya kedaluwarsa sendiri.
 */
class PastikanAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->aktif) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Akun Anda telah dinonaktifkan. Hubungi admin.']);
        }

        return $next($request);
    }
}
