{{--
    Teks polos menjadi paragraf: baris kosong = paragraf baru, baris tunggal = <br>.
    Selalu di-escape, jadi aman untuk isi dari admin. Dipakai halaman proyek dan About Us.
--}}
@props(['text' => null])

@php
    $paragraphs = filled($text) ? preg_split('/\R{2,}/', trim($text)) : [];
@endphp

@if ($paragraphs)
    <div {{ $attributes->class('space-y-5') }}>
        @foreach ($paragraphs as $paragraph)
            <p>{!! nl2br(e($paragraph)) !!}</p>
        @endforeach
    </div>
@endif
