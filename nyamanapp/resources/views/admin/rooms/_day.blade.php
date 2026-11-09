<div class="day-head">
    <h2>{{ $selected->translatedFormat('l, j F Y') }}</h2>
</div>

{{-- Empat angka: dua tentang penjualan kamar, dua tentang kedatangan tamu.
     Yang kedua inilah yang dicari saat panel dibuka di tengah hari —
     berapa tamu yang benar-benar sudah datang, dan berapa yang ditunggu. --}}
<div class="stats">
    <div class="stat isi">
        <div class="n">{{ $terisi }} <small>/ {{ $totalKamar }}</small></div>
        <div class="l">Kamar Terisi</div>
    </div>
    <div class="stat kosong">
        <div class="n">{{ $kosong }} <small>/ {{ $totalKamar }}</small></div>
        <div class="l">Kamar Kosong</div>
    </div>
    <div class="stat ci">
        <div class="n">{{ $sudahCi }} <small>/ {{ $entries->count() }}</small></div>
        <div class="l">Sudah Check-in</div>
    </div>
    <div class="stat belum">
        <div class="n">{{ $belumCi }} <small>/ {{ $entries->count() }}</small></div>
        <div class="l">Belum Check-in</div>
    </div>
</div>

@if ($sudahCo > 0)
    <div class="co-ring">
        @include('partials.icon', ['name' => 'check', 'class' => 'ic-xs'])
        <span><b>{{ $sudahCo }}</b> dari {{ $entries->count() }} kamar sudah check-out</span>
    </div>
@endif

@php $bfKamar = $entries->filter(fn ($b) => $b->breakfast)->count(); @endphp
@if ($entries->isNotEmpty())
    <div class="bf-ring">
        @include('partials.icon', ['name' => 'inbox', 'class' => 'ic-xs'])
        <span><b>{{ $bfKamar }}</b> dari {{ $entries->count() }} kamar terisi memesan breakfast</span>
    </div>
@endif

{{-- Filter nama tamu + Tiket.com / Umum / Breakfast / DP / Belum Lunas. --}}
@include('partials.filter-kamar')

@if ($entries->isEmpty())
    <div class="day-empty">Tidak ada kamar terisi pada tanggal ini.</div>
