<div class="day-head">
    <h2>Booking untuk {{ $selected->translatedFormat('l, j F Y') }}</h2>
</div>

{{-- Ringkasan hunian: satu baris kecil --}}
<div class="occ-line">
    <span class="pill isi">Terisi <b>{{ $terisi }}/{{ $totalKamar }}</b></span>
    <span class="pill kosong">Kosong <b>{{ $kosong }}</b></span>
    @foreach (['king' => 'King', 'twin' => 'Twin', 'family' => 'Family'] as $tipe => $label)
        @php
            $tot = $totalPerTipe->get($tipe, 0);
            $isi = $terisiPerTipe->get($tipe, 0);
        @endphp
        @if ($tot > 0)
            <span class="pill">{{ $label }} <b>{{ max(0, $tot - $isi) }}/{{ $tot }}</b> kosong</span>
        @endif
    @endforeach
    @php $bfKamar = $dayGroups->flatten()->filter(fn ($b) => $b->breakfast)->count(); @endphp
    @if ($terisi > 0)
        <span class="pill bf">Breakfast <b>{{ $bfKamar }}</b> kamar</span>
    @endif
</div>

{{-- Filter nama tamu + Tiket.com / Umum / Breakfast / DP / Belum Lunas. --}}
@include('partials.filter-kamar')

@if ($dayGroups->isEmpty())
    <div class="day-empty">Tidak ada kamar terisi pada tanggal ini.</div>
