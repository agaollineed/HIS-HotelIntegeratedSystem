@php
    /**
     * Jadwal shift hari ini + status absensi tiap orang.
     * Dipakai di dashboard: entri bisa ditekan untuk melihat detail
     * (waktu absen, keterlambatan, dan foto buktinya).
     */
    $jadwalHariIni  = $jadwalHariIni  ?? collect();
    $liburHariIni   = $liburHariIni   ?? collect();
    $tanggalHariIni = $tanggalHariIni ?? now();
    $vertikal       = $vertikal ?? false;   // tampilan sempit di samping kartu fitur

    $sudahAbsen = $jadwalHariIni->filter(fn ($j) => $j->absensi !== null);
    $telatCount = $sudahAbsen->filter(fn ($j) => $j->absensi->telat())->count();
@endphp

@push('head')
<style>
    .jd-card { padding: 22px; margin-bottom: 26px; }
    .jd-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 14px; }
    .jd-top h2 { margin: 0 0 4px; font-size: 1.15rem; font-weight: 800; letter-spacing: -.02em; }
    .jd-top p { margin: 0; color: var(--muted); font-size: .86rem; }

    .jd-ring { display: flex; gap: 8px; flex-wrap: wrap; }
    .jd-ring .pill {
        border: 1px solid var(--border-2); border-radius: 999px; padding: 6px 13px;
        background: var(--surface); font-size: .78rem; color: var(--muted);
    }
    .jd-ring .pill b { font-variant-numeric: tabular-nums; }
    .jd-ring .pill.h b { color: var(--ok); }
    .jd-ring .pill.t b { color: var(--alert); }
    .jd-ring .pill.b b { color: var(--accent-2); }

    .jd-list { display: flex; flex-direction: column; gap: 10px; }
    .jd-item { border: 1px solid var(--border-2); border-radius: 14px; background: var(--surface); overflow: hidden; }
    .jd-item.belum { border-left: 3px solid var(--accent-2); }
    .jd-item.hadir { border-left: 3px solid var(--ok); }
    .jd-item.telat { border-left: 3px solid var(--alert); }

    .jd-head {
        width: 100%; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
        padding: 14px 16px; background: transparent; border: none; cursor: pointer;
        font-family: inherit; text-align: left; color: var(--text); transition: background .15s;
    }
    .jd-head:hover { background: var(--hover); }
    .jd-no { font-variant-numeric: tabular-nums; color: var(--muted); font-size: .82rem; }
    .jd-nama { font-weight: 700; }
    .jd-jam { font-variant-numeric: tabular-nums; color: var(--ok); font-size: .86rem; font-weight: 700; }
    .jd-spacer { flex: 1 1 auto; }
    .jd-chev { color: var(--muted); display: inline-flex; transition: transform .18s; }
    .jd-item.open .jd-chev { transform: rotate(180deg); }

    .st-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; font-size: .74rem; font-weight: 700; }
    .st-hadir { background: rgba(74,222,128,.12); color: var(--ok); border: 1px solid rgba(74,222,128,.28); }
    .st-telat { background: var(--alert-soft); color: var(--alert); border: 1px solid var(--alert-line); }
    .st-belum { background: var(--accent-2-soft); color: var(--accent-2-600); border: 1px solid var(--border-violet); }

    .jd-detail { padding: 4px 16px 18px; border-top: 1px solid var(--border-2); }
    .jd-detail[hidden] { display: none !important; }
    .jd-detail dl {
        margin: 14px 0 0; display: grid; grid-template-columns: 130px 1fr;
        row-gap: 9px; column-gap: 12px; font-size: .9rem;
    }
    .jd-detail dt { color: var(--muted); font-size: .72rem; letter-spacing: .05em; text-transform: uppercase; align-self: center; }
    .jd-detail dd { margin: 0; font-weight: 600; }
    .jd-detail dd.mono { font-variant-numeric: tabular-nums; }
    .jd-foto { margin-top: 14px; }
    .jd-foto img {
        width: 220px; max-width: 100%; border-radius: 12px; cursor: zoom-in;
        border: 1px solid var(--border-2); box-shadow: 0 16px 34px -18px var(--accent-glow);
    }
    .jd-nofoto {
        margin-top: 14px; border: 1px dashed var(--border-violet); border-radius: 12px;
        padding: 14px; text-align: center; color: var(--muted); font-size: .84rem;
    }

    .jd-libur { margin-top: 14px; font-size: .86rem; color: var(--muted); }
    .jd-libur b { color: var(--accent); }
    .jd-empty { text-align: center; color: var(--muted); padding: 28px 20px; }

    /* ---------- Varian vertikal (kolom sempit di samping kartu fitur) ---------- */
    .jd-card.vertikal { padding: 20px; margin-bottom: 0; }
    .jd-card.vertikal .jd-top { flex-direction: column; align-items: stretch; gap: 10px; }
    .jd-card.vertikal .jd-ring { gap: 6px; }
    .jd-card.vertikal .jd-ring .pill { padding: 5px 10px; font-size: .72rem; }
    .jd-card.vertikal .jd-head { flex-wrap: wrap; gap: 6px 9px; padding: 12px 13px; }
    .jd-card.vertikal .jd-spacer { display: none; }
    .jd-card.vertikal .jd-nama { flex: 1 1 auto; }
    .jd-card.vertikal .jd-jam { flex: 0 0 auto; font-size: .8rem; }
    .jd-card.vertikal .st-badge { flex: 0 0 auto; font-size: .7rem; padding: 4px 10px; }
    .jd-card.vertikal .jd-chev { margin-left: auto; }
    .jd-card.vertikal .jd-detail dl { grid-template-columns: 1fr; row-gap: 2px; }
    .jd-card.vertikal .jd-detail dt { margin-top: 8px; }
    .jd-card.vertikal .jd-foto img { width: 100%; }

    @media (max-width: 640px) {
        .jd-detail dl { grid-template-columns: 104px 1fr; }
    }
