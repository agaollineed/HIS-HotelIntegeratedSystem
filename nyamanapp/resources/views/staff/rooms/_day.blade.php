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

{{-- Filter nama tamu untuk daftar hari ini. Menyaring di peramban, jadi
     hasilnya muncul seketika tanpa memuat ulang panel. --}}
@if ($dayGroups->isNotEmpty())
    <div class="filter-bar">
        @include('partials.icon', ['name' => 'search', 'class' => 'ic-sm'])
        <input type="text" id="filter-tamu" autocomplete="off" placeholder="Filter nama tamu atau nomor kamar…">
        <span class="hitung" id="filter-hitung"></span>
        <button type="button" class="bersih" id="filter-bersih" title="Hapus filter" hidden>
            @include('partials.icon', ['name' => 'x', 'class' => 'ic-sm'])
        </button>
    </div>
@endif

@if ($dayGroups->isEmpty())
    <div class="day-empty">Tidak ada kamar terisi pada tanggal ini.</div>
@else
    <div class="bk-list">
        @foreach ($dayGroups as $grup)
            @php
                /** @var \App\Models\Booking $utama */
                $utama    = $grup->first();
                $sudah    = $utama->isCheckedIn();
                $keluar   = $utama->isCheckedOut();
                $jumlahKm = $grup->count();
            @endphp
            @php
                $kunciCari = trim($utama->nama_tamu . ' ' . $utama->telepon . ' '
                    . $grup->map(fn ($b) => $b->room?->nomor)->filter()->implode(' '));
            @endphp
            <div class="bk {{ $keluar ? 'out' : ($sudah ? 'done' : 'pending') }}"
                 data-cari="{{ $kunciCari }}">
                <div class="bk-main">
                    <div class="bk-rooms">
                        @foreach ($grup as $b)
                            <span class="bk-room">
                                <span class="no">{{ $b->room?->nomor ?? '—' }}</span>
                                @if ($b->room)
                                    <span class="tipe-badge tipe-{{ $b->room->tipe }}">{{ $b->room->labelTipe() }}</span>
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
                    </div>
                    <div class="bk-meta">
                        <span class="tel">{{ $utama->telepon }}</span> ·
                        {{ $utama->check_in->translatedFormat('j M Y') }} → {{ $utama->check_out->translatedFormat('j M Y') }}
                        ({{ $utama->durasi }} malam)
                        @if ($sudah)
                            · check-in {{ $utama->checked_in_at->translatedFormat('j M, H:i') }}
                        @endif
                        @if ($keluar)
                            · check-out {{ $utama->checked_out_at->translatedFormat('j M, H:i') }}
                        @endif
                    </div>
                </div>

                <div class="bk-side">
                    @if ($keluar)
                        <span class="st-badge {{ $utama->checkoutAwal() ? 'st-awal' : ($utama->checkoutTelat() ? 'st-telat' : 'st-out') }}">
                            {{ $utama->labelStatusCheckout() }}
                        </span>
                    @elseif ($sudah)
                        <span class="st-badge st-done">@include('partials.icon', ['name' => 'check', 'class' => 'ic-xs']) Sudah Check-in</span>
                    @else
                        <span class="st-badge st-pending">Belum Check-in</span>
                    @endif

                    @if ($utama->earlyCheckin())
                        <span class="st-badge st-awal">{{ $utama->labelEarlyCheckin() }}</span>
                    @endif

                    @php($tertunda = $utama->permintaanTertunda())

                    @if ($tertunda)
                        <div class="bk-tertunda">
                            @include('partials.icon', ['name' => 'clock', 'class' => 'ic-xs'])
                            Menunggu persetujuan admin: <b>{{ $tertunda->labelJenis() }}</b>
                        </div>
                    @endif

                    <div class="bk-actions">
                        {{-- Tombol check-in: satu kali per GRUP, hanya bila belum check-in --}}
                        @unless ($sudah)
                            <a href="{{ route('staff.rooms.checkin', $utama) }}" class="btn btn-orange btn-sm">
                                Tamu Check-in{{ $jumlahKm > 1 ? ' (' . $jumlahKm . ' kamar)' : '' }}
                            </a>
                        @endunless

                        {{-- Perubahan tidak dikerjakan sendiri oleh staff: ia
                             diajukan, lalu admin yang memutuskan. --}}
                        @unless ($keluar || $tertunda)
                            <a href="{{ route('staff.rooms.perubahan', $utama) }}" class="btn btn-ghost btn-sm">
                                @include('partials.icon', ['name' => 'clipboard', 'class' => 'ic-xs'])
                                Ajukan Perubahan
                            </a>
                        @endunless

                        {{-- Check-out hanya masuk akal setelah tamu benar-benar masuk. --}}
                        @if ($sudah && ! $keluar)
                            <a href="{{ route('staff.rooms.checkout', $utama) }}" class="btn btn-accent btn-sm">
                                @include('partials.icon', ['name' => 'log-out', 'class' => 'ic-xs'])
                                Tamu Check-out{{ $jumlahKm > 1 ? ' (' . $jumlahKm . ' kamar)' : '' }}
                            </a>
                        @endif

                        @if ($keluar && $utama->foto_kartu)
                            <a href="{{ route('kartu.show', $utama) }}" class="btn btn-ghost btn-sm"
                               data-lightbox="{{ route('kartu.show', $utama) }}">
                                @include('partials.icon', ['name' => 'key-card', 'class' => 'ic-xs']) Foto Kartu
                            </a>
                        @endif

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