@else
    <div class="bk-list">
        @foreach ($dayGroups as $grup)
            @php
                /** @var \App\Models\Booking $utama */
                $utama    = $grup->first();
                // Status dibaca dari SEMUA kamar, bukan kamar pertama: tamu dalam
                // satu pesanan bisa datang dan pulang pada waktu berbeda.
                $sudah    = $grup->every(fn ($m) => $m->isCheckedIn());
                $adaCi    = $grup->contains(fn ($m) => $m->isCheckedIn());
                $keluar   = $grup->every(fn ($m) => $m->isCheckedOut());
                $adaCo    = $grup->contains(fn ($m) => $m->isCheckedOut());
                $didalam  = $grup->filter(fn ($m) => $m->isCheckedIn() && ! $m->isCheckedOut());
                $aktifKm  = $grup->first(fn ($m) => ! $m->isCheckedOut()) ?? $utama;
                $jumlahKm = $grup->count();
                $coBeda   = $grup->filter(fn ($m) => $m->isCheckedOut())
                    ->map(fn ($m) => $m->checked_out_at->format('YmdHi') . $m->checkout_status)->unique()->count() > 1;
                $campur   = $grup->map(fn ($m) => $m->isCheckedOut() ? 'keluar' : ($m->isCheckedIn() ? 'menginap' : 'menunggu'))
                    ->unique()->count() > 1;
                $perKamar = $campur || $coBeda;
                // Kamar pindahan di-check-in sendiri saat tamunya pindah.
                $ciBiasa  = $grup->filter(fn ($m) => ! $m->isCheckedIn() && ! $m->isCheckedOut() && ! $m->isPindahan());
                $pindahan = $grup->filter(fn ($m) => $m->pindahanTertunda());
            @endphp
            @php
                $kunciCari = trim($utama->nama_tamu . ' ' . $utama->telepon . ' '
                    . $grup->map(fn ($b) => $b->room?->nomor)->filter()->implode(' '));
            @endphp
            <div class="bk {{ $keluar ? 'out' : ($didalam->isNotEmpty() ? 'done' : 'pending') }}"
                 data-kartu data-cari="{{ $kunciCari }}" data-filter="{{ implode(' ', \App\Support\FilterKamar::tag($grup)) }}">
                <div class="bk-main">
                    <div class="bk-rooms">
                        @foreach ($grup as $b)
                            <span class="bk-room">
                                <span class="no">{{ $b->room?->nomor ?? '—' }}</span>
                                @if ($b->room)
                                    <span class="tipe-badge tipe-{{ $b->room->tipe }}">{{ $b->room->labelTipe() }}</span>
                                @endif
                                @if ($campur)
                                    <span class="km-st {{ $b->isCheckedOut() ? 'keluar' : ($b->isCheckedIn() ? 'menginap' : '') }}">
                                        {{ $b->isCheckedOut() ? 'Sudah CO' : ($b->isCheckedIn() ? 'Menginap' : 'Belum CI') }}
                                    </span>
                                @endif
                            </span>
                        @endforeach
                        @if ($jumlahKm > 1)
                            <span class="tipe-badge tipe-king">{{ $jumlahKm }} kamar · 1 pesanan</span>
                        @endif
                        <span class="bf-badge {{ $utama->breakfast ? 'ada' : 'tanpa' }}">{{ $utama->labelBreakfast() }}</span>
                    </div>

                    <div class="bk-guest">
                        {{ $utama->nama_tamu }}
                        <span class="sumber-tag {{ $utama->dariTiket() ? 'tiket' : 'umum' }}">{{ $utama->labelSumber() }}</span>
                        <span class="sumber-tag {{ $utama->lunas() ? 'lunas' : 'belum' }}">{{ $utama->labelBayar() }}</span>
                        @if ($utama->diskonPesanan() > 0)
                            <span class="sumber-tag diskon">{{ $utama->labelDiskon() }}</span>
                        @endif
                        @php $extend = (int) $grup->max('perpanjang_otomatis_malam'); @endphp
                        @if ($extend > 0)
                            <span class="sumber-tag extend" title="Tamu masih di kamar lewat {{ \App\Models\Booking::JAM_LATE_SETENGAH }}, dihitung menambah malam">Extend otomatis +{{ $extend }} malam</span>
                        @endif
                    </div>
                    @foreach ($grup as $b)
                        @if ($b->isPindahan() || $b->pindahKe)
                            <div class="bk-meta" style="color: var(--accent-2)">
                                Kamar {{ $b->room?->nomor }}:
                                @if ($b->isPindahan())
                                    pindahan dari kamar {{ $b->pindahDari?->room?->nomor ?? '—' }}.
                                @endif
                                @if ($b->pindahKe)
                                    lanjut ke kamar {{ $b->pindahKe->room?->nomor ?? '—' }} mulai {{ $b->pindahKe->check_in->translatedFormat('j M') }}.
                                @endif
                            </div>
                        @endif
                    @endforeach
                    <div class="bk-meta">
                        @if ($utama->linkWa())
                            <a class="tel wa-link" href="{{ $utama->linkWa() }}" target="_blank" rel="noopener" title="Chat WhatsApp">{{ $utama->telepon }}</a> ·
                        @else
                            <span class="tel">{{ $utama->labelTelepon() }}</span> ·
                        @endif
                        {{ $utama->check_in->translatedFormat('j M Y') }} → {{ $utama->check_out->translatedFormat('j M Y') }}
                        ({{ $utama->durasi }} malam)
                        @if ($sudah)
                            · check-in {{ $utama->checked_in_at->translatedFormat('j M, H:i') }}
                        @endif
                        @if ($keluar && ! $perKamar)
                            · check-out {{ $utama->checked_out_at->translatedFormat('j M, H:i') }}
                        @endif
                    </div>
                    @if ($perKamar)
                        @foreach ($grup as $b)
                            <div class="bk-meta">
                                Kamar {{ $b->room?->nomor }}:
                                @if ($b->isCheckedOut())
                                    check-out {{ $b->checked_out_at->translatedFormat('j M, H:i') }} — {{ $b->labelStatusCheckout() }}
                                @elseif ($b->isCheckedIn())
                                    masih menginap, batas {{ $b->batasCheckout()->translatedFormat('j M, H:i') }}
                                @else
                                    belum check-in
                                @endif
                            </div>
                        @endforeach
                    @endif

                    {{-- Detail booking & pembayaran untuk staff: harga per kamar,
                         total, diskon, DP, dan sisa — dihitung dari SELURUH
                         kamar pesanan, bukan hanya yang menginap malam ini. --}}
                    @php
                        $semuaKm  = ($utama->grup_semua ?? $grup)->sortBy(fn ($m) => $m->check_in->format('Ymd') . sprintf('%06d', (int) preg_replace('/\D/', '', (string) $m->room?->nomor)));
                        $totalKm  = \App\Models\Booking::totalPesananDari($semuaKm);
                        $tagihanK = $utama->tagihanPesanan($totalKm);
                        $dibayarK = $utama->dibayar($totalKm);
                        $sisaK    = $utama->sisaBayar($totalKm);
                    @endphp
                    <details class="bk-detail">
                        <summary>
                            @include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-xs'])
                            Detail booking &amp; pembayaran
                            <span class="ringkas">
                                Tagihan {{ \App\Models\Booking::rupiah($tagihanK) }}
                                @if ($sisaK > 0) · sisa <b>{{ \App\Models\Booking::rupiah($sisaK) }}</b> @else · lunas @endif
                            </span>
                        </summary>
                        <div class="bk-detail-isi">
                            <div class="bk-tabel">
                                @foreach ($semuaKm as $m)
                                    <div class="bk-baris">
                                        <span class="km">
                                            <b>{{ $m->room?->nomor ?? '—' }}</b>
                                            {{ $m->room?->labelTipe() }} · {{ $m->breakfast ? 'Breakfast' : 'Tanpa BF' }}
                                            @if ($m->isPindahan()) · pindahan @endif
                                            @if ($m->isCheckedOut()) · {{ $m->labelStatusCheckout() }} @endif
                                        </span>
                                        <span class="tg">
                                            {{ $m->check_in->translatedFormat('j M') }} – {{ $m->check_out->translatedFormat('j M') }}
                                            · {{ $m->durasi }} mlm × {{ \App\Models\Booking::rupiah($m->hargaMalam()) }}
                                        </span>
                                        <span class="rp">{{ \App\Models\Booking::rupiah($m->totalKamar()) }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <dl class="bk-uang">
                                <dt>Total kamar</dt><dd>{{ \App\Models\Booking::rupiah($totalKm) }}</dd>
                                @if ($utama->diskonPesanan() > 0)
                                    <dt>Diskon</dt><dd class="hijau">&minus;{{ \App\Models\Booking::rupiah($utama->diskonPesanan()) }}{{ $utama->diskonPersen() > 0 ? ' (' . $utama->diskonPersen() . '%)' : '' }}</dd>
                                @endif
                                <dt>Tagihan</dt><dd><b>{{ \App\Models\Booking::rupiah($tagihanK) }}</b></dd>
                                <dt>{{ $utama->dp() ? 'DP diterima' : 'Sudah dibayar' }}</dt><dd>{{ \App\Models\Booking::rupiah($dibayarK) }}</dd>
                                <dt>Sisa</dt><dd class="{{ $sisaK > 0 ? 'merah' : 'hijau' }}"><b>{{ \App\Models\Booking::rupiah($sisaK) }}</b></dd>
                                <dt>Status</dt><dd>{{ $utama->labelBayar() }} · {{ $utama->labelSumber() }}</dd>
                                <dt>Diinput</dt><dd>{{ $utama->creator?->name ?? '—' }}, {{ $utama->created_at?->translatedFormat('j M Y H:i') }}</dd>
                            </dl>
                            @php
                                $bukti = collect([
                                    [$utama->dariTiket() ? 'E-tiket' : 'Bukti bayar awal', $utama->foto_bayar_awal ? route('bayar.awal', $utama) : null],
                                    ['Bukti pelunasan', $utama->foto_pelunasan ? route('bayar.show', $utama) : null],
                                ])->merge($semuaKm->filter(fn ($m) => $m->foto_bayar_tambahan)
                                    ->map(fn ($m) => ['Bayar selisih ' . $m->room?->nomor, route('bayar.tambahan', $m)]))
                                  ->merge($semuaKm->filter(fn ($m) => $m->foto_pembayaran_late)->unique('foto_pembayaran_late')
                                    ->map(fn ($m) => ['Bukti check-out ' . $m->room?->nomor, route('bayar.late', $m)]))
                                  ->filter(fn ($x) => $x[1]);
                            @endphp
                            @if ($bukti->isNotEmpty())
                                <div class="bk-bukti">
                                    @foreach ($bukti as [$lbl, $url])
                                        <a href="{{ $url }}" data-lightbox="{{ $url }}" class="btn btn-ghost btn-sm">{{ $lbl }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </details>
                </div>

                <div class="bk-side">
                    @if ($keluar && $coBeda)
                        <span class="st-badge st-out">Semua kamar check-out</span>
                    @elseif ($keluar)
                        <span class="st-badge {{ $utama->checkoutAwal() ? 'st-awal' : ($utama->checkoutTelat() ? 'st-telat' : 'st-out') }}">
                            {{ $utama->labelStatusCheckout() }}
                        </span>
                    @elseif ($adaCo)
                        <span class="st-badge st-sebagian">Sebagian check-out</span>
                    @elseif ($sudah)
                        <span class="st-badge st-done">@include('partials.icon', ['name' => 'check', 'class' => 'ic-xs']) Sudah Check-in</span>
                    @elseif ($adaCi)
                        <span class="st-badge st-sebagian">Sebagian check-in</span>
                    @elseif ($utama->isPindahan())
                        <span class="st-badge st-pending">Menunggu tamu pindah</span>
                    @else
                        <span class="st-badge st-pending">Belum Check-in</span>
                    @endif

                    @if ($utama->earlyCheckin())
                        <span class="st-badge st-awal">{{ $utama->labelEarlyCheckin() }}</span>
                    @endif

                    @php $tertunda = $aktifKm->permintaanTertunda(); @endphp

                    @if ($tertunda)
                        <div class="bk-tertunda">
                            @include('partials.icon', ['name' => 'clock', 'class' => 'ic-xs'])
                            Menunggu persetujuan admin: <b>{{ $tertunda->labelJenis() }}</b>
                        </div>
                    @endif

                    <div class="bk-actions">
                        {{-- Tombol check-in: satu kali per GRUP, hanya bila belum check-in --}}
                        @if ($ciBiasa->isNotEmpty())
                            <a href="{{ route('staff.rooms.checkin', $ciBiasa->first()) }}" class="btn btn-orange btn-sm">
                                Tamu Check-in{{ $ciBiasa->count() > 1 ? ' (' . $ciBiasa->count() . ' kamar)' : '' }}
                            </a>
                        @endif

                        {{-- Tamu pindah kamar per hari: check-in ke kamar baru
                             sekaligus menutup kamar lamanya. --}}
                        @foreach ($pindahan as $p)
                            <a href="{{ route('staff.rooms.checkin', $p) }}" class="btn btn-orange btn-sm">
                                Check-in Pindahan ke {{ $p->room?->nomor }}
                            </a>
                        @endforeach

                        {{-- Satu tombol Edit, isinya dipisah: pindah kamar dikerjakan
                             langsung (semua kamar pesanan bisa dipilih), perubahan
                             lain diajukan dan diputus admin. --}}
                        @php
                            $belumKeluar = ($utama->grup_semua ?? $grup)->reject(fn ($m) => $m->isCheckedOut());
                            $bisaPindah  = $grup->contains(fn ($m) => ! $m->isCheckedOut());
                            $bisaAjukan  = ! ($keluar || $tertunda);
                        @endphp
                        @if ($bisaPindah || $bisaAjukan)
                            <details class="menu-edit">
                                <summary class="btn btn-ghost btn-sm">
                                    @include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-xs'])
                                    Edit
                                    @include('partials.icon', ['name' => 'chevron-down', 'class' => 'ic-xs'])
                                </summary>
                                <div class="menu-edit-isi">
                                    @if ($bisaPindah)
                                        <a href="{{ route('staff.rooms.pindah', $grup->first(fn ($m) => ! $m->isCheckedOut())) }}">
                                            <b>@include('partials.icon', ['name' => 'bed', 'class' => 'ic-xs']) Pindah Kamar{{ $belumKeluar->count() > 1 ? ' (' . $belumKeluar->count() . ' kamar)' : '' }}</b>
                                            <span>Langsung diterapkan, tanpa persetujuan.</span>
                                        </a>
                                    @endif
                                    @if ($bisaAjukan)
                                        <a href="{{ route('staff.rooms.perubahan', $aktifKm) }}">
                                            <b>@include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-xs']) Ajukan Perubahan</b>
                                            <span>Nama, telepon, tanggal, perpanjang — diputus admin.</span>
                                        </a>
                                    @endif
                                </div>
                            </details>
                        @endif

                        {{-- Check-out hanya masuk akal setelah tamu benar-benar masuk. --}}
                        @if ($didalam->isNotEmpty())
                            <a href="{{ route('staff.rooms.checkout', $didalam->first()) }}" class="btn btn-accent btn-sm">
                                @include('partials.icon', ['name' => 'log-out', 'class' => 'ic-xs'])
                                Tamu Check-out{{ $didalam->count() > 1 ? ' (' . $didalam->count() . ' kamar)' : '' }}
                            </a>
                        @endif

                        @php $kartuKm = $grup->filter(fn ($m) => $m->isCheckedOut() && $m->foto_kartu)->unique('foto_kartu'); @endphp
                        @foreach ($kartuKm as $m)
                            <a href="{{ route('kartu.show', $m) }}" class="btn btn-ghost btn-sm"
                               data-lightbox="{{ route('kartu.show', $m) }}">
                                @include('partials.icon', ['name' => 'key-card', 'class' => 'ic-xs']) Foto Kartu{{ $kartuKm->count() > 1 ? ' ' . $m->room?->nomor : '' }}
                            </a>
                        @endforeach

                        {{-- Hapus tidak lagi berdiri sendiri di baris ini: tombolnya
                             ada di halaman Edit, supaya tidak tertekan tanpa sengaja. --}}
                        <a href="{{ route('staff.invoices.create', ['booking' => $utama->id]) }}" class="btn btn-ghost btn-sm">
                            @include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-xs']) Invoice
                        </a>

                        {{-- Ubah/hapus pesanan kini wewenang admin: staff mencatat
                             dan memproses, koreksi data lewat pengawas. --}}
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="filter-kosong" id="filter-kosong" hidden>Tidak ada tamu yang cocok dengan filter.</div>
@endif
