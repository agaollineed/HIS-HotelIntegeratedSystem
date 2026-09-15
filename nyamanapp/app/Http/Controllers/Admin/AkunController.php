<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Kelola akun admin & staff dari antarmuka — tanpa menyentuh database.
 *
 * Tiga aturan yang dijaga di server, bukan hanya disembunyikan di tampilan:
 *
 *   1. Akun tidak pernah DIHAPUS, hanya dinonaktifkan — riwayat "siapa
 *      mengerjakan apa" pada booking, invoice, dan log harus tetap utuh.
 *   2. Admin tidak bisa menonaktifkan dirinya sendiri.
 *   3. Admin aktif terakhir tidak bisa dinonaktifkan atau diturunkan —
 *      tanpa satu pun admin, tidak ada yang bisa mengelola akun lagi.
 */
class AkunController extends Controller
{
    public function index()
    {
        return view('admin.akun.index', [
            'users' => User::orderByDesc('aktif')->orderBy('role')->orderBy('name')->get(),
        ]);
    }

    /**
     * Buat akun baru (admin atau staff).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users,email',
            'role'     => ['required', Rule::in(User::ROLES)],
            'password' => 'required|string|min:8|confirmed',
        ], [
            'name.required'      => 'Nama wajib diisi.',
            'email.required'     => 'Email wajib diisi.',
            'email.email'        => 'Format email tidak valid.',
            'email.unique'       => 'Email tersebut sudah terpakai.',
            'role.required'      => 'Pilih peran akun.',
            'role.in'            => 'Peran tidak valid.',
            'password.required'  => 'Kata sandi wajib diisi.',
            'password.min'       => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'role'     => $data['role'],
            'aktif'    => true,
            'password' => Hash::make($data['password']),
        ]);

        return redirect()->route('admin.akun.index')
            ->with('success', 'Akun ' . $data['name'] . ' (' . $data['role'] . ') berhasil dibuat.');
    }

    /**
     * Ganti kata sandi sebuah akun.
     */
    public function password(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.required'  => 'Kata sandi baru wajib diisi.',
            'password.min'       => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        return redirect()->route('admin.akun.index')
            ->with('success', 'Kata sandi ' . $user->name . ' berhasil diganti.');
    }

    /**
     * Nonaktifkan / aktifkan kembali sebuah akun.
     */
    public function toggle(User $user): RedirectResponse
    {
        if ($user->aktif) {
            if ((int) $user->id === (int) Auth::id()) {
                return redirect()->route('admin.akun.index')
                    ->withErrors(['akun' => 'Anda tidak bisa menonaktifkan akun sendiri.']);
            }

            if ($user->isSuperadmin() && $this->superadminAktifLain($user) === 0) {
                return redirect()->route('admin.akun.index')
                    ->withErrors(['akun' => 'Tidak bisa: ' . $user->name
                        . ' adalah superadmin aktif terakhir. Angkat superadmin lain lebih dulu.']);
            }

            if ((int) $user->id === (int) Auth::id()) {
                return redirect()->route('admin.akun.index')
                    ->withErrors(['akun' => 'Anda tidak bisa menurunkan peran akun sendiri.']);
            }
        }

        $user->forceFill(['aktif' => ! $user->aktif])->save();

        return redirect()->route('admin.akun.index')
            ->with('success', 'Akun ' . $user->name . ($user->aktif ? ' diaktifkan kembali.' : ' dinonaktifkan — sesi yang sedang berjalan ikut terputus.'));
    }

    /**
     * Ubah peran akun (superadmin / admin / staff).
     */
    public function role(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::in(User::ROLES)]]);

        // Superadmin terakhir tidak boleh diturunkan. Tanpa satu pun
        // superadmin, tidak ada lagi yang bisa mengelola akun — termasuk
        // mengangkat superadmin berikutnya. Aplikasi terkunci dari dirinya
        // sendiri, dan satu-satunya jalan keluar adalah menyentuh database.
        if ($user->isSuperadmin() && $data['role'] !== User::ROLE_SUPERADMIN) {
            if ($this->superadminAktifLain($user) === 0) {
                return redirect()->route('admin.akun.index')
                    ->withErrors(['akun' => 'Tidak bisa: ' . $user->name
                        . ' adalah superadmin aktif terakhir. Angkat superadmin lain lebih dulu.']);
            }

            if ((int) $user->id === (int) Auth::id()) {
                return redirect()->route('admin.akun.index')
                    ->withErrors(['akun' => 'Anda tidak bisa menurunkan peran akun sendiri.']);
            }
        }

        $user->forceFill(['role' => $data['role']])->save();

        return redirect()->route('admin.akun.index')
            ->with('success', 'Peran ' . $user->name . ' diubah menjadi ' . $user->labelRole() . '.');
    }

    // ------------------------------------------------------------------

    private function superadminAktifLain(User $selain): int
    {
        return User::where('role', User::ROLE_SUPERADMIN)
            ->where('aktif', true)
            ->where('id', '!=', $selain->id)
            ->count();
    }
}
