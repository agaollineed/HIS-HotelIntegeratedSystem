@extends('layouts.panel')

@section('title', 'Kelola Akun')

@push('head')
<style>
    .form-card { padding: 26px; margin-bottom: 26px; }
    .form-card h2 { margin: 0 0 18px; font-size: 1.02rem; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .fld { margin-bottom: 16px; }
    .fld label { display: block; font-size: .73rem; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 7px; }
    .fld input[type=text], .fld input[type=email], .fld input[type=password] {
        width: 100%; font-family: inherit; font-size: .97rem; color: var(--text);
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; padding: 12px 14px;
    }
    .fld input:focus { outline: none; border-color: var(--accent); }

    .role-grup { display: flex; gap: 10px; }
    .role-opsi {
        flex: 1; display: flex; align-items: center; gap: 10px; cursor: pointer;
        border: 1px solid var(--border-2); border-radius: 11px; padding: 12px 14px; background: var(--surface);
    }
    .role-opsi:hover { border-color: var(--accent); }
    .role-opsi.aktif { border-color: var(--accent); background: var(--accent-soft); }
    .role-opsi input { width: 16px; height: 16px; accent-color: var(--accent); cursor: pointer; }
    .role-opsi .t { font-weight: 700; font-size: .88rem; }

    .form-actions { display: flex; justify-content: flex-end; margin-top: 6px; }

    /* ---------- Daftar akun ---------- */
    .ak-list { display: flex; flex-direction: column; gap: 10px; }
    .ak {
        border: 1px solid var(--border-2); border-radius: 14px; background: var(--surface); padding: 16px;
    }
    .ak.nonaktif { opacity: .62; border-style: dashed; }
    .ak-baris { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .ak-info { flex: 1 1 220px; min-width: 0; }
    .ak-nama { font-weight: 700; font-size: 1rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .ak-email { font-size: .82rem; color: var(--muted); margin-top: 2px; }

    .badge { display: inline-block; padding: 3px 11px; border-radius: 999px; font-size: .68rem; font-weight: 700; letter-spacing: .04em; }
    .role-pilih {
        font-family: inherit; font-size: .84rem; font-weight: 600; color: var(--text);
        background: var(--surface); border: 1px solid var(--border-2); border-radius: 999px;
        padding: 8px 13px; cursor: pointer; color-scheme: dark; margin-right: 8px;
    }
    .role-pilih:focus { outline: none; border-color: var(--accent); }
    .badge.superadmin { background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border); }
    .badge.admin { background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border); }
    .badge.staff { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-orange); }
    .badge.mati { background: var(--alert-soft); color: var(--alert); border: 1px solid var(--alert-line); }
    .badge.saya { background: var(--hover); color: var(--muted); border: 1px solid var(--border-2); }

    .ak-aksi { display: flex; gap: 8px; flex-wrap: wrap; }

    /* Form ganti sandi tersembunyi sampai dibuka. */
    .sandi-box { margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--border-2); }
    .sandi-box[hidden] { display: none !important; }
    .sandi-grid { display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: end; }

    @media (max-width: 640px) {
        .grid-2, .sandi-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
    <div class="page-head">
        <h1>Kelola Akun</h1>
        <p>Akun tidak dihapus, hanya dinonaktifkan — riwayat pekerjaannya tetap utuh.</p>
    </div>

    {{-- ---------- Tambah akun ---------- --}}
    <div class="card form-card">
        <h2>Tambah Akun Baru</h2>
        <form method="POST" action="{{ route('admin.akun.store') }}">
            @csrf
            <div class="grid-2">
                <div class="fld">
                    <label for="name">Nama</label>
                    <input id="name" type="text" name="name" required value="{{ old('name') }}" placeholder="Nama lengkap">
                </div>
                <div class="fld">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" required value="{{ old('email') }}" placeholder="nama@nyaman.test">
                </div>
            </div>
            <div class="fld">
                <label>Peran</label>
                <div class="role-grup" id="role-grup">
                    <label class="role-opsi {{ old('role', 'staff') === 'staff' ? 'aktif' : '' }}">
                        <input type="radio" name="role" value="staff" @checked(old('role', 'staff') === 'staff')>
                        <span class="t">Staff<br><span style="font-weight:500;font-size:.76rem;color:var(--muted)">Operasional harian</span></span>
                    </label>
                    <label class="role-opsi {{ old('role') === 'admin' ? 'aktif' : '' }}">
                        <input type="radio" name="role" value="admin" @checked(old('role') === 'admin')>
                        <span class="t">Admin<br><span style="font-weight:500;font-size:.76rem;color:var(--muted)">Pengawasan &amp; laporan</span></span>
                    </label>
                    <label class="role-opsi {{ old('role') === 'superadmin' ? 'aktif' : '' }}">
                        <input type="radio" name="role" value="superadmin" @checked(old('role') === 'superadmin')>
                        <span class="t">Superadmin<br><span style="font-weight:500;font-size:.76rem;color:var(--muted)">Akses penuh · hapus data, kelola akun &amp; keuangan</span></span>
                    </label>
                </div>
            </div>
            <div class="grid-2">
                <div class="fld">
                    <label for="password">Kata Sandi <span style="text-transform:none;letter-spacing:0">(min. 8 karakter)</span></label>
                    <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
                </div>
                <div class="fld">
                    <label for="password_confirmation">Ulangi Kata Sandi</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-accent">@include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm']) Buat Akun</button>
            </div>
        </form>
    </div>

    {{-- ---------- Daftar akun ---------- --}}
    <div class="ak-list">
        @foreach ($users as $u)
            <div class="ak {{ $u->aktif ? '' : 'nonaktif' }}">
                <div class="ak-baris">
                    <div class="ak-info">
                        <div class="ak-nama">
                            {{ $u->name }}
                            <span class="badge {{ $u->role }}">{{ strtoupper($u->role) }}</span>
                            @unless ($u->aktif)
                                <span class="badge mati">NONAKTIF</span>
                            @endunless
                            @if ((int) $u->id === (int) auth()->id())
                                <span class="badge saya">ANDA</span>
                            @endif
                        </div>
                        <div class="ak-email">{{ $u->email }} · dibuat {{ $u->created_at->translatedFormat('j M Y') }}</div>
                    </div>

                    <div class="ak-aksi">
                        <button type="button" class="btn btn-ghost btn-sm" data-buka-sandi="{{ $u->id }}">
                            @include('partials.icon', ['name' => 'key-card', 'class' => 'ic-xs']) Ganti Sandi
                        </button>

                        <form method="POST" action="{{ route('admin.akun.role', $u) }}">
                            @csrf
                            {{-- Tiga peran, jadi tidak bisa lagi diwakili satu
                                 tombol "jadikan X". Pilihannya ditampilkan apa adanya. --}}
                            <select name="role" aria-label="Peran {{ $u->name }}" class="role-pilih">
                                @foreach (\App\Models\User::LABEL_ROLE as $nilai => $label)
                                    <option value="{{ $nilai }}" @selected($u->role === $nilai)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-ghost btn-sm">Simpan Peran</button>
                        </form>

                        <form method="POST" action="{{ route('admin.akun.toggle', $u) }}"
                              @if ($u->aktif) onsubmit="return confirm('Nonaktifkan {{ $u->name }}? Sesi yang sedang berjalan ikut terputus.')" @endif>
                            @csrf
                            <button type="submit" class="btn btn-sm {{ $u->aktif ? 'btn-danger' : 'btn-accent' }}">
                                {{ $u->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </form>
                    </div>
                </div>

                <div class="sandi-box" id="sandi-{{ $u->id }}" hidden>
                    <form method="POST" action="{{ route('admin.akun.password', $u) }}">
                        @csrf
                        <div class="sandi-grid">
                            <div class="fld" style="margin:0">
                                <label>Kata Sandi Baru</label>
                                <input type="password" name="password" required minlength="8" autocomplete="new-password">
                            </div>
                            <div class="fld" style="margin:0">
                                <label>Ulangi</label>
                                <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                            </div>
                            <button type="submit" class="btn btn-accent btn-sm">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-buka-sandi]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const kotak = document.getElementById('sandi-' + this.dataset.bukaSandi);
            if (kotak) {
                kotak.hidden = !kotak.hidden;
                if (!kotak.hidden) kotak.querySelector('input').focus();
            }
        });
    });

    const grup = document.getElementById('role-grup');
    if (grup) {
        grup.addEventListener('change', function () {
            grup.querySelectorAll('.role-opsi').forEach(function (l) {
                l.classList.toggle('aktif', l.querySelector('input').checked);
            });
        });
    }
})();
</script>
@endpush
