<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Batasi akses route berdasarkan role user.
     * Contoh pemakaian di route: ->middleware('role:admin')
     * Bisa banyak role: ->middleware('role:admin,staff')
     *
     * SUPERADMIN SELALU LOLOS.
     *
     * Aturannya ditegakkan di sini, bukan dengan menuliskan "superadmin" di
     * setiap baris route. Alasannya sederhana: daftar route panjang, dan satu
     * baris yang terlupa akan menghasilkan halaman 403 untuk peran yang
     * seharusnya bisa membuka segalanya — persis jenis kesalahan yang baru
     * ketahuan saat dipakai jam dua pagi.
     *
     * Route yang HANYA boleh dibuka superadmin tetap ditulis eksplisit
     * (`role:superadmin`), dan tidak terpengaruh kelonggaran ini karena admin
     * memang tidak ada dalam daftarnya.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if (! $user) {
            abort(403, 'Anda tidak punya akses ke halaman ini.');
        }

        if ($user->role === User::ROLE_SUPERADMIN) {
            return $next($request);
        }

        if (! in_array($user->role, $roles, true)) {
            // Diarahkan ke panel sesuai role-nya sendiri agar tidak "nyasar".
            abort(403, 'Anda tidak punya akses ke halaman ini.');
        }

        return $next($request);
    }
}
