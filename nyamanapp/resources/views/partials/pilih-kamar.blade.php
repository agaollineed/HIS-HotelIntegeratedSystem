{{-- Daftar kamar pesanan pada halaman check-in dan check-out.

     Tamu dalam satu pesanan tidak selalu datang atau pulang bersamaan, jadi
     staff yang memilih kamar mana yang diproses. Setiap baris:
       id, nomor, info, label, kelas (ok | awas | netral), catatan, data[]

     Bila pilihannya hanya satu kamar, tidak ada yang perlu dicentang. --}}
@php
    $banyak  = count($baris) > 1;
    $dipilih = array_map('intval', (array) old('kamar', $terpilih));
@endphp

@if ($banyak || ($tampilTunggal ?? false))
    <div class="pilih-kamar">
        @if ($banyak)
            <div class="pk-judul">{{ $judul }}</div>
        @endif
        @foreach ($baris as $b)
            <label class="pk-baris {{ $banyak ? '' : 'tunggal' }}">
                @if ($banyak)
                    <input type="checkbox" name="kamar[]" value="{{ $b['id'] }}" class="js-kamar"
                           @checked(in_array($b['id'], $dipilih, true))
                           @foreach (($b['data'] ?? []) as $k => $v) data-{{ $k }}="{{ $v }}" @endforeach>
                @else
                    <input type="hidden" name="kamar[]" value="{{ $b['id'] }}" class="js-kamar"
                           @foreach (($b['data'] ?? []) as $k => $v) data-{{ $k }}="{{ $v }}" @endforeach>
                @endif
                <span class="pk-no">{{ $b['nomor'] }}</span>
                <span class="pk-isi">
                    <span class="pk-info">{{ $b['info'] }}</span>
                    @if (! empty($b['catatan']))
                        <span class="pk-catatan">{{ $b['catatan'] }}</span>
                    @endif
                </span>
                <span class="pk-st {{ $b['kelas'] ?? 'netral' }}">{{ $b['label'] }}</span>
            </label>
        @endforeach
    </div>
@else
    @foreach ($baris as $b)
        <input type="hidden" name="kamar[]" value="{{ $b['id'] }}" class="js-kamar"
               @foreach (($b['data'] ?? []) as $k => $v) data-{{ $k }}="{{ $v }}" @endforeach>
    @endforeach
@endif

@once
@push('head')
<style>
    .pilih-kamar { display: grid; gap: 8px; margin-bottom: 22px; }
    .pk-judul { font-size: .86rem; font-weight: 700; margin-bottom: 2px; }
    .pk-baris {
        display: flex; align-items: center; gap: 12px; padding: 11px 14px; cursor: pointer;
        border: 1px solid var(--border-2); border-radius: 12px; background: var(--bg);
    }
    .pk-baris.tunggal { cursor: default; }
    .pk-baris:has(input:checked) { border-color: var(--accent); background: var(--accent-soft); }
    .pk-baris input[type="checkbox"] { width: 18px; height: 18px; flex: 0 0 auto; accent-color: var(--accent); }
    .pk-no { font-weight: 800; font-variant-numeric: tabular-nums; }
    .pk-isi { flex: 1 1 auto; min-width: 0; display: grid; gap: 2px; }
    .pk-info { font-size: .8rem; color: var(--muted); }
    .pk-catatan { font-size: .78rem; color: var(--text); line-height: 1.4; }
    .pk-st { flex: 0 0 auto; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; text-align: right; }
    .pk-st.ok     { color: var(--ok); background: var(--ok-soft, rgba(74,222,128,.12)); }
    .pk-st.awas   { color: var(--alert); background: var(--alert-soft); }
    .pk-st.netral { color: var(--muted); background: var(--hover); }
    @media (max-width: 480px) { .pk-baris { flex-wrap: wrap; } .pk-isi { flex-basis: 60%; } }
</style>
@endpush
@endonce
