<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'aktif',
    ];

    // ------------------------------------------------------------------
    // Peran
    //
    // Tiga tingkat, dan pembedanya bukan "siapa yang lebih penting"
    // melainkan SIAPA YANG DIAWASI OLEH SIAPA:
    //
    //   staff       mencatat pekerjaan harian; koreksi data lewat pengajuan
    //   admin       mengawasi staff — menyetujui pengajuan, mengubah booking,
    //               dan mengerjakan seluruh pekerjaan staff bila perlu
    //   superadmin  mengawasi admin — satu-satunya yang boleh MENGHAPUS data
    //               dan MEMEGANG kunci akun (membuat akun & mengganti sandi)
    //
    // Pemisahan terakhir itulah intinya: alat pengawasan tidak boleh berada
    // di tangan orang yang diawasinya. Admin yang bisa menghapus booking dan
    // membuat akun bisa menghapus jejaknya sendiri, dan seluruh rantai bukti
    // foto di aplikasi ini kehilangan artinya.
    // ------------------------------------------------------------------

    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_ADMIN      = 'admin';
    public const ROLE_STAFF      = 'staff';

    /** Seluruh peran yang sah — dipakai validasi formulir & perintah artisan. */
    public const ROLES = [self::ROLE_SUPERADMIN, self::ROLE_ADMIN, self::ROLE_STAFF];

    public const LABEL_ROLE = [
        self::ROLE_SUPERADMIN => 'Superadmin',
        self::ROLE_ADMIN      => 'Admin',
        self::ROLE_STAFF      => 'Staff',
    ];

    /** Superadmin: akses penuh, tanpa kecuali. */
    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    /**
     * Berada di panel PENGAWAS (admin maupun superadmin).
     *
     * Dipakai untuk memilih panel dan tampilan, BUKAN untuk memutuskan
     * wewenang. Pertanyaan "boleh apa" dijawab oleh metode boleh*() di bawah,
     * supaya penambahan peran baru tidak menuntut penyisiran seluruh view.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPERADMIN], true);
    }

    /** Admin biasa — bukan superadmin. */
    public function isAdminBiasa(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function labelRole(): string
    {
        return self::LABEL_ROLE[$this->role] ?? ucfirst((string) $this->role);
    }

    // ------------------------------------------------------------------
    // Wewenang
    //
    // Satu tempat untuk seluruh pertanyaan "boleh atau tidak". Route,
    // controller, dan view semuanya bertanya ke sini — jadi mengubah aturan
    // cukup satu baris, dan tidak mungkin ada tombol yang tampil padahal
    // servernya menolak (atau sebaliknya).
    // ------------------------------------------------------------------

    /** Membuat akun, mengganti kata sandi, menonaktifkan, mengubah peran. */
    public function bolehKelolaAkun(): bool
    {
        return $this->isSuperadmin();
    }

    /** Menghapus baris booking (satu kamar maupun satu grup pesanan). */
    public function bolehHapusBooking(): bool
    {
        return $this->isSuperadmin();
    }

    /**
     * Menghapus catatan keuangan (saldo harian maupun omset bulanan).
     *
     * Admin ikut mengisi dan mengoreksi angka — mengisi ulang tanggal atau
     * bulan yang sama sudah menimpa angkanya — tetapi menghapus jejaknya
     * tetap wewenang pemilik.
     */
    public function bolehHapusKeuangan(): bool
    {
        return $this->isSuperadmin();
    }

    /**
     * Menghapus data per bulan (booking, absensi, laporan, invoice, log stok).
     *
     * Ikut superadmin karena purge booking pada dasarnya adalah hapus booking
     * secara borongan — melarang yang satuan tapi mengizinkan yang borongan
     * tidak menutup celah apa pun.
     */
    public function bolehHapusDataBulanan(): bool
    {
        return $this->isSuperadmin();
    }

    /**
     * Menyetel jumlah stok LANGSUNG lewat formulir item.
     *
     * Admin menambah ITEM baru dan mengubah nama/harga, tetapi jumlahnya
     * bergerak lewat formulir tambah/kurangi yang tercatat di log stok —
     * bukan lewat penimpaan angka yang tidak menyisakan selisih.
     */
    public function bolehSetJumlahStok(): bool
    {
        return $this->isSuperadmin();
    }

    /** Membuat, mengubah, dan menghapus item stok. */
    public function bolehKelolaItemStok(): bool
    {
        return $this->isAdmin();
    }

    /** Menyetujui / menolak pengajuan perubahan dari staff. */
    public function bolehPutusPerubahan(): bool
    {
        return $this->isAdmin();
    }

    /** Mengubah booking langsung, tanpa lewat pengajuan. */
    public function bolehUbahBooking(): bool
    {
        return $this->isAdmin();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'aktif'    => 'boolean',
        ];
    }
}
