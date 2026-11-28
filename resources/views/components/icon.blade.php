@props(['name' => '', 'size' => 18])

@php
    // One inline SVG sprite keeps the icon language technical (1.5 px strokes,
    // square caps) and needs no icon font, no JavaScript and no extra request.
    $paths = [
        'queue' => '<path d="M8 6h12M8 12h12M8 18h12"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'package' => '<path d="M12 3 4 7v10l8 4 8-4V7l-8-4Z"/><path d="m4 7 8 4 8-4M12 11v10"/>',
        'inbox' => '<path d="M4 4h16v10H4z"/><path d="M4 14h4l1.5 3h5L16 14h4"/><path d="M8 8h8M8 11h5"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5"/><path d="M16 5.6A3 3 0 0 1 16 11M17.5 20c0-2.4-.8-4.2-2.2-5.3"/>',
        'key' => '<circle cx="8" cy="12" r="4"/><path d="M12 12h9M17.5 12v3.5M20.5 12v2.5"/>',
        'print' => '<path d="M7 8V4h10v4"/><path d="M7 17H5a1 1 0 0 1-1-1v-5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-2"/><path d="M7 13h10v7H7z"/>',
        'logout' => '<path d="M14 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="M9 8l-4 4 4 4M5 12h9"/>',
        'arrow-right' => '<path d="M4 12h15M13 6l6 6-6 6"/>',
        'chevron-right' => '<path d="m9 5 7 7-7 7"/>',
        'camera' => '<path d="M3 8.5A1.5 1.5 0 0 1 4.5 7H7l1.5-2h7L17 7h2.5A1.5 1.5 0 0 1 21 8.5v9A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5Z"/><circle cx="12" cy="13" r="3.5"/>',
        'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4.5l3 1.8"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7"/>',
        'x' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'alert' => '<path d="M12 4 3 20h18L12 4Z"/><path d="M12 10v4M12 17h.01"/>',
        'wifi-off' => '<path d="m3 3 18 18"/><path d="M8.5 15.5a5 5 0 0 1 7 0M5 12a10 10 0 0 1 3-2.2M19 12a10 10 0 0 0-3-2.2M2 8.8A15 15 0 0 1 8 5.4M22 8.8a15 15 0 0 0-6-3.4"/><path d="M12 19h.01"/>',
        'moon' => '<path d="M20 14.6A8.4 8.4 0 0 1 9.4 4a8.4 8.4 0 1 0 10.6 10.6Z"/>',
        'sun' => '<path d="M12 16.2a4.2 4.2 0 1 0 0-8.4 4.2 4.2 0 0 0 0 8.4Z"/><path d="M12 2.6v2.2M12 19.2v2.2M2.6 12h2.2M19.2 12h2.2M5.4 5.4l1.6 1.6M17 17l1.6 1.6M18.6 5.4 17 7M7 17l-1.6 1.6"/>',
    ];
@endphp

@if (isset($paths[$name]))
    <svg xmlns="http://www.w3.org/2000/svg" width="{{ $size }}" height="{{ $size }}"
         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
         stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true" focusable="false">
        {!! $paths[$name] !!}
    </svg>
@endif
