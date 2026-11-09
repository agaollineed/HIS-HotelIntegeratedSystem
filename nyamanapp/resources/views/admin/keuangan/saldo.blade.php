@extends('layouts.panel')

@section('title', 'Saldo Rekening')

@push('head')
<style>
    /* ---------- Grafik ---------- */
    .sl-card { padding: 20px 22px; margin-bottom: 22px; }
    .sl-dua { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); gap: 18px; }
    .sl-dua > .sl-card { margin-bottom: 22px; min-width: 0; }
    @media (max-width: 1000px) { .sl-dua { grid-template-columns: 1fr; } }
    .sl-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 14px; }
    .sl-top h2 { margin: 0 0 4px; font-size: 1.1rem; font-weight: 800; letter-spacing: -.02em; }
    .sl-top p { margin: 0; color: var(--muted); font-size: .84rem; line-height: 1.5; }
    .sl-rentang { display: inline-flex; gap: 4px; padding: 3px; border: 1px solid var(--border-2); border-radius: 999px; background: var(--bg); }
    .sl-rentang button {
        font-family: inherit; font-size: .78rem; font-weight: 700; cursor: pointer; border: none;
        padding: 6px 12px; border-radius: 999px; background: none; color: var(--muted);
    }
    .sl-rentang button[aria-pressed="true"] { background: var(--grad); color: #fff; }

    .sl-plot { position: relative; min-height: 260px; }
    .sl-plot svg { display: block; width: 100%; height: 260px; overflow: visible; }
    .sl-plot .grid line { stroke: var(--border-2); stroke-width: 1; }
    .sl-plot .grid text, .sl-plot .sumbu-x text { fill: var(--muted); font-size: 11px; font-family: inherit; font-variant-numeric: tabular-nums; }
    .sl-plot .area { fill: var(--accent-soft); }
    .sl-plot .garis { fill: none; stroke: var(--accent); stroke-width: 2.5; stroke-linejoin: round; stroke-linecap: round; }
    .sl-plot .titik { fill: var(--bg); stroke: var(--accent); stroke-width: 2; }
    .sl-plot .titik.akhir { fill: var(--accent); }
    .sl-plot .penanda { stroke: var(--muted); stroke-dasharray: 3 4; }
    .sl-tip {
        position: absolute; pointer-events: none; z-index: 2; transform: translate(-50%, -110%);
        background: var(--surface-2); border: 1px solid var(--border); border-radius: 10px; padding: 8px 11px;
        font-size: .78rem; white-space: nowrap; box-shadow: 0 14px 30px -14px rgba(0,0,0,.9);
    }
    .sl-tip span { display: block; font-weight: 700; }
    .sl-tip b { display: block; font-size: .92rem; font-variant-numeric: tabular-nums; }
    .sl-tip[hidden] { display: none; }
    .sl-kosong { text-align: center; color: var(--muted); padding: 60px 20px; font-size: .92rem; }

    .sl-live { display: inline-flex; align-items: center; gap: 7px; font-size: .74rem; color: var(--muted); margin-top: 10px; }
    .sl-live .dot {
        width: 8px; height: 8px; border-radius: 50%; background: var(--ok);
        box-shadow: 0 0 0 3px var(--ok-soft, rgba(74,222,128,.12)); animation: slDenyut 2s ease-in-out infinite;
    }
    @keyframes slDenyut { 0%,100% { opacity: 1; } 50% { opacity: .35; } }
    @media (prefers-reduced-motion: reduce) { .sl-live .dot { animation: none; } }

    /* ---------- Tanggal 5 ---------- */
    .sl-lima { display: flex; align-items: flex-end; gap: 16px; overflow-x: auto; padding: 6px 2px 4px; min-height: 250px; }
    .sl-kol { flex: 0 0 auto; width: 82px; display: flex; flex-direction: column; align-items: center; gap: 6px; }
    .sl-kol .nilai { font-size: .74rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    .sl-kol .tiang { width: 34px; height: 170px; display: flex; align-items: flex-end; }
    .sl-kol .batang { width: 100%; border-radius: 7px 7px 0 0; background: var(--grad-bar); min-height: 3px; }
    .sl-kol .batang.kosong { background: none; border: 1px dashed var(--border); height: 100%; border-radius: 7px; }
    .sl-kol .bln { font-size: .72rem; font-weight: 700; color: var(--muted); white-space: nowrap; }
    .sl-kol .beda { font-size: .68rem; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sl-kol .pakai { font-size: .64rem; color: var(--muted); white-space: nowrap; }

</style>
@endpush

@section('content')
    @php
        $rp = fn (int $n) => \App\Models\SaldoHarian::rupiah($n);
        $teksBeda = fn ($n) => $n === null ? '—' : (($n > 0 ? '+' : ($n < 0 ? '−' : '')) . $rp(abs($n)));
        $kelasBeda = fn ($n) => $n === null ? '' : ($n > 0 ? 'naik' : ($n < 0 ? 'turun' : 'tetap'));
        $bolehHapus = auth()->user()->bolehHapusKeuangan();
        $isiBaru = ['tanggal' => $tglHariIni, 'saldo' => $hariIni?->saldo, '_judul' => 'Catat Saldo'];
    @endphp

    @include('admin.keuangan._kepala', [
        'sub'     => 'Saldo rekening harian, grafik diperbarui otomatis.',
        'catat'   => ['label' => 'Catat Saldo', 'dialog' => 'dlg-catat', 'isi' => $isiBaru],
        'lainnya' => [
            ['label' => 'Export CSV', 'href' => route('admin.keuangan.saldo.export'), 'ket' => 'Unduh semua saldo'],
            ['label' => 'Import CSV', 'dialog' => 'dlg-impor', 'ket' => 'Masukkan data lama sekaligus'],
        ],
    ])

    <div id="sl-akar" data-url="{{ route('admin.keuangan.saldo.data') }}">

    <div class="hero">
        <div class="hero-utama">
            <div class="lbl">Saldo rekening terakhir</div>
            <div class="angka" id="r-saldo">{{ $ringkas['saldo'] !== null ? $rp($ringkas['saldo']) : '—' }}</div>
            <div class="ket"><span id="r-tanggal">{{ $ringkas['tanggal'] ?? 'Belum ada catatan' }}</span></div>
        </div>
        <div class="card hero-stat">
            <div class="lbl">Perubahan</div>
            <div class="n {{ $kelasBeda($ringkas['perubahan']) }}" id="r-ubah">{{ $teksBeda($ringkas['perubahan']) }}</div>
            <div class="sub" id="r-ubah-sub">{{ $ringkas['tanggalSebelum'] ? 'dibanding ' . $ringkas['tanggalSebelum'] : 'dibanding catatan sebelumnya' }}</div>
        </div>
        <div class="card hero-stat">
            <div class="lbl">Dibanding tgl {{ \App\Models\SaldoHarian::TANGGAL_BANDING }}</div>
            <div class="n {{ $kelasBeda($ringkas['vsLima']) }}" id="r-lima">{{ $teksBeda($ringkas['vsLima']) }}</div>
            <div class="sub">bulan ini</div>
        </div>
    </div>

    <div class="ajak" id="r-ajak" @if ($ringkas['hariIniTercatat']) hidden @endif>
        <span>Saldo <b>hari ini</b> ({{ now()->translatedFormat('j F') }}) belum dicatat.</span>
        <button type="button" class="btn btn-accent btn-sm" data-buka="dlg-catat" data-isi="{{ json_encode($isiBaru) }}">Catat sekarang</button>
    </div>

    <div class="sl-dua">
        <div class="card sl-card">
            <div class="sl-top">
                <div>
                    <h2>Saldo Harian</h2>
                    <p>Arahkan kursor atau sentuh grafik untuk melihat angkanya.</p>
                </div>
                <div class="sl-rentang" role="group" aria-label="Rentang grafik">
                    <button type="button" data-rentang="30" aria-pressed="false">30 hari</button>
                    <button type="button" data-rentang="90" aria-pressed="true">3 bulan</button>
                    <button type="button" data-rentang="0" aria-pressed="false">Semua</button>
                </div>
            </div>
            <div class="sl-plot" id="sl-plot">
                <div class="sl-kosong">Memuat grafik…</div>
            </div>
            <span class="sl-live"><span class="dot"></span> Live — diperbarui otomatis <span id="sl-waktu"></span></span>
        </div>

        <div class="card sl-card">
            <div class="sl-top">
                <div>
                    <h2>Tiap Tanggal {{ \App\Models\SaldoHarian::TANGGAL_BANDING }}</h2>
                    <p>Sejak {{ \Illuminate\Support\Carbon::parse(\App\Models\SaldoHarian::MULAI_BANDING)->translatedFormat('F Y') }}; tanggal 5 kosong memakai saldo terakhir tgl 1–5.</p>
                </div>
            </div>
            <div class="sl-lima" id="sl-lima">
                <div class="sl-kosong" style="width:100%">Memuat grafik…</div>
            </div>
        </div>
    </div>

    </div>

    <div class="card keu-kartu">
        <div class="keu-kartu-kepala">
            <h2>Riwayat</h2>
            <span class="ket">31 catatan terakhir · klik baris untuk mengubah</span>
        </div>
        @if ($riwayat->isEmpty())
            <div class="keu-kosong">Belum ada saldo yang dicatat.</div>
        @else
            <div class="keu-tabel-wrap">
                <table class="keu-tabel">
                    <thead>
                        <tr><th>Tanggal</th><th class="num">Saldo</th><th class="num">Perubahan</th><th>Dicatat</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($riwayat as $i => $s)
                            @php
                                $lama = $riwayat->get($i + 1);
                                $beda = $lama ? $s->saldo - $lama->saldo : null;
                                $isi = ['tanggal' => $s->tanggal->toDateString(), 'saldo' => $s->saldo, '_kunci' => true,
                                    '_judul' => 'Ubah Saldo ' . $s->tanggal->translatedFormat('j M Y'),
                                    '_hapus' => $bolehHapus ? route('admin.keuangan.saldo.destroy', $s) : null,
                                    '_hapusTanya' => 'Hapus saldo ' . $s->tanggal->translatedFormat('j F Y') . '?'];
                            @endphp
                            <tr class="bisa-klik" tabindex="0" data-buka="dlg-catat" data-isi="{{ json_encode($isi) }}">
                                <td>{{ $s->tanggal->translatedFormat('D, j M Y') }}</td>
                                <td class="num"><b>{{ $rp($s->saldo) }}</b></td>
                                <td class="num {{ $kelasBeda($beda) }}">{{ $teksBeda($beda) }}</td>
                                <td class="redup">{{ $s->pencatat?->name ?? '—' }}</td>
                                <td class="pena">@include('partials.icon', ['name' => 'pensil', 'class' => 'ic-xs'])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Jendela catat / ubah saldo --}}
    <dialog class="keu-dlg" id="dlg-catat" data-judul-baru="Catat Saldo">
        <form method="POST" action="{{ route('admin.keuangan.saldo.store') }}">
            @csrf
            <input type="hidden" name="_dialog" value="dlg-catat">
            <div class="dlg-kepala">
                <h2 data-judul>Catat Saldo</h2>
                <button type="button" class="dlg-x" data-tutup aria-label="Tutup">@include('partials.icon', ['name' => 'x', 'class' => 'ic-sm'])</button>
            </div>
            <div class="dlg-isi">
                <div class="fld">
                    <label for="tanggal">Tanggal</label>
                    <input type="date" id="tanggal" name="tanggal" required max="{{ $tglHariIni }}" data-kunci value="{{ old('tanggal', $tglHariIni) }}">
                </div>
                <div class="fld">
                    <label for="saldo_tampil">Saldo rekening</label>
                    <div class="rp-input">
                        <span class="rp">Rp</span>
                        <input type="text" id="saldo_tampil" data-rupiah="saldo" inputmode="numeric" autocomplete="off" placeholder="0" required>
                    </div>
                    <input type="hidden" name="saldo" id="saldo" value="{{ old('saldo', $hariIni?->saldo) }}">
                </div>
                <div class="dlg-catatan">Mengisi tanggal yang sudah ada akan memperbarui saldonya.</div>
            </div>
            <div class="dlg-kaki">
                @if ($bolehHapus)
                    <button type="submit" form="hapus-saldo" class="btn btn-danger btn-sm" data-hapus hidden>Hapus</button>
                @endif
                <div class="kanan">
                    <button type="button" class="btn btn-ghost" data-tutup>Batal</button>
                    <button type="submit" class="btn btn-accent">Simpan</button>
                </div>
            </div>
        </form>
        @if ($bolehHapus)
            <form method="POST" id="hapus-saldo" class="dlg-hapus">@csrf @method('DELETE')</form>
        @endif
    </dialog>

    @include('admin.keuangan._impor', [
        'impor'  => route('admin.keuangan.saldo.import'),
        'kolom'  => 'tanggal, saldo',
        'contoh' => '2026-04-05,25000000',
    ])
@endsection

@push('scripts')
<script>
(function () {
    const akar = document.getElementById('sl-akar');
    if (!akar) return;

    const rp = n => (n < 0 ? '-Rp ' : 'Rp ') + Math.abs(n).toLocaleString('id-ID');
    const tanda = n => (n > 0 ? '+' : n < 0 ? '−' : '') + rp(Math.abs(n));
    const kelas = n => n > 0 ? 'naik' : (n < 0 ? 'turun' : 'tetap');
    const singkat = function (n) {
        const a = Math.abs(n), s = n < 0 ? '-' : '';
        if (a >= 1e9) return s + (a / 1e9).toFixed(a >= 1e10 ? 0 : 1).replace('.0', '') + ' M';
        if (a >= 1e6) return s + (a / 1e6).toFixed(a >= 1e8 ? 0 : 1).replace('.0', '') + ' jt';
        if (a >= 1e3) return s + Math.round(a / 1e3) + ' rb';
        return s + a;
    };
    const tgl = s => new Date(s + 'T00:00:00');
    const tglPendek = d => d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });

    // ---------- Ringkasan ----------
    function isiRingkas(r) {
        document.getElementById('r-saldo').textContent = r.saldo === null ? '—' : rp(r.saldo);
        document.getElementById('r-tanggal').textContent = r.tanggal || 'Belum ada catatan';
        const ubah = document.getElementById('r-ubah');
        ubah.textContent = r.perubahan === null ? '—' : tanda(r.perubahan);
        ubah.className = 'n ' + (r.perubahan === null ? '' : kelas(r.perubahan));
        document.getElementById('r-ubah-sub').textContent = r.tanggalSebelum ? 'dibanding ' + r.tanggalSebelum : 'dibanding catatan sebelumnya';
        const lima = document.getElementById('r-lima');
        lima.textContent = r.vsLima === null ? '—' : tanda(r.vsLima);
        lima.className = 'n ' + (r.vsLima === null ? '' : kelas(r.vsLima));
        document.getElementById('r-ajak').hidden = !!r.hariIniTercatat;
    }

    // ---------- Grafik saldo harian (garis) ----------
    const plot = document.getElementById('sl-plot');
    let deret = [];
    let rentang = 90;

    function gambarHarian() {
        let data = deret;
        if (rentang > 0 && data.length) {
            const batas = tgl(data[data.length - 1].tanggal);
            batas.setDate(batas.getDate() - rentang + 1);
            data = data.filter(d => tgl(d.tanggal) >= batas);
        }

        if (!data.length) {
            plot.innerHTML = '<div class="sl-kosong">Belum ada saldo yang dicatat. Tekan "Catat Saldo" untuk memulai.</div>';
            return;
        }

        const W = Math.max(300, plot.clientWidth), H = 260;
        const P = { l: 64, r: 16, t: 16, b: 30 };
        const t = data.map(d => tgl(d.tanggal).getTime());
        const t0 = t[0], t1 = t[t.length - 1];
        let lo = Math.min.apply(null, data.map(d => d.saldo));
        let hi = Math.max.apply(null, data.map(d => d.saldo));
        if (lo === hi) { lo = lo * 0.9; hi = hi * 1.1 || 1; }
        else { const pad = (hi - lo) * 0.12; lo = Math.max(0, lo - pad); hi += pad; }

        const x = v => t1 === t0 ? P.l + (W - P.l - P.r) / 2 : P.l + (v - t0) / (t1 - t0) * (W - P.l - P.r);
        const y = v => P.t + (hi - v) / (hi - lo) * (H - P.t - P.b);
        const titik = data.map((d, i) => [x(t[i]), y(d.saldo)]);

        let svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" role="img" aria-label="Grafik saldo harian"><g class="grid">';
        for (let i = 0; i <= 4; i++) {
            const v = lo + (hi - lo) * i / 4, yy = y(v).toFixed(1);
            svg += '<line x1="' + P.l + '" x2="' + (W - P.r) + '" y1="' + yy + '" y2="' + yy + '"></line>'
                + '<text x="' + (P.l - 8) + '" y="' + (+yy + 4) + '" text-anchor="end">' + singkat(Math.round(v)) + '</text>';
        }
        svg += '</g><g class="sumbu-x">';
        const label = Math.min(data.length, Math.max(2, Math.floor((W - P.l - P.r) / 90)));
        const sudah = new Set();
        for (let i = 0; i < label; i++) {
            const k = label === 1 ? 0 : Math.round(i * (data.length - 1) / (label - 1));
            if (sudah.has(k)) continue;
            sudah.add(k);
            svg += '<text x="' + titik[k][0].toFixed(1) + '" y="' + (H - 8) + '" text-anchor="middle">' + tglPendek(tgl(data[k].tanggal)) + '</text>';
        }
        svg += '</g>';

        const garis = titik.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' ');
        const bawah = (H - P.b).toFixed(1);
        svg += '<path class="area" d="' + garis + ' L' + titik[titik.length - 1][0].toFixed(1) + ' ' + bawah + ' L' + titik[0][0].toFixed(1) + ' ' + bawah + ' Z"></path>';
        svg += '<path class="garis" d="' + garis + '"></path>';
        if (data.length <= 60) {
            titik.forEach(function (p, i) {
                if (i < titik.length - 1) svg += '<circle class="titik" r="3" cx="' + p[0].toFixed(1) + '" cy="' + p[1].toFixed(1) + '"></circle>';
            });
        }
        const akhir = titik[titik.length - 1];
        svg += '<circle class="titik akhir" r="5" cx="' + akhir[0].toFixed(1) + '" cy="' + akhir[1].toFixed(1) + '"></circle>';
        svg += '<line class="penanda" id="sl-penanda" y1="' + P.t + '" y2="' + bawah + '" x1="0" x2="0" visibility="hidden"></line>';
        svg += '<circle class="titik akhir" id="sl-sorot" r="5" cx="0" cy="0" visibility="hidden"></circle>';
        svg += '<rect id="sl-sentuh" x="0" y="0" width="' + W + '" height="' + H + '" fill="transparent"></rect></svg>';

        plot.innerHTML = svg + '<div class="sl-tip" id="sl-tip" hidden></div>';

        const tip = document.getElementById('sl-tip');
        const penanda = document.getElementById('sl-penanda');
        const sorot = document.getElementById('sl-sorot');
        const sentuh = document.getElementById('sl-sentuh');

        function tunjuk(ev) {
            const kotak = plot.getBoundingClientRect();
            const px = (ev.clientX - kotak.left) * W / kotak.width;
            let k = 0;
            titik.forEach(function (p, i) { if (Math.abs(p[0] - px) < Math.abs(titik[k][0] - px)) k = i; });
            const p = titik[k];
            penanda.setAttribute('x1', p[0]); penanda.setAttribute('x2', p[0]);
            sorot.setAttribute('cx', p[0]); sorot.setAttribute('cy', p[1]);
            penanda.setAttribute('visibility', 'visible'); sorot.setAttribute('visibility', 'visible');
            const beda = k > 0 ? data[k].saldo - data[k - 1].saldo : null;
            tip.innerHTML = '';
            tip.appendChild(document.createTextNode(data[k].label));
            const b = document.createElement('b'); b.textContent = rp(data[k].saldo); tip.appendChild(b);
            if (beda !== null) {
                const s = document.createElement('span'); s.className = kelas(beda); s.textContent = tanda(beda); tip.appendChild(s);
            }
            tip.style.left = Math.min(Math.max(p[0] * kotak.width / W, 70), kotak.width - 70) + 'px';
            tip.style.top = (p[1] * kotak.height / H) + 'px';
            tip.hidden = false;
        }
        sentuh.addEventListener('pointermove', tunjuk);
        sentuh.addEventListener('pointerdown', tunjuk);
        sentuh.addEventListener('pointerleave', function () {
            tip.hidden = true;
            penanda.setAttribute('visibility', 'hidden');
            sorot.setAttribute('visibility', 'hidden');
        });
    }

    document.querySelectorAll('[data-rentang]').forEach(function (b) {
        b.addEventListener('click', function () {
            rentang = parseInt(b.dataset.rentang, 10);
            document.querySelectorAll('[data-rentang]').forEach(x => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
            gambarHarian();
        });
    });

    // ---------- Grafik tanggal 5 (batang per bulan) ----------
    function gambarLima(lima) {
        const el = document.getElementById('sl-lima');
        el.innerHTML = '';
        if (!lima.length) {
            el.innerHTML = '<div class="sl-kosong" style="width:100%">Perbandingan dimulai April 2026.</div>';
            return;
        }
        const maks = Math.max(1, Math.max.apply(null, lima.map(b => b.saldo || 0)));

        lima.forEach(function (b) {
            const kol = document.createElement('div'); kol.className = 'sl-kol';
            const n = document.createElement('div'); n.className = 'nilai';
            n.textContent = b.saldo === null ? '—' : singkat(b.saldo);
            if (b.saldo !== null) n.title = rp(b.saldo);

            const tiang = document.createElement('div'); tiang.className = 'tiang';
            const batang = document.createElement('div');
            if (b.saldo === null) {
                batang.className = 'batang kosong';
                batang.title = b.belum ? 'Tanggal 5 belum tiba' : 'Tidak ada catatan tanggal 1–5';
            } else {
                batang.className = 'batang';
                batang.style.height = Math.max(3, Math.round(b.saldo / maks * 170)) + 'px';
                batang.title = rp(b.saldo);
            }
            tiang.appendChild(batang);

            const bln = document.createElement('div'); bln.className = 'bln'; bln.textContent = b.label;
            const beda = document.createElement('div');
            beda.className = 'beda ' + (b.selisih === null ? 'tetap' : kelas(b.selisih));
            beda.textContent = b.selisih === null ? (b.belum ? 'belum tiba' : (b.saldo === null ? 'tidak dicatat' : 'awal')) : (b.selisih > 0 ? '▲ ' : b.selisih < 0 ? '▼ ' : '') + singkat(Math.abs(b.selisih));
            if (b.selisih !== null) beda.title = tanda(b.selisih) + ' dari bulan sebelumnya';

            // Baris "pakai" selalu ada (kosong bila tidak perlu) supaya semua
            // kolom sama tinggi dan dasar batangnya sejajar.
            const pakai = document.createElement('div'); pakai.className = 'pakai';
            pakai.textContent = (b.dipakai && b.dipakai !== b.tanggal) ? 'pakai ' + tglPendek(tgl(b.dipakai)) : '\u00a0';
            kol.append(n, tiang, bln, beda, pakai);
            el.appendChild(kol);
        });
        el.scrollLeft = el.scrollWidth;
    }

    // ---------- Muat & segarkan (live) ----------
    function muat() {
        return fetch(akar.dataset.url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(function (d) {
                deret = d.harian || [];
                gambarHarian();
                gambarLima(d.lima || []);
                isiRingkas(d.ringkas);
                document.getElementById('sl-waktu').textContent = '· ' + d.diperbarui;
            })
            .catch(function () {
                if (!deret.length) plot.innerHTML = '<div class="sl-kosong">Grafik gagal dimuat. Muat ulang halaman untuk mencoba lagi.</div>';
            });
    }

    muat();
    setInterval(function () { if (!document.hidden) muat(); }, 30000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) muat(); });

    let jeda;
    window.addEventListener('resize', function () { clearTimeout(jeda); jeda = setTimeout(gambarHarian, 150); });
})();
</script>
@endpush