@else
    <div class="ent-list">
        @foreach ($dayGroups as $grup)
            @php
                /** @var \App\Models\Booking $utama */
                $utama   = $grup->first();
                $aktif   = $grup->reject(fn ($m) => $m->isCheckedOut());
                $sudah   = $grup->every(fn ($m) => $m->isCheckedIn());
                $adaCi   = $grup->contains(fn ($m) => $m->isCheckedIn());
                $keluar  = $grup->every(fn ($m) => $m->isCheckedOut());
                $banyak  = $grup->count() > 1;

                // Tanggal boleh berbeda antar kamar sejak masa menginap bisa
                // diperpanjang per kamar. Kalau berbeda, tanggalnya ditulis
                // per kamar — bukan satu rentang yang tidak berlaku untuk
                // semuanya.
                $tanggalSeragam = $grup->map(fn ($m) => $m->check_in->toDateString() . '|' . $m->check_out->toDateString())
                    ->unique()->count() === 1;
                $bfSeragam = $grup->map(fn ($m) => (int) $m->breakfast)->unique()->count() === 1;
                // Kamar pindahan punya check-in sendiri (saat tamunya pindah),
                // jadi tidak ikut tombol check-in pesanan.
                $ciBiasa   = $grup->filter(fn ($m) => ! $m->isCheckedIn() && ! $m->isCheckedOut() && ! $m->isPindahan());
                $pindahan  = $grup->filter(fn ($m) => $m->pindahanTertunda());
                // Check-out pesanan hanya untuk kamar yang sedang ditempati.
                // Kamar pertama yang "belum keluar" bisa saja belum check-in
                // (misalnya kamar pindahan), dan halamannya akan menolak.
                $didalam   = $grup->filter(fn ($m) => $m->isCheckedIn() && ! $m->isCheckedOut());
                // Kamar dalam satu pesanan bisa keluar pada waktu dan status berbeda;
                // kalau begitu rinciannya dibaca per kamar, bukan dari kamar pertama.
                $adaCo     = $grup->contains(fn ($m) => $m->isCheckedOut());
                $coBeda    = $grup->filter(fn ($m) => $m->isCheckedOut())
                    ->map(fn ($m) => $m->checked_out_at->format('YmdHi') . $m->checkout_status)->unique()->count() > 1;
                $coSeragam = ! $coBeda && ($keluar || ! $adaCo);
            @endphp
            <div class="ent {{ $keluar ? ($utama->checkoutAwal() ? 'awal' : ($utama->checkoutTelat() ? 'telat' : 'out')) : ($sudah ? 'done' : 'pending') }}"
                 data-kartu
                 data-cari="{{ trim($utama->nama_tamu . ' ' . $utama->telepon . ' ' . $grup->map(fn ($m) => $m->room?->nomor)->filter()->implode(' ')) }}"
                 data-filter="{{ implode(' ', \App\Support\FilterKamar::tag($grup)) }}">
                <button type="button" class="ent-top" aria-expanded="false">
                    <span class="ent-no">{{ $grup->map(fn ($m) => $m->room?->nomor ?? '—')->implode(' · ') }}</span>
                    @if ($banyak)
                        <span class="tipe-badge tipe-grup">{{ $grup->count() }} kamar</span>
                    @elseif ($utama->room)
                        <span class="tipe-badge tipe-{{ $utama->room->tipe }}">{{ $utama->room->labelTipe() }}</span>
                    @endif
                    <span class="ent-guest">{{ $utama->nama_tamu }}</span>
                    @if ($bfSeragam)
                        <span class="bf-badge {{ $utama->breakfast ? 'ada' : 'tanpa' }}">{{ $utama->breakfast ? 'Breakfast' : 'Tanpa BF' }}</span>
                    @else
                        <span class="bf-badge campur">Breakfast campur</span>
                    @endif
                    <span class="src-badge {{ $utama->dariTiket() ? 'tiket' : 'umum' }}">{{ $utama->labelSumber() }}</span>
                    @if ($utama->diskonPesanan() > 0)
                        <span class="src-badge diskon">{{ $utama->labelDiskon() }}</span>
                    @endif
                    @if ($utama->dp())
                        <span class="src-badge dp">DP</span>
                    @elseif ($utama->status_bayar === \App\Models\Booking::BAYAR_BELUM && ! $utama->dariTiket())
                        <span class="src-badge belum">Belum Lunas</span>
                    @endif
                    @if (($extend = (int) $grup->max('perpanjang_otomatis_malam')) > 0)
                        <span class="src-badge extend" title="Tamu masih di kamar lewat {{ \App\Models\Booking::JAM_LATE_SETENGAH }}, dihitung menambah malam">Extend +{{ $extend }}</span>
                    @endif
                    @if ($keluar)
                        <span class="st-badge {{ $utama->checkoutAwal() ? 'st-awal' : ($utama->checkoutTelat() ? 'st-telat' : 'st-out') }}">
                            @include('partials.icon', ['name' => 'log-out', 'class' => 'ic-xs']) {{ $coBeda ? 'Semua kamar check-out' : $utama->labelStatusCheckout() }}
                        </span>
                    @elseif ($adaCo)
                        <span class="st-badge st-sebagian">Sebagian check-out</span>
                    @elseif ($sudah)
                        <span class="st-badge st-done">@include('partials.icon', ['name' => 'check', 'class' => 'ic-xs']) Sudah Check-in</span>
                    @elseif ($adaCi)
                        <span class="st-badge st-sebagian">Sebagian check-in</span>
                    @else
                        <span class="st-badge st-pending">Belum Check-in</span>
                    @endif
                    <span class="ent-chev">@include('partials.icon', ['name' => 'chevron-down', 'class' => 'ic-xs'])</span>
                </button>

                <div class="ent-detail" hidden>
                    {{-- Aksi tingkat PESANAN: tamu datang dan pulang sekali.
                         Admin bisa mengerjakan seluruh langkah kapan pun,
                         dengan syarat foto yang sama persis seperti staff —
                         halaman yang dibuka pun halaman yang sama. --}}
                    <div class="ent-aksi">
                        @if ($ciBiasa->isNotEmpty())
                            <a href="{{ route('admin.rooms.checkin', $ciBiasa->first()) }}" class="btn btn-orange btn-sm">
                                @include('partials.icon', ['name' => 'check', 'class' => 'ic-xs'])
                                Check-in Tamu{{ $ciBiasa->count() > 1 ? ' (' . $ciBiasa->count() . ' kamar)' : '' }}
                            </a>
                        @endif
                        @foreach ($pindahan as $p)
                            <a href="{{ route('admin.rooms.checkin', $p) }}" class="btn btn-orange btn-sm">
                                @include('partials.icon', ['name' => 'log-out', 'class' => 'ic-xs'])
                                Check-in Pindahan ke {{ $p->room?->nomor }}
                            </a>
                        @endforeach

                        @if ($didalam->isNotEmpty())
                            <a href="{{ route('admin.rooms.checkout', $didalam->first()) }}" class="btn btn-orange btn-sm">
                                @include('partials.icon', ['name' => 'log-out', 'class' => 'ic-xs'])
                                Check-out{{ $didalam->count() > 1 ? ' (' . $didalam->count() . ' kamar)' : '' }}
                            </a>
                        @endif

                        {{-- Nota untuk pesanan ini, langsung dari panel admin —
                             isiannya terisi otomatis dari booking, sama seperti
                             tombol Invoice di panel staff. --}}
                        <a href="{{ route('admin.invoices.create', ['booking' => $utama->id]) }}" class="btn btn-ghost btn-sm">
                            @include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-xs']) Buat Invoice
                        </a>
                    </div>

                    {{-- ---------- Rincian per kamar ----------
                         Di sinilah kamar kembali dipisah. Satu kotak enak
                         dibaca, tetapi mengubah booking tetap per kamar:
                         101 boleh diperpanjang tanpa menyeret 103. --}}
                    <div class="kamar-list">
                        @foreach ($grup as $m)
                            <div class="kmr {{ $m->isCheckedOut() ? 'out' : ($m->isCheckedIn() ? 'done' : 'pending') }}">
                                <div class="kmr-head">
                                    <span class="kmr-no">{{ $m->room?->nomor ?? '—' }}</span>
                                    @if ($m->room)
                                        <span class="tipe-badge tipe-{{ $m->room->tipe }}">{{ $m->room->labelTipe() }}</span>
                                    @endif
                                    <span class="bf-badge {{ $m->breakfast ? 'ada' : 'tanpa' }}">{{ $m->breakfast ? 'Breakfast' : 'Tanpa BF' }}</span>
                                    <span class="kmr-harga mono">{{ \App\Models\Booking::rupiah($m->hargaMalam()) }}/malam</span>
                                </div>

                                <div class="kmr-tgl mono">
                                    {{ $m->check_in->translatedFormat('j M') }} &ndash;
                                    {{ $m->check_out->translatedFormat('j M Y') }}
                                    <span style="color: var(--muted)">· {{ $m->durasi }} malam ·
                                        {{ \App\Models\Booking::rupiah($m->totalKamar()) }}</span>
                                    @if ($m->isCheckedOut())
                                        <span class="kmr-st out">{{ $m->labelStatusCheckout() }}</span>
                                    @elseif ($m->isCheckedIn())
                                        <span class="kmr-st done">Sudah check-in</span>
                                    @elseif ($m->isPindahan())
                                        <span class="kmr-st pending">Menunggu tamu pindah</span>
                                    @else
                                        <span class="kmr-st pending">Belum check-in</span>
                                    @endif
                                </div>
                                @if ($m->isPindahan() || $m->pindahKe)
                                    <div class="kmr-tgl" style="color: var(--accent-2)">
                                        @if ($m->isPindahan())
                                            Pindahan dari kamar {{ $m->pindahDari?->room?->nomor ?? '—' }}.
                                        @endif
                                        @if ($m->pindahKe)
                                            Lanjut ke kamar {{ $m->pindahKe->room?->nomor ?? '—' }} mulai {{ $m->pindahKe->check_in->translatedFormat('j M') }}.
                                        @endif
                                    </div>
                                @endif

                                {{-- Pesanan beberapa kamar: jejak check-in, check-out, dan
                                     fotonya bisa berbeda, jadi ditulis per kamar. --}}
                                @if ($banyak && $m->isCheckedIn())
                                    <div class="kmr-tgl" style="color: var(--muted)">
                                        Check-in {{ $m->checked_in_at->translatedFormat('j M, H:i') }}
                                        @if ($m->isCheckedOut())
                                            · check-out {{ $m->checked_out_at->translatedFormat('j M, H:i') }}
                                            oleh {{ $m->checkoutBy?->name ?? '—' }} · {{ $m->labelKondisi() }}
                                        @endif
                                    </div>
                                    @if ($m->checkout_catatan)
                                        <div class="kmr-tgl" style="color: var(--muted)">Catatan: {{ $m->checkout_catatan }}</div>
                                    @endif
                                    <div class="kmr-aksi">
                                        @foreach ([
                                            ['KTP', $m->foto_ktp, 'ktp.show'],
                                            ['Kartu', $m->foto_kartu, 'kartu.show'],
                                            ['Bukti check-out', $m->foto_pembayaran_late, 'bayar.late'],
                                            ['Kerusakan', $m->foto_kerusakan, 'kerusakan.show'],
                                        ] as [$namaFoto, $adaFoto, $ruteFoto])
                                            @if ($adaFoto)
                                                <a href="{{ route($ruteFoto, $m) }}" data-lightbox="{{ route($ruteFoto, $m) }}" class="btn btn-ghost btn-sm">{{ $namaFoto }}</a>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                                <div class="kmr-aksi">
                                    {{-- Satu tombol Edit; ubah data dan pindah kamar
                                         dipisah di dalamnya. --}}
                                    <details class="menu-edit dalam">
                                        <summary class="btn btn-accent btn-sm">
                                            @include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-xs'])
                                            Edit
                                            @include('partials.icon', ['name' => 'chevron-down', 'class' => 'ic-xs'])
                                        </summary>
                                        <div class="menu-edit-isi">
                                            <a href="{{ route('admin.rooms.edit', $m) }}">
                                                <b>@include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-xs']) {{ auth()->user()->bolehHapusBooking() ? 'Ubah / Hapus Booking' : 'Ubah Booking' }}</b>
                                                <span>Nama, telepon, tanggal, sarapan, pembayaran.</span>
                                            </a>
                                            @unless ($m->isCheckedOut())
                                                <a href="{{ route('admin.rooms.pindah', $m) }}">
                                                    <b>@include('partials.icon', ['name' => 'bed', 'class' => 'ic-xs']) Pindah Kamar{{ $aktif->count() > 1 ? ' (' . $aktif->count() . ' kamar)' : '' }}</b>
                                                    <span>Seluruh masa atau mulai malam tertentu; semua kamar pesanan bisa dipilih.</span>
                                                </a>
                                            @endunless
                                        </div>
                                    </details>

                                    {{-- Aksi per kamar hanya ditawarkan saat pesanannya
                                         memang punya lebih dari satu kamar. --}}
                                    @if ($banyak && ! $m->isCheckedIn() && ! $m->isPindahan())
                                        <a href="{{ route('admin.rooms.checkin', ['booking' => $m, 'scope' => 'one']) }}" class="btn btn-ghost btn-sm">
                                            Check-in kamar ini
                                        </a>
                                    @endif
                                    @if ($banyak && $m->isCheckedIn() && ! $m->isCheckedOut())
                                        <a href="{{ route('admin.rooms.checkout', ['booking' => $m, 'scope' => 'one']) }}" class="btn btn-ghost btn-sm">
                                            Check-out kamar ini
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @php $b = $utama; @endphp
                    <dl>
                        <dt>Nama Tamu</dt>
                        <dd>{{ $b->nama_tamu }}</dd>

                        <dt>Telepon</dt>
                        <dd class="mono">{{ $b->telepon }}</dd>

                        @if ($tanggalSeragam)
                            <dt>Check-in</dt>
                            <dd>{{ $b->check_in->translatedFormat('l, j F Y') }}</dd>

                            <dt>Durasi</dt>
                            <dd>{{ $b->durasi }} malam</dd>

                            <dt>Check-out</dt>
                            <dd>{{ $b->check_out->translatedFormat('l, j F Y') }}</dd>
                        @else
                            <dt>Masa Menginap</dt>
                            <dd style="color: var(--warn)">
                                Berbeda antar kamar — lihat rincian per kamar di atas.
                            </dd>
                        @endif

                        <dt>Waktu Check-in</dt>
                        <dd>
                            @if ($sudah)
                                {{ $b->checked_in_at->translatedFormat('j F Y, H:i') }}
                                @if ($b->earlyCheckin())
                                    <span style="color: var(--alert)"> — {{ $b->labelEarlyCheckin() }}</span>
                                @endif
                            @elseif ($adaCi)
                                <span style="color: var(--warn)">Berbeda antar kamar — lihat rincian per kamar di atas.</span>
                            @else
                                <span style="color: var(--accent-2-600)">Tamu belum check-in</span>
                            @endif
                        </dd>

                        <dt>Waktu Check-out</dt>
                        <dd>
                            @if (! $coSeragam)
                                <span style="color: var(--warn)">Berbeda antar kamar — lihat rincian per kamar di atas.</span>
                            @elseif ($b->isCheckedOut())
                                {{ $b->checked_out_at->translatedFormat('j F Y, H:i') }}
                                <span style="color: {{ $b->checkoutAwal() ? 'var(--alert)' : ($b->checkoutTelat() ? 'var(--accent-2-600)' : 'var(--ok)') }}">
                                    — {{ $b->labelStatusCheckout() }}
                                </span>
                            @else
                                <span style="color: var(--muted)">Belum di-check-out (batas {{ $b->batasCheckout()->translatedFormat('j F, H:i') }})</span>
                            @endif
                        </dd>

                        @if ($coSeragam && $b->isCheckedOut())
                            <dt>Diproses Oleh</dt>
                            <dd>{{ $b->checkoutBy?->name ?? '—' }}</dd>

                            @if ($b->checkout_catatan)
                                <dt>Catatan Check-out</dt>
                                <dd>{{ $b->checkout_catatan }}</dd>
                            @endif
                        @endif

                        @if ($bfSeragam)
                            <dt>Sarapan</dt>
                            <dd>{{ $b->labelBreakfast() }}</dd>
                        @endif

                        <dt>Asal Pesanan</dt>
                        <dd>{{ $b->labelSumber() }}</dd>

                        @php
                            // Seluruh kamar pesanan, bukan hanya yang menginap malam ini.
                            $totalGrup = (int) ($b->grup_total ?? $grup->sum(fn ($m) => $m->totalKamar()));
                            $tagihan   = $b->tagihanPesanan($totalGrup);
                        @endphp

                        <dt>Pembayaran</dt>
                        <dd style="color: {{ $b->lunas() ? 'var(--ok)' : ($b->dp() ? 'var(--warn)' : 'var(--alert)') }}">
                            {{ $b->ringkasanBayar($totalGrup) }}
                        </dd>

                        <dt>Total Kamar</dt>
                        <dd class="mono">
                            {{ \App\Models\Booking::rupiah($totalGrup) }}
                            @if ($b->diskonPesanan() > 0)
                                <span style="color: var(--ok)">
                                    &minus;{{ \App\Models\Booking::rupiah($b->diskonPesanan()) }}{{ $b->diskonPersen() > 0 ? ' (' . $b->diskonPersen() . '%)' : '' }}
                                </span>
                                <span style="color: var(--muted)">&rarr; tagihan {{ \App\Models\Booking::rupiah($tagihan) }}</span>
                            @endif
                        </dd>

                        @if ($coSeragam && $b->isCheckedOut())
                            <dt>Kondisi Kamar</dt>
                            <dd style="color: {{ $b->checkout_kondisi_ok ? 'var(--ok)' : 'var(--alert)' }}">
                                {{ $b->labelKondisi() }}
                                @if ($b->report_id)
                                    <span style="color: var(--muted)">— tiket #{{ $b->report_id }}</span>
                                @endif
                            </dd>
                        @endif

                        <dt>Kamar Pesanan</dt>
                        <dd class="mono">{{ $b->grup_rooms ?: ($grup->map(fn ($m) => $m->room?->nomor)->filter()->implode(', ') ?: '—') }}</dd>

                        <dt>Diinput Oleh</dt>
                        <dd>{{ $b->creator?->name ?? '—' }}</dd>
                    </dl>

                    <div class="foto-row">
                        @if ($b->foto_ktp)
                            <div class="foto-item">
                                <div class="foto-lbl">KTP saat check-in</div>
                                <img src="{{ route('ktp.show', $b) }}"
                                     alt="Foto KTP {{ $b->nama_tamu }}"
                                     data-lightbox="{{ route('ktp.show', $b) }}">
                            </div>
                        @else
                            <div class="ktp-none">Belum ada foto KTP — tamu belum melakukan check-in.</div>
                        @endif

                        @if ($b->foto_bayar_awal)
                            <div class="foto-item">
                                <div class="foto-lbl">Bukti bayar saat pesanan dibuat</div>
                                <img src="{{ route('bayar.awal', $b) }}"
                                     alt="Bukti pembayaran awal {{ $b->nama_tamu }}"
                                     data-lightbox="{{ route('bayar.awal', $b) }}">
                            </div>
                        @elseif ($b->wajibBuktiBayarAwal() && ! \App\Models\Booking::adaBuktiBayarDi($b->grup_semua ?? $grup))
                            <div class="ktp-none">Ditandai {{ $b->labelBayar() }} tanpa bukti bayar — pesanan lama, sebelum bukti diwajibkan.</div>
                        @endif

                        @if ($b->foto_pelunasan)
                            <div class="foto-item">
                                <div class="foto-lbl">Bukti pelunasan saat check-in</div>
                                <img src="{{ route('bayar.show', $b) }}"
                                     alt="Bukti pelunasan {{ $b->nama_tamu }}"
                                     data-lightbox="{{ route('bayar.show', $b) }}">
                            </div>
                        @endif

                        @if ($coSeragam && $b->foto_kartu)
                            <div class="foto-item">
                                <div class="foto-lbl">Kartu kembali saat check-out</div>
                                <img src="{{ route('kartu.show', $b) }}"
                                     alt="Foto kartu kunci {{ $b->nama_tamu }}"
                                     data-lightbox="{{ route('kartu.show', $b) }}">
                            </div>
                        @elseif ($b->isCheckedOut())
                            <div class="ktp-none">Check-out tercatat tanpa foto kartu.</div>
                        @endif

                        @foreach (($b->grup_semua ?? $grup)->filter(fn ($m) => $m->foto_bayar_tambahan) as $t)
                            <div class="foto-item">
                                <div class="foto-lbl">Bayar selisih — kamar {{ $t->room?->nomor }}</div>
                                <img src="{{ route('bayar.tambahan', $t) }}"
                                     alt="Bukti bayar selisih {{ $t->nama_tamu }}"
                                     data-lightbox="{{ route('bayar.tambahan', $t) }}">
                            </div>
                        @endforeach
                        @if ($coSeragam && $b->foto_pembayaran_late)
                            <div class="foto-item">
                                <div class="foto-lbl">Pembayaran saat check-out</div>
                                <img src="{{ route('bayar.late', $b) }}"
                                     alt="Pembayaran late {{ $b->nama_tamu }}"
                                     data-lightbox="{{ route('bayar.late', $b) }}">
                            </div>
                        @endif

                        @if ($coSeragam && $b->foto_kerusakan)
                            <div class="foto-item">
                                <div class="foto-lbl">Kerusakan / barang tertinggal</div>
                                <img src="{{ route('kerusakan.show', $b) }}"
                                     alt="Kerusakan kamar {{ $b->room?->nomor }}"
                                     data-lightbox="{{ route('kerusakan.show', $b) }}">
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="filter-kosong" id="filter-kosong" hidden>Tidak ada tamu yang cocok dengan filter.</div>
@endif
