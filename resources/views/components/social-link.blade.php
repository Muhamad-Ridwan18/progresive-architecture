@props(['name', 'url', 'size' => 20])

{{-- Tautan media sosial berupa ikon (logo platform). Platform dikenali dari namanya, atau dari alamatnya bila nama
     tidak dikenal; platform lain memakai ikon tautan. Nama platform menjadi label untuk pembaca layar. --}}
@php
    $haystack = strtolower($name.' '.(parse_url($url, PHP_URL_HOST) ?? ''));
    $platform = collect([
        'instagram' => 'instagram', 'linkedin' => 'linkedin', 'facebook' => 'facebook', 'fb.com' => 'facebook',
        'tiktok' => 'tiktok', 'youtube' => 'youtube', 'youtu.be' => 'youtube', 'twitter' => 'x', 'x.com' => 'x',
        'whatsapp' => 'whatsapp', 'wa.me' => 'whatsapp', 'pinterest' => 'pinterest', 'behance' => 'behance',
    ])->first(fn (string $key, string $needle): bool => str_contains($haystack, $needle), 'link');
@endphp

<a href="{{ $url }}" rel="noopener" target="_blank" aria-label="{{ $name }}" title="{{ $name }}"
   {{ $attributes->class(['inline-flex h-11 w-11 items-center justify-center']) }}>
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        @switch($platform)
            @case('instagram')
                <rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r=".6" fill="currentColor"></circle>
                @break
            @case('linkedin')
                <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle>
                @break
            @case('facebook')
                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                @break
            @case('x')
                <path d="M4 4l16 16M20 4L4 20"></path>
                @break
            @case('youtube')
                <path d="M22.5 6.4a2.8 2.8 0 0 0-2-2C18.9 4 12 4 12 4s-6.900 0-8.600.4a2.8 2.8 0 0 0-2 2A29 29 0 0 0 1 11.700a29 29 0 0 0 .4 5.300 2.800 2.800 0 0 0 2 2C5.100 19.500 12 19.500 12 19.500s6.900 0 8.600-.5a2.800 2.800 0 0 0 2-2 29 29 0 0 0 .4-5.300 29 29 0 0 0-.5-5.300z"></path><path d="M9.800 15V8.500l5.700 3.300z"></path>
                @break
            @case('tiktok')
                <path d="M14 3v11.500a3.500 3.500 0 1 1-3.500-3.500M14 3c.3 2.600 2 4.400 5 4.600"></path>
                @break
            @case('whatsapp')
                <path d="M3 21l1.700-5A8.500 8.500 0 1 1 8 19.300z"></path><path d="M9 9.500c0 3 2.500 5.500 5.500 5.500l1-1.500-2-1-.8.800a4 4 0 0 1-2.200-2.200l.8-.8-1-2z"></path>
                @break
            @case('pinterest')
                <circle cx="12" cy="12" r="9"></circle><path d="M10.500 20l1.800-7.500M9 10.500a3 3 0 1 1 3.500 3"></path>
                @break
            @case('behance')
                <path d="M3 6h5a2.500 2.500 0 0 1 0 5H3zM3 11h6a2.500 2.500 0 0 1 0 5H3zM14 8h6M14.500 14a2.800 2.800 0 0 0 5.500 0 2.800 2.800 0 0 0-5.500 0z"></path>
                @break
            @default
                <path d="M10 14a4 4 0 0 0 5.700 0l3-3a4 4 0 0 0-5.700-5.700l-1 1M14 10a4 4 0 0 0-5.700 0l-3 3A4 4 0 0 0 11 18.700l1-1"></path>
        @endswitch
    </svg>
</a>
