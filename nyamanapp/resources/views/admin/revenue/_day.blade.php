<div class="rv-day-head">
    <div>
        <h3>{{ $selected->translatedFormat('l, j F Y') }}</h3>
        <p>{{ $bookings->count() }} kamar terisi malam ini</p>
    </div>
    <div class="rv-day-total">
        <div class="n">Rp {{ number_format($total, 0, ',', '.') }}</div>
        <div class="l">Pendapatan hari ini</div>
    </div>
</div>

@if ($bookings->isEmpty())
    <div class="rv-empty">Tidak ada kamar terisi — pendapatan Rp 0 pada tanggal ini.</div>
@else
    {{-- Rekap per tipe kamar --}}
    @php $bfKamar = $bookings->filter(fn ($b) => $b->breakfast)->count(); @endphp
    <div class="rv-tipe">
        <div class="rv-tipe-item">
            <span class="bf-badge ada">Breakfast</span>
            <span class="rv-tipe-calc">{{ $bfKamar }} dari {{ $bookings->count() }} kamar</span>
        </div>
        @foreach ($perTipe as $t)
            <div class="rv-tipe-item">
                <span class="tipe-badge tipe-{{ $t['tipe'] }}">{{ $t['label'] }}</span>
                <span class="rv-tipe-calc">
                    {{ $t['jumlah'] }} kamar
                </span>
                <span class="rv-tipe-total">Rp {{ number_format($t['total'], 0, ',', '.') }}</span>
            </div>
        @endforeach
    </div>

    {{-- Rincian per kamar --}}
    <div class="rv-tscroll">
        <table class="rv-table">
            <thead>
                <tr>
                    <th>Kamar</th>
                    <th>Tamu</th>
                    <th>Menginap</th>
                    <th>Sarapan</th>
                    <th>Status</th>
                    <th class="num">Tarif / Malam</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bookings as $b)
                    <tr>
                        <td>
                            <span class="rv-no">{{ $b->room?->nomor ?? '—' }}</span>
                            @if ($b->room)
                                <span class="tipe-badge tipe-{{ $b->room->tipe }}">{{ $b->room->labelTipe() }}</span>
                            @endif
                        </td>
                        <td>{{ $b->nama_tamu }}</td>
                        <td class="rv-muted">
                            {{ $b->check_in->translatedFormat('j M') }} → {{ $b->check_out->translatedFormat('j M') }}
                            ({{ $b->durasi }} mlm)
                        </td>
                        <td>
                            <span class="bf-badge {{ $b->breakfast ? 'ada' : 'tanpa' }}">{{ $b->breakfast ? 'Breakfast' : 'Tanpa BF' }}</span>
                        </td>
                        <td>
                            @if ($b->isCheckedIn())
                                <span class="st-badge st-done">@include('partials.icon', ['name' => 'check', 'class' => 'ic-xs']) Sudah Check-in</span>
                            @else
                                <span class="st-badge st-pending">Belum Check-in</span>
                            @endif
                        </td>
                        <td class="num rv-money">Rp {{ number_format($b->hargaMalam(), 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="rv-foot-label">Total malam ini</td>
                    <td class="num rv-foot-total">Rp {{ number_format($total, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
