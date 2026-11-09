{{-- Kepala halaman Keuangan: judul, tombol utama "Catat …", menu "Lainnya", tab,
     dan pemberitahuan hasil impor.
     Parameter: $sub, $catat ['label', 'dialog', 'isi'], $lainnya [['label', 'href'|'dialog'], …] --}}
@include('admin.keuangan._gaya')

<div class="keu-kepala">
    <div>
        <h1>Keuangan</h1>
        <p>{{ $sub }}</p>
    </div>
    <div class="keu-aksi">
        @if (! empty($lainnya))
            <details class="menu-edit">
                <summary class="btn btn-ghost btn-sm">
                    Lainnya @include('partials.icon', ['name' => 'chevron-down', 'class' => 'ic-xs'])
                </summary>
                <div class="menu-edit-isi">
                    @foreach ($lainnya as $m)
                        @if (isset($m['href']))
                            <a href="{{ $m['href'] }}"><b>{{ $m['label'] }}</b>@if (! empty($m['ket']))<span>{{ $m['ket'] }}</span>@endif</a>
                        @else
                            <button type="button" data-buka="{{ $m['dialog'] }}" data-isi="{{ json_encode($m['isi'] ?? new stdClass) }}"><b>{{ $m['label'] }}</b>@if (! empty($m['ket']))<span>{{ $m['ket'] }}</span>@endif</button>
                        @endif
                    @endforeach
                </div>
            </details>
        @endif
        <button type="button" class="btn btn-accent" data-buka="{{ $catat['dialog'] }}" data-isi="{{ json_encode($catat['isi'] ?? new stdClass) }}">
            @include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm']) {{ $catat['label'] }}
        </button>
    </div>
</div>

@include('admin.keuangan._tab')

@php
    $galatImpor = session('galat_impor', []);
    $peringatanImpor = session('peringatan_impor', []);
@endphp
@if ($galatImpor)
    <details class="keu-info galat" open>
        <summary>Impor dibatalkan — {{ count($galatImpor) }} baris perlu diperbaiki</summary>
        <ul>
            @foreach (array_slice($galatImpor, 0, 20) as $g)<li>{{ $g }}</li>@endforeach
        </ul>
        @if (count($galatImpor) > 20)<div>… dan {{ count($galatImpor) - 20 }} lainnya.</div>@endif
    </details>
@endif
@if ($peringatanImpor)
    <details class="keu-info awas">
        <summary>{{ count($peringatanImpor) }} angka hasil impor perlu dicek</summary>
        <div>{{ session('judul_peringatan', 'Sudah tersimpan. Angka ini jauh berbeda dari hari-hari sekitarnya — pastikan memang benar (bisa transfer besar, bisa salah ketik):') }}</div>
        <ul>
            @foreach (array_slice($peringatanImpor, 0, 20) as $p)<li>{{ $p }}</li>@endforeach
        </ul>
    </details>
@endif
