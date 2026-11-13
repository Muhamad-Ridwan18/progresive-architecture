{{-- Penanda Preview: muncul hanya untuk administrator pada draft yang belum terlihat pengunjung. --}}
@props(['editUrl'])

<div class="bg-ink text-white" role="status">
    <p class="container-page py-3 text-small">
        <strong class="font-semibold">Preview.</strong>
        Ini tampilan draft dan belum terlihat oleh pengunjung.
        <a href="{{ $editUrl }}" class="ml-2 underline">Kembali ke editor</a>
    </p>
</div>
