{{-- Pencarian tamu lintas tanggal — dipakai halaman kamar staff & admin.
     Variabel: $cari (string), $hasilCari (Collection|null). --}}
@php
    $ruteIndex = \App\Support\Panel::nama('rooms.index');
    $labelStatus = [
        'menginap' => 'Sedang menginap',
        'akan'     => 'Akan datang',
        'selesai'  => 'Sudah check-out',
        'lewat'    => 'Lewat, belum check-out',
    ];
@endphp

@once
@push('head')
<style>
    .cari-card { padding: 16px 18px; margin-bottom: 22px; }
    .cari-form { display: flex; gap: 10px; align-items: center; }
    .cari-form .kolom {
        flex: 1; display: flex; align-items: center; gap: 10px;
        background: var(--bg); border: 1px solid var(--border-2); border-radius: 12px; padding: 0 14px;
    }
    .cari-form .kolom:focus-within { border-color: var(--accent); }
    .cari-form input {
        flex: 1; min-width: 0; font-family: inherit; font-size: .95rem; color: var(--text);
        background: transparent; border: 0; padding: 12px 0;
    }
    .cari-form input:focus { outline: none; }
    .cari-form .kolom .ic-sm { color: var(--muted); flex: 0 0 auto; }
    .cari-info { margin: 12px 2px 0; font-size: .82rem; color: var(--muted); }
    .cari-list { display: grid; gap: 8px; margin-top: 12px; }
    .cari-item {
        display: grid; grid-template-columns: 1fr auto; gap: 6px 14px; align-items: center;
        padding: 12px 14px; border: 1px solid var(--border-2); border-radius: 12px;
        background: var(--surface-2); color: inherit; text-decoration: none;
        transition: border-color .15s, background .15s;
    }
    .cari-item:hover { border-color: var(--accent); background: var(--hover); }
    .cari-item .nama { font-weight: 800; }
    .cari-item .tel { color: var(--muted); font-size: .82rem; font-variant-numeric: tabular-nums; }
    .cari-item .meta { grid-column: 1 / -1; font-size: .82rem; color: var(--muted); font-variant-numeric: tabular-nums; }
    .cari-item .meta b { color: var(--text); font-weight: 700; }
    .cari-st {
        font-size: .7rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase;
        border-radius: 999px; padding: 3px 10px; white-space: nowrap; border: 1px solid var(--border-2);
    }
    .cari-st.menginap { color: var(--ok); background: var(--ok-soft); border-color: var(--ok-line); }
    .cari-st.akan     { color: var(--accent); background: var(--accent-soft); border-color: var(--border); }
    .cari-st.selesai  { color: var(--muted); }
    .cari-st.lewat    { color: var(--alert); background: var(--alert-soft); border-color: var(--alert-line); }
    @media (max-width: 560px) {
        .cari-form { flex-wrap: wrap; }
        .cari-form .btn { flex: 1; }
    }
</style>
@endpush
@endonce

<div class="card cari-card">
    <form method="GET" action="{{ route($ruteIndex) }}" class="cari-form" role="search">
        <label class="kolom">
            @include('partials.icon', ['name' => 'search', 'class' => 'ic-sm'])
            <input type="search" name="cari" value="{{ $cari }}" autocomplete="off"
                   placeholder="Cari tamu: nama, nomor telepon, atau nomor kamar…" aria-label="Cari tamu">
        </label>
        <button type="submit" class="btn btn-accent">Cari</button>
        @if ($cari !== '')
            <a href="{{ route($ruteIndex) }}" class="btn btn-ghost">Hapus</a>
        @endif
    </form>

    @if ($cari !== '' && $hasilCari === null)
        <p class="cari-info">Ketik minimal 2 huruf atau angka.</p>
    @elseif ($hasilCari !== null)
        @if ($hasilCari->isEmpty())
            <p class="cari-info">Tidak ada tamu yang cocok dengan "{{ $cari }}".</p>
        @else
            <p class="cari-info">
                {{ $hasilCari->count() }} kunjungan cocok dengan "{{ $cari }}"{{ $hasilCari->count() >= 25 ? ' — ditampilkan 25 teratas, persempit kata kuncinya' : '' }}.
                Klik untuk membuka tanggalnya di kalender.
            </p>
            <div class="cari-list">
                @foreach ($hasilCari as $k)
                    @php
                        // Kamar ditulis urut waktu; pindah per hari terbaca "202 → 203".
                        $kamar = '';
                        foreach ($k->anggota->values() as $i => $b) {
                            $nomor  = $b->room?->nomor ?? '—';
                            $kamar .= $i === 0 ? $nomor : (($b->isPindahan() ? ' → ' : ', ') . $nomor);
                        }
                    @endphp
                    <a class="cari-item"
                       href="{{ route($ruteIndex, ['tanggal' => $k->buka->toDateString(), 'bulan' => $k->buka->format('Y-m')]) }}#day-panel">
                        <div>
                            <span class="nama">{{ $k->utama->nama_tamu }}</span>
                            <span class="tel">· {{ $k->utama->telepon }}</span>
                        </div>
                        <span class="cari-st {{ $k->status }}">{{ $labelStatus[$k->status] ?? $k->status }}</span>
                        <div class="meta">
                            Kamar <b>{{ $kamar }}</b> ·
                            {{ $k->awal->translatedFormat('j M') }} – {{ $k->akhir->translatedFormat('j M Y') }}
                            ({{ $k->malam }} malam) ·
                            {{ $k->utama->ringkasanBayar($k->total) }}
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    @endif
</div>
