@extends('layouts.panel')

@section('title', 'Jadwal Shift')

@push('head')
<style>
    .head-row { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }

    .tgl-card { padding: 18px 20px; margin-bottom: 22px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .tgl-card .lbl { font-size: .72rem; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); font-weight: 700; }
    .tgl-card input[type=date] {
        font-family: inherit; font-size: 1rem; color: var(--text); color-scheme: dark;
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; padding: 11px 13px;
    }
    .tgl-card input[type=date]:focus { outline: none; border-color: var(--accent); }
    .tgl-pintas { display: flex; gap: 8px; flex-wrap: wrap; }

    .shift-card { padding: 20px; }
    .shift-row {
        display: grid; grid-template-columns: 26px 1fr auto auto; gap: 12px; align-items: center;
        padding: 12px; border: 1px solid var(--border-2); border-radius: 12px; margin-bottom: 10px;
        background: var(--surface);
    }
    .shift-row.libur { opacity: .55; }
    .shift-row input[type=checkbox] { width: 18px; height: 18px; accent-color: var(--accent); cursor: pointer; }
    .shift-nama { font-weight: 700; }
    .shift-nama .st { display: block; font-size: .74rem; color: var(--muted); font-weight: 600; }
    .jam-grup { display: flex; align-items: center; gap: 8px; }
    .jam-grup input[type=time] {
        font-variant-numeric: tabular-nums; font-size: .92rem; color: var(--text); color-scheme: dark;
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 9px; padding: 9px 10px;
    }
    .jam-grup input[type=time]:focus { outline: none; border-color: var(--accent); }
    .jam-grup .sep { color: var(--muted); }

    .preset { display: flex; gap: 6px; flex-wrap: wrap; }
    .preset button {
        font-family: inherit; font-size: .72rem; font-weight: 700; cursor: pointer;
        background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border);
        border-radius: 999px; padding: 5px 10px; white-space: nowrap;
    }
    .preset button:hover { background: var(--hover); border-color: var(--accent); }

    .nb {
        margin-top: 16px; padding: 13px 16px; border-radius: 12px; font-size: .88rem;
        background: var(--accent-soft); border: 1px solid var(--border); color: var(--text);
    }
    .nb b { color: var(--accent); }

    .form-actions { display: flex; gap: 12px; margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--border-2); }

    /* Ringkasan jadwal tersimpan */
    .ringkas { padding: 20px; margin-bottom: 22px; }
    .ringkas h2 { margin: 0 0 12px; font-size: 1.05rem; font-weight: 800; letter-spacing: -.02em; }
    .ringkas ol { margin: 0; padding-left: 20px; display: flex; flex-direction: column; gap: 7px; }
    .ringkas li { font-size: .95rem; }
    .ringkas .jam { font-variant-numeric: tabular-nums; color: var(--ok); font-weight: 700; margin-left: 6px; }
    .ringkas .kosong { color: var(--muted); font-size: .9rem; }

    /* Kelola karyawan */
    .kry-card { padding: 20px; margin-top: 22px; }
    .kry-card h2 { margin: 0 0 4px; font-size: 1.05rem; font-weight: 800; letter-spacing: -.02em; }
    .kry-card p.sub { margin: 0 0 14px; color: var(--muted); font-size: .85rem; }
    .kry-list { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
    .kry {
        display: inline-flex; align-items: center; gap: 9px;
        border: 1px solid var(--border-2); border-radius: 999px; padding: 6px 8px 6px 14px;
        background: var(--surface); font-size: .88rem;
    }
    .kry.off { opacity: .5; }
    .kry form { display: inline; }
    .kry button {
        font-family: inherit; font-size: .7rem; font-weight: 700; cursor: pointer;
        border: 1px solid var(--border-2); background: var(--bg); color: var(--muted);
        border-radius: 999px; padding: 4px 10px;
    }
    .kry button:hover { border-color: var(--accent); color: var(--accent); }
    .kry-tambah { display: flex; gap: 10px; flex-wrap: wrap; }
    .kry-tambah input {
        flex: 1 1 200px; font-family: inherit; font-size: .95rem; color: var(--text);
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 10px; padding: 11px 13px;
    }
    .kry-tambah input:focus { outline: none; border-color: var(--accent); }

    @media (max-width: 640px) {
        .shift-row { grid-template-columns: 26px 1fr; }
        .jam-grup, .preset { grid-column: 1 / -1; }
    }
</style>
@endpush

@section('content')
    <div class="page-head head-row">
        <div>
            <h1>Jadwal Shift</h1>
            <p>Karyawan yang tidak dicentang berarti libur.</p>
        </div>
        <a href="{{ route('staff.absensi.index') }}" class="btn btn-ghost">
            @include('partials.icon', ['name' => 'clock', 'class' => 'ic-sm']) Absensi
        </a>
    </div>

    {{-- Pilih tanggal --}}
    <form method="GET" action="{{ route('staff.jadwal.index') }}" class="card tgl-card">
        <div>
            <div class="lbl">Tanggal jadwal</div>
            <input type="date" name="tanggal" value="{{ $tanggal->toDateString() }}" onchange="this.form.submit()">
        </div>
        <div class="tgl-pintas">
            <a href="{{ route('staff.jadwal.index', ['tanggal' => $hariIni]) }}" class="btn btn-ghost btn-sm">Hari ini</a>
            <a href="{{ route('staff.jadwal.index', ['tanggal' => $besok]) }}" class="btn btn-ghost btn-sm">Besok</a>
        </div>
    </form>

    {{-- Ringkasan jadwal yang sudah tersimpan --}}
    <div class="card ringkas">
        <h2>Jadwal {{ $tanggal->translatedFormat('l, j F Y') }}</h2>
        @if ($jadwal->isEmpty())
            <div class="kosong">Belum ada jadwal untuk tanggal ini — isi formulir di bawah.</div>
        @else
            <ol>
                @foreach ($jadwal->sortBy('jam_mulai') as $j)
                    <li>{{ $j->karyawan?->nama ?? '—' }}<span class="jam">{{ $j->labelJam() }}</span></li>
                @endforeach
            </ol>
            <div class="nb">
                NB: <b>{{ $libur->isEmpty() ? 'Full Team' : $libur->implode(', ') . ' libur' }}</b>
            </div>
        @endif
    </div>

    {{-- Formulir susun jadwal --}}
    <form method="POST" action="{{ route('staff.jadwal.store') }}" class="card shift-card">
        @csrf
        <input type="hidden" name="tanggal" value="{{ $tanggal->toDateString() }}">

        @foreach ($karyawan as $k)
            @php
                $ada  = $jadwal->get($k->id);
                $isi  = old('shift.' . $k->id, []);
                $cek  = array_key_exists('bertugas', (array) $isi) ? (bool) $isi['bertugas'] : $ada !== null;
                $mulai   = $isi['jam_mulai']   ?? $ada?->jam_mulai   ?? '08:00';
                $selesai = $isi['jam_selesai'] ?? $ada?->jam_selesai ?? '17:00';
            @endphp
            <div class="shift-row {{ $cek ? '' : 'libur' }}" data-baris>
                <input type="checkbox" name="shift[{{ $k->id }}][bertugas]" value="1" @checked($cek)>
                <div class="shift-nama">
                    {{ $k->nama }}
                    <span class="st">{{ $cek ? 'Bertugas' : 'Libur' }}</span>
                </div>
                <div class="jam-grup">
                    <input type="time" name="shift[{{ $k->id }}][jam_mulai]" value="{{ $mulai }}">
                    <span class="sep">–</span>
                    <input type="time" name="shift[{{ $k->id }}][jam_selesai]" value="{{ $selesai }}">
                </div>
                <div class="preset">
                    @foreach ($preset as $p)
                        @php [$pm, $ps] = explode('-', $p); @endphp
                        <button type="button" data-mulai="{{ $pm }}" data-selesai="{{ $ps }}">{{ str_replace(':', '.', $pm) }}–{{ str_replace(':', '.', $ps) }}</button>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if ($karyawan->isEmpty())
            <p style="color: var(--muted)">Belum ada karyawan aktif. Tambahkan lewat kotak di bawah.</p>
        @endif

        <div class="nb" id="nb-preview">NB: <b>—</b></div>

        <div class="form-actions">
            <button type="submit" class="btn btn-accent">
                @include('partials.icon', ['name' => 'check', 'class' => 'ic-sm']) Simpan Jadwal
            </button>
        </div>
    </form>

    {{-- Kelola karyawan --}}
    <div class="card kry-card">
        <h2>Karyawan</h2>
        <p class="sub">Karyawan nonaktif tidak muncul di jadwal baru, tapi riwayat absensinya tetap tersimpan.</p>

        <div class="kry-list">
            @foreach ($semuaKaryawan as $k)
                <span class="kry {{ $k->aktif ? '' : 'off' }}">
                    {{ $k->nama }}
                    <form method="POST" action="{{ route('staff.karyawan.toggle', $k) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit">{{ $k->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                    </form>
                </span>
            @endforeach
        </div>

        <form method="POST" action="{{ route('staff.karyawan.store') }}" class="kry-tambah">
            @csrf
            <input type="text" name="nama" placeholder="Nama karyawan baru" required>
            <button type="submit" class="btn btn-ghost">
                @include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm']) Tambah
            </button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const baris = Array.from(document.querySelectorAll('[data-baris]'));
        const nb = document.getElementById('nb-preview');
        if (!baris.length || !nb) return;

        function perbarui() {
            const libur = [];
            baris.forEach(function (row) {
                const cb = row.querySelector('input[type=checkbox]');
                const nama = row.querySelector('.shift-nama').childNodes[0].textContent.trim();
                const st = row.querySelector('.shift-nama .st');
                row.classList.toggle('libur', !cb.checked);
                st.textContent = cb.checked ? 'Bertugas' : 'Libur';
                if (!cb.checked) libur.push(nama);
            });
            nb.innerHTML = 'NB: <b>' + (libur.length === 0 ? 'Full Team' : libur.join(', ') + ' libur') + '</b>';
        }

        baris.forEach(function (row) {
            row.querySelector('input[type=checkbox]').addEventListener('change', perbarui);

            // Preset jam: klik -> isi kedua kolom sekaligus + tandai bertugas.
            row.querySelectorAll('.preset button').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    row.querySelector('input[name$="[jam_mulai]"]').value = this.dataset.mulai;
                    row.querySelector('input[name$="[jam_selesai]"]').value = this.dataset.selesai;
                    row.querySelector('input[type=checkbox]').checked = true;
                    perbarui();
                });
            });
        });

        perbarui();
    })();
</script>
@endpush
