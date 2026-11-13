{{--
    Dokumen pelamar dibaca langsung di halaman (tanpa mengunduh): PDF memakai penampil bawaan browser (halaman, zoom,
    layar penuh), gambar tampil langsung. Berkas diambil dari rute admin privat yang menyajikannya inline; jenis
    lain (mis. DOCX) tidak dapat dipratinjau dan hanya bisa diunduh.
--}}
@include('filament.job-applications._styles')

@php
    $files = $getRecord()->loadMissing('files')->files;
    $label = fn ($file): string => $file->isPdf() ? 'PDF' : (str_starts_with((string) $file->mime_type, 'image/') ? 'IMG' : strtoupper(pathinfo($file->original_name, PATHINFO_EXTENSION) ?: 'FILE'));
@endphp

@if ($files->isEmpty())
    <p class="pa-empty">Tidak ada dokumen.</p>
@else
    <div class="pa-docs" x-data="{ current: {{ $files->first()->getKey() }} }">
        <div class="pa-doclist">
            @foreach ($files as $file)
                <button type="button" class="pa-doc" :class="{ 'is-on': current === {{ $file->getKey() }} }"
                        @click="current = {{ $file->getKey() }}" :aria-current="current === {{ $file->getKey() }} ? 'true' : 'false'">
                    <span class="pa-doc-ic">{{ $label($file) }}</span>
                    <span style="min-width: 0">
                        <strong>{{ $file->original_name }}</strong>
                        <small>{{ $file->humanSize() }}</small>
                    </span>
                </button>
            @endforeach

            <p class="pa-note">
                <span aria-hidden="true">🔒</span>
                <span>Dokumen bersifat privat dan hanya dibuka di dalam dashboard. Tidak perlu mengunduh untuk membacanya.</span>
            </p>
        </div>

        {{-- wire:ignore: penampil tidak boleh dimuat ulang saat bagian lain halaman diperbarui (mis. setelah menambah catatan). --}}
        <div class="pa-viewer" wire:ignore>
            @foreach ($files as $file)
                @php
                    $url = route('admin.application-files.show', $file);
                    $previewable = $file->isPdf() || in_array($file->mime_type, ['image/jpeg', 'image/png'], true);
                @endphp

                <div x-show="current === {{ $file->getKey() }}" x-cloak>
                    <div class="pa-toolbar">
                        <div class="grow"><strong>{{ $file->original_name }}</strong> <span class="pa-muted">&middot; {{ $file->humanSize() }}</span></div>
                        @if ($previewable)
                            <a href="{{ $url }}" target="_blank" rel="noopener">Buka di tab baru</a>
                        @endif
                        <a href="{{ route('admin.application-files.show', [$file, 'download' => 1]) }}" class="quiet">Unduh</a>
                    </div>

                    {{-- Dimuat hanya saat dipilih, supaya dokumen besar tidak ikut diunduh browser sebelum dibuka. --}}
                    <template x-if="current === {{ $file->getKey() }}">
                        @if ($file->isPdf())
                            <iframe class="pa-frame" src="{{ $url }}#toolbar=1&navpanes=0&view=FitH" title="{{ $file->original_name }}"></iframe>
                        @elseif ($previewable)
                            <div class="pa-stage"><img src="{{ $url }}" alt="{{ $file->original_name }}"></div>
                        @else
                            <p class="pa-empty">Jenis berkas ini tidak dapat dipratinjau di browser. Gunakan tombol Unduh untuk membukanya.</p>
                        @endif
                    </template>
                </div>
            @endforeach
        </div>
    </div>
@endif
