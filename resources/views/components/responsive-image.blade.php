{{--
    Gambar responsif dari varian WebP yang dibuat pipeline (SRS 12.4):
    srcset + sizes, dimensi eksplisit (mencegah layout shift), lazy loading, dan titik fokus cover.

    @props
      usage  MediaUsage dengan file siap
      alt    cadangan bila penggunaan belum punya alt text
      sizes  atribut sizes
      cover  true = isi wadah (object-fit: cover) mengikuti titik fokus; false = rasio asli
      eager  true untuk gambar di atas lipatan (tanpa lazy loading)
--}}
@props(['usage', 'alt' => '', 'sizes' => '100vw', 'cover' => false, 'eager' => false])

@php
    $file = $usage->file;
    $focal = $usage->focal ?? ['x' => 50, 'y' => 50];
@endphp

<img
    src="{{ $file->url(1280) }}"
    srcset="{{ $file->srcset() }}"
    sizes="{{ $sizes }}"
    width="{{ $file->width }}"
    height="{{ $file->height }}"
    alt="{{ $usage->alt_text ?: $alt }}"
    @if ($eager) fetchpriority="high" @else loading="lazy" @endif
    decoding="async"
    @if ($cover) style="object-position: {{ $focal['x'] ?? 50 }}% {{ $focal['y'] ?? 50 }}%" @endif
    {{ $attributes->class([$cover ? 'h-full w-full object-cover' : 'h-auto w-full']) }}
>
