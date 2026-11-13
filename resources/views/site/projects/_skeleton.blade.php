{{--
    Kerangka pemuatan satu batch penuh, mengikuti pola dan rasio yang sama dengan item asli
    (GridPattern), sehingga saat digantikan konten nyata tinggi halaman hampir tidak berubah
    (SRS 12.1: hindari layout shift). 8 item per halaman = panjang pola, jadi satu template
    ini berlaku untuk semua batch.
--}}
@use('App\Support\GridPattern')

@foreach (range(0, GridPattern::PROJECTS_PER_PAGE - 1) as $i)
    @php $pattern = GridPattern::project($i); @endphp

    <div data-skeleton aria-hidden="true" class="col-span-1 {{ $pattern['col'] }} {{ $pattern['offset'] }}">
        <div class="skeleton {{ $pattern['ratio'] }}"></div>
        {{-- Tinggi baris sama dengan teks asli: judul 24px + kategori 21px + jarak 12px. --}}
        <div class="skeleton mt-3 h-6 w-2/3"></div>
        <div class="skeleton h-[21px] w-1/3" style="opacity:.6"></div>
    </div>
@endforeach
