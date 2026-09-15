<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->nomor }} — {{ \App\Models\Invoice::HOTEL['nama'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /*
         * Dokumen ini dibuat untuk DICETAK, bukan dibaca di panel gelap.
         * Karena itu warnanya sengaja terang dan lepas dari tema aplikasi:
         * kertas putih, tinta hitam, dan satu aksen kuning dari logo.
         */
        :root {
            --tinta:   #12100f;
            --abu:     #6b6560;
            --garis:   #ddd8d2;
            --kuning:  #eeb04b;
            --lunas:   #1f8a4c;
            --belum:   #c2410c;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0; padding: 28px 18px 60px;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: #f3f1ee; color: var(--tinta);
            -webkit-font-smoothing: antialiased;
        }

        .lembar {
            width: 210mm; max-width: 100%; min-height: 297mm; margin: 0 auto;
            background: #fff; padding: 20mm 18mm 16mm;
            box-shadow: 0 26px 60px -30px rgba(0,0,0,.45);
            display: flex; flex-direction: column;
        }

        /* ---------- Kop surat ---------- */
        .kop { display: flex; gap: 18px; align-items: flex-start; border-bottom: 2px solid var(--tinta); padding-bottom: 16px; }
        .kop img { width: 92px; height: auto; flex: 0 0 auto; }
        .kop .ident { flex: 1 1 auto; min-width: 0; }
        .kop h1 { margin: 0 0 5px; font-size: 1.32rem; font-weight: 800; letter-spacing: -.02em; }
        .kop p { margin: 0; font-size: .78rem; line-height: 1.55; color: var(--abu); }
        .kop .telp { margin-top: 3px; font-variant-numeric: tabular-nums; }

        /* ---------- Judul & nomor ---------- */
        .judul-baris {
            display: flex; justify-content: space-between; align-items: flex-end;
            gap: 16px; margin: 22px 0 20px; flex-wrap: wrap;
        }
        .judul-baris h2 { margin: 0; font-size: 1.5rem; font-weight: 800; letter-spacing: .08em; }
        .judul-baris .meta { text-align: right; font-size: .8rem; color: var(--abu); line-height: 1.7; }
        .judul-baris .meta b { color: var(--tinta); font-variant-numeric: tabular-nums; }

        /* ---------- Data tamu ---------- */
        .kepada { margin-bottom: 20px; }
        .kepada .lbl { font-size: .68rem; letter-spacing: .1em; text-transform: uppercase; color: var(--abu); margin-bottom: 5px; }
        .kepada .nama { font-size: 1.06rem; font-weight: 700; }
        .kepada .sub { font-size: .82rem; color: var(--abu); margin-top: 3px; font-variant-numeric: tabular-nums; }

        /* ---------- Tabel rincian ---------- */
        table { width: 100%; border-collapse: collapse; font-size: .86rem; }
        thead th {
            text-align: left; padding: 10px 12px; font-size: .68rem; letter-spacing: .09em;
            text-transform: uppercase; color: #fff; background: var(--tinta); font-weight: 700;
        }
        thead th.ang, tbody td.ang { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        tbody td { padding: 13px 12px; border-bottom: 1px solid var(--garis); vertical-align: top; }
        tbody .item { font-weight: 700; }
        tbody .ket { font-size: .76rem; color: var(--abu); margin-top: 3px; }

        tfoot td { padding: 10px 12px; font-size: .88rem; }
        tfoot .total td {
            border-top: 2px solid var(--tinta); font-size: 1.06rem; font-weight: 800;
            padding-top: 14px;
        }
        tfoot .total td.ang { font-variant-numeric: tabular-nums; white-space: nowrap; }

        /* ---------- Keterangan ---------- */
        .catatan { margin-top: 22px; }
        .catatan .lbl { font-size: .68rem; letter-spacing: .1em; text-transform: uppercase; color: var(--abu); margin-bottom: 8px; }
        .cip { display: flex; gap: 8px; flex-wrap: wrap; }
        .cip span {
            font-size: .74rem; font-weight: 700; border-radius: 999px; padding: 5px 12px;
            border: 1px solid var(--garis); color: var(--abu);
        }
        .cip span.lunas { color: var(--lunas); border-color: var(--lunas); }
        .cip span.belum { color: var(--belum); border-color: var(--belum); }
        .catatan p { margin: 10px 0 0; font-size: .84rem; line-height: 1.6; color: var(--abu); white-space: pre-line; }

        /* ---------- Tanda tangan ---------- */
        .ttd-area { margin-top: auto; padding-top: 34px; display: flex; justify-content: flex-end; }
        .ttd { text-align: center; width: 230px; }
        .ttd .kota { font-size: .82rem; color: var(--abu); margin-bottom: 2px; }
        .ttd .an { font-size: .82rem; margin-bottom: 2px; }
        .ttd img { width: 130px; height: auto; margin: 0 auto; display: block; }
        .ttd .nama { border-top: 1px solid var(--tinta); padding-top: 6px; font-weight: 700; font-size: .88rem; }
        .ttd .jab { font-size: .76rem; color: var(--abu); margin-top: 2px; }

        .kaki { margin-top: 26px; padding-top: 12px; border-top: 1px solid var(--garis); font-size: .72rem; color: var(--abu); text-align: center; }

        /* ---------- Tombol layar (tidak ikut tercetak) ---------- */
        .bar { width: 210mm; max-width: 100%; margin: 0 auto 16px; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
        .bar a, .bar button {
            font-family: inherit; font-size: .88rem; font-weight: 700; cursor: pointer;
            border-radius: 999px; padding: 10px 20px; text-decoration: none; border: 1px solid var(--garis);
            background: #fff; color: var(--tinta);
        }
        .bar .utama { background: var(--tinta); color: #fff; border-color: var(--tinta); }

        @media print {
            @page { size: A4; margin: 0; }
            body { background: #fff; padding: 0; }
            .bar { display: none !important; }
            .lembar { box-shadow: none; width: auto; min-height: auto; padding: 16mm 15mm; }
        }
    </style>
</head>
<body>
    @php $hotel = \App\Models\Invoice::HOTEL; @endphp

    <div class="bar">
        <a href="{{ url()->previous() }}">Kembali</a>
        <button type="button" class="utama" onclick="window.print()">Cetak Invoice</button>
    </div>

    <div class="lembar">
        <div class="kop">
            <img src="{{ asset('img/logo-nyaman.png') }}" alt="Logo {{ $hotel['nama'] }}">
            <div class="ident">
                <h1>{{ $hotel['nama'] }}</h1>
                <p>{{ $hotel['alamat'] }}</p>
                <p class="telp">Telp. {{ $hotel['telepon'] }}</p>
            </div>
        </div>

        <div class="judul-baris">
            <h2>INVOICE</h2>
            <div class="meta">
                Nomor: <b>{{ $invoice->nomor }}</b><br>
                Tanggal: <b>{{ $invoice->created_at->translatedFormat('j F Y') }}</b>
            </div>
        </div>

        <div class="kepada">
            <div class="lbl">Kepada</div>
            <div class="nama">{{ $invoice->nama_tamu }}</div>
            @if ($invoice->tanggal_masuk && $invoice->tanggal_keluar)
                <div class="sub">
                    Menginap {{ $invoice->tanggal_masuk->translatedFormat('j F Y') }}
                    &ndash; {{ $invoice->tanggal_keluar->translatedFormat('j F Y') }}
                </div>
            @endif
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:64%">Uraian</th>
                    <th class="ang" style="width:36%">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoice->items as $item)
                    <tr>
                        <td>
                            <div class="item">{{ $item->uraian }}</div>
                            @if ($item->keterangan)
                                <div class="ket">{{ $item->baris() ? 'Nomor kamar: ' : '' }}{{ $item->keterangan }}</div>
                            @endif
                        </td>
                        <td class="ang">{{ \App\Models\Invoice::rupiah($item->subtotal) }}</td>
                    </tr>
                @empty
                    {{-- Invoice lama sebelum rincian dipisah ke barisnya sendiri. --}}
                    <tr>
                        <td>
                            <div class="item">Kamar {{ $invoice->tipe_kamar }}</div>
                            @if ($invoice->nomor_kamar)
                                <div class="ket">Nomor kamar: {{ $invoice->nomor_kamar }}</div>
                            @endif
                        </td>
                        <td class="ang">{{ \App\Models\Invoice::rupiah($invoice->total) }}</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="total">
                    <td>TOTAL</td>
                    <td class="ang">{{ \App\Models\Invoice::rupiah($invoice->total) }}</td>
                </tr>
            </tfoot>
        </table>

        <div class="catatan">
            <div class="lbl">Keterangan</div>
            <div class="cip">
                <span class="{{ $invoice->lunas ? 'lunas' : 'belum' }}">{{ $invoice->lunas ? 'LUNAS' : 'BELUM LUNAS' }}</span>
            </div>
            @if ($invoice->catatan)
                <p>{{ $invoice->catatan }}</p>
            @endif
        </div>

        <div class="ttd-area">
            <div class="ttd">
                <div class="kota">Magetan, {{ $invoice->created_at->translatedFormat('j F Y') }}</div>
                <div class="an">a.n. Manager</div>
                <img src="{{ asset('img/ttd-manager.png') }}" alt="Tanda tangan">
                <div class="nama">Manager</div>
                <div class="jab">{{ $hotel['nama'] }}</div>
            </div>
        </div>

        <div class="kaki">
            Terima kasih atas kunjungan Anda. Invoice ini sah tanpa memerlukan cap basah.
        </div>
    </div>
</body>
</html>
