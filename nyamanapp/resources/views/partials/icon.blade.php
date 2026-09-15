@php
    /**
     * Ikon garis (stroke) monokrom — mewarisi warna teks lewat `currentColor`.
     *
     * Pemakaian:  @include('partials.icon', ['name' => 'box'])
     *             @include('partials.icon', ['name' => 'plus', 'class' => 'ic-sm'])
     *
     * Ukuran ditulis sebagai atribut width/height pada <svg>, bukan hanya lewat
     * CSS: tanpa itu, SVG akan melebar memenuhi kontainer di halaman yang tidak
     * memuat stylesheet panel (login, welcome, 403).
     */
    $ukuran = [
        'ic-xs' => 14,
        'ic-sm' => 16,
        'ic'    => 18,
        'ic-lg' => 22,
        'ic-xl' => 26,
    ];

    $extra = trim($class ?? '');
    $px    = $size ?? 18;

    foreach (preg_split('/\s+/', $extra, -1, PREG_SPLIT_NO_EMPTY) as $kelas) {
        if (isset($ukuran[$kelas])) {
            $px = $ukuran[$kelas];
            break;
        }
    }

    $ikon = [
        'box' => '<path d="M20.5 7.3 12 12 3.5 7.3"/><path d="M12 12v9.5"/><path d="M20.5 7.3v9.4L12 21.5l-8.5-4.8V7.3L12 2.5z"/>',
        'bed' => '<path d="M2.5 19V6.5"/><path d="M2.5 11.5h13a5 5 0 0 1 5 5V19"/><path d="M2.5 19h19"/><circle cx="7.5" cy="8.8" r="2.1"/>',
        'inbox' => '<path d="M3 13.5h4.2l1.4 2.8h6.8l1.4-2.8H21"/><path d="M5.6 4.5h12.8L21 13.5v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4L5.6 4.5z"/>',
        'check' => '<path d="M4.5 12.5 9.5 17.5 19.5 6.5"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'x' => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
        'chevron-left' => '<path d="M14.5 5 7.5 12l7 7"/>',
        'chevron-right' => '<path d="M9.5 5l7 7-7 7"/>',
        'chevron-down' => '<path d="M5.5 9 12 15.5 18.5 9"/>',
        'arrow-left' => '<path d="M19.5 12H4.5"/><path d="M10.5 6 4.5 12l6 6"/>',
        'arrow-right' => '<path d="M4.5 12h15"/><path d="M13.5 6l6 6-6 6"/>',
        'download' => '<path d="M12 3.5v11.5"/><path d="M7.5 10.5 12 15l4.5-4.5"/><path d="M4 19.5h16"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.3-4.3"/>',
        'alert' => '<path d="M12 4.5 2.8 20.2h18.4L12 4.5z"/><path d="M12 10.2v4.4"/><path d="M12 17.4h.01"/>',
        'id-card' => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><circle cx="8.4" cy="10.6" r="2.1"/><path d="M5.2 16.2c.7-1.4 1.9-2.1 3.2-2.1s2.5.7 3.2 2.1"/><path d="M15 9.8h4"/><path d="M15 13.4h4"/>',
        'camera' => '<rect x="2.5" y="6.8" width="19" height="12.7" rx="2.5"/><path d="M8.6 6.8 10.2 4.3h3.6l1.6 2.5"/><circle cx="12" cy="13.2" r="3.4"/>',
        'image' => '<rect x="3" y="4.5" width="18" height="15" rx="2.5"/><circle cx="8.4" cy="9.8" r="1.7"/><path d="M3.8 17.2 9 12.6l3.9 3.4 3-2.5 4.3 3.7"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 19.2c.7-3.1 3-4.7 6-4.7s5.3 1.6 6 4.7"/><path d="M15.8 5.2a3.2 3.2 0 0 1 0 6.1"/><path d="M17.4 14.7c2 .7 3.2 2.1 3.6 4.5"/>',
        'clipboard' => '<rect x="4.5" y="5" width="15" height="15.5" rx="2.5"/><path d="M9 5V3.7h6V5"/><path d="M8.5 10.4h7"/><path d="M8.5 13.9h7"/><path d="M8.5 17.2h4"/>',
        'undo' => '<path d="M4.5 9.5h11a5 5 0 0 1 0 10H9"/><path d="M8.5 5.5l-4 4 4 4"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.2V12l3.2 2"/>',
        'calendar' => '<rect x="3.5" y="5.5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17"/><path d="M8 3.5v4"/><path d="M16 3.5v4"/>',
        'chart' => '<path d="M4 20V4"/><path d="M4 20h16"/><path d="M8 20v-6"/><path d="M12.5 20V8.5"/><path d="M17 20v-9"/>',
        'phone' => '<path d="M8.4 4.5H5.6A2 2 0 0 0 3.6 6.7c.3 3.4 1.7 6.5 4 8.8s5.4 3.7 8.8 4a2 2 0 0 0 2.1-2v-2.7l-3.6-1.2-1.8 1.8a13 13 0 0 1-4.7-4.7l1.8-1.8L8.4 4.5z"/>',
        'key-card' => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><rect x="5.6" y="8.4" width="5.2" height="4.3" rx="1.1"/><path d="M14.6 9.4h4.2"/><path d="M14.6 12.6h4.2"/><path d="M14.6 15.8h2.6"/>',
        'log-out' => '<path d="M15.5 4.5H18a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-2.5"/><path d="M10.5 8 14.5 12l-4 4"/><path d="M14.5 12h-11"/>',
        'dot' => '<circle cx="12" cy="12" r="4"/>',
    ];

    $svg = $ikon[$name] ?? $ikon['dot'];
@endphp
<svg class="ic {{ $extra }}" data-icon="{{ $name }}" width="{{ $px }}" height="{{ $px }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $svg !!}</svg>