</style>
@endpush

<div class="card jd-card {{ $vertikal ? 'vertikal' : '' }}" id="jadwal-hari-ini">
    <div class="jd-top">
        <div>
            <h2>Jadwal Hari Ini</h2>
            <p>
                {{ $tanggalHariIni->translatedFormat($vertikal ? 'l, j F' : 'l, j F Y') }}
                @unless ($vertikal) — tekan sebuah nama untuk melihat status absensinya. @endunless
            </p>
        </div>
        @if ($jadwalHariIni->isNotEmpty())
            <div class="jd-ring">
                <span class="pill h">Hadir <b>{{ $sudahAbsen->count() - $telatCount }}</b></span>
                <span class="pill t">Telat <b>{{ $telatCount }}</b></span>
                <span class="pill b">Belum absen <b>{{ $jadwalHariIni->count() - $sudahAbsen->count() }}</b></span>
            </div>
        @endif
    </div>

    @if ($jadwalHariIni->isEmpty())
        <div class="jd-empty">Belum ada jadwal shift untuk hari ini.</div>
    @else
        <div class="jd-list">
            @foreach ($jadwalHariIni as $i => $j)
                @php $a = $j->absensi; @endphp
                <div class="jd-item {{ $a === null ? 'belum' : ($a->telat() ? 'telat' : 'hadir') }}">
                    <button type="button" class="jd-head" aria-expanded="false">
                        <span class="jd-no">{{ $i + 1 }}.</span>
                        <span class="jd-nama">{{ $j->karyawan?->nama ?? '—' }}</span>
                        <span class="jd-jam">{{ $j->labelJam() }}</span>
                        <span class="jd-spacer"></span>
                        @if ($a === null)
                            <span class="st-badge st-belum">Belum absen</span>
                        @elseif ($a->telat())
                            <span class="st-badge st-telat">{{ $a->labelTelat() }}</span>
                        @else
                            <span class="st-badge st-hadir">
                                @include('partials.icon', ['name' => 'check', 'class' => 'ic-xs']) Tepat waktu
                            </span>
                        @endif
                        <span class="jd-chev">@include('partials.icon', ['name' => 'chevron-down', 'class' => 'ic-xs'])</span>
                    </button>

                    <div class="jd-detail" hidden>
                        <dl>
                            <dt>Jam Shift</dt>
                            <dd class="mono">{{ $j->labelJam() }}{{ $j->lintasHari() ? ' (+1 hari)' : '' }}</dd>

                            <dt>Status</dt>
                            <dd>{{ $a === null ? 'Belum absen' : ($a->telat() ? $a->labelTelat() : 'Tepat waktu') }}</dd>

                            <dt>Waktu Absen</dt>
                            <dd class="mono">{{ $a ? $a->waktu_absen->translatedFormat('j F Y, H:i') : '—' }}</dd>

                            <dt>Diinput Oleh</dt>
                            <dd>{{ $a?->creator?->name ?? '—' }}</dd>
                        </dl>

                        @if ($a && $a->foto)
                            <div class="jd-foto">
                                <img src="{{ route('absensi.foto', $a) }}"
                                     alt="Foto absen {{ $j->karyawan?->nama }}"
                                     data-lightbox="{{ route('absensi.foto', $a) }}">
                            </div>
                        @else
                            <div class="jd-nofoto">{{ $a ? 'Foto sudah dihapus sesuai kebijakan retensi.' : 'Belum ada foto — karyawan ini belum melakukan absensi.' }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($vertikal)
            <p style="margin: 10px 2px 0; font-size: .76rem; color: var(--muted);">Tekan nama untuk melihat foto &amp; detail absensinya.</p>
        @endif

        <div class="jd-libur">
            NB: <b>{{ $liburHariIni->isEmpty() ? 'Full Team' : $liburHariIni->implode(', ') . ' libur' }}</b>
        </div>
    @endif
</div>

@push('scripts')
<script>
    // Tekan entri jadwal -> buka/tutup detail absensinya.
    (function () {
        const panel = document.getElementById('jadwal-hari-ini');
        if (!panel) return;

        panel.addEventListener('click', function (e) {
            const head = e.target.closest('.jd-head');
            if (!head) return;

            const item = head.closest('.jd-item');
            const detail = item.querySelector('.jd-detail');
            if (!detail) return;

            const buka = detail.hidden;
            detail.hidden = !buka;
            item.classList.toggle('open', buka);
            head.setAttribute('aria-expanded', buka ? 'true' : 'false');
        });
    })();
</script>
@endpush
