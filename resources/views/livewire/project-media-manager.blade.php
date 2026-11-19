@php
    use App\Enums\MediaStatus;
@endphp

{{-- Gaya memakai komponen Filament + inline style agar tidak perlu membangun tema khusus. --}}
{{-- Alpine: progres unggah (event livewire-upload-*), penanda "belum tersimpan", dan peringatan sebelum menutup halaman
     saat unggahan atau simpanan otomatis masih berjalan. --}}
<div x-data="{ uploading: false, progress: 0, count: 0, dirty: false }"
     x-init="Livewire.hook('commit', ({ succeed, fail }) => { succeed(() => { dirty = false }); fail(() => { dirty = false }) })"
     x-on:livewire-upload-start="uploading = true; progress = 0"
     x-on:livewire-upload-progress="progress = $event.detail.progress"
     x-on:livewire-upload-finish="uploading = false"
     x-on:livewire-upload-error="uploading = false"
     x-on:livewire-upload-cancel="uploading = false"
     x-on:input="if ($event.target.matches('[data-autosave]')) { dirty = true }"
     x-on:beforeunload.window="if (uploading || dirty) { $event.preventDefault(); $event.returnValue = '' }"
     style="display:flex;flex-direction:column;gap:12px">

    {{-- Polling hanya aktif selama ada gambar yang masih menunggu/diproses (SRS 12.2 - status per gambar). --}}
    @if ($hasPending)
        <div wire:poll.3s style="display:none"></div>
    @endif

    {{-- Unggah --}}
    <div>
        <label style="display:inline-block;cursor:pointer">
            <span class="fi-btn fi-btn-size-md fi-color-primary" style="display:inline-flex;gap:6px;align-items:center;padding:8px 14px;border-radius:8px;background:var(--primary-600,#475569);color:#fff;font-size:14px;font-weight:600">
                {{ $this->isTeamPhoto()
                    ? ($this->items->isEmpty() ? 'Upload photo' : 'Replace photo')
                    : ($this->isCover() ? ($this->items->isEmpty() ? 'Upload cover' : 'Replace cover') : 'Upload photos') }}
            </span>
            <input type="file"
                   wire:model="uploads"
                   accept="image/jpeg,image/png,image/webp"
                   @if (! $this->isCover()) multiple @endif
                   x-on:change="count = $event.target.files.length"
                   style="display:none">
        </label>
        <span style="margin-left:8px;font-size:12px;opacity:.6">
            JPG, PNG, atau WebP · maks {{ (int) (config('media.max_size_kb') / 1024) }} MB dan {{ (int) (config('media.max_pixels') / 1_000_000) }} megapiksel per gambar
            @if (! $this->isCover())
                · maks {{ config('media.max_files_per_batch') }} sekali unggah
            @endif
        </span>

        {{-- Progres unggah langsung: berapa foto dan berapa persen yang sudah terkirim. --}}
        <div x-show="uploading" x-cloak role="progressbar" aria-label="Progres unggah" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100" style="margin-top:10px;max-width:460px">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
                <span>Mengunggah <span x-text="count"></span> foto…</span>
                <strong x-text="progress + '%'"></strong>
            </div>
            <div style="height:8px;border-radius:4px;background:rgba(128,128,128,.25);overflow:hidden">
                <div :style="'width:' + progress + '%'" style="height:100%;background:var(--primary-600,#475569);transition:width .2s ease-out"></div>
            </div>
        </div>

        <div wire:loading wire:target="uploads" x-show="!uploading" style="margin-top:8px;font-size:12px;opacity:.7">Memproses foto…</div>

        {{-- Simpan otomatis: ketikan dikirim sebentar setelah berhenti, jadi tidak hilang saat halaman dimuat ulang. --}}
        <div style="margin-top:8px;font-size:12px;opacity:.6" aria-live="polite">
            <span x-show="dirty" x-cloak>Menyimpan perubahan…</span>
            <span x-show="!dirty">Perubahan tersimpan otomatis.</span>
        </div>
    </div>

    @foreach ($uploadErrors as $message)
        <div style="font-size:13px;color:#b3261e">{{ $message }}</div>
    @endforeach

    @if ($this->items->isEmpty())
        <div style="font-size:13px;opacity:.6">Belum ada gambar.</div>
    @endif

    @php
        // Galeri proyek: foto dikelompokkan. Satu grup = beberapa foto yang berbagi satu caption (tampil di bawah foto-fotonya).
        $groupInfo = [];

        if ($this->isProjectGallery()) {
            $number = 0;
            $currentId = null;

            foreach ($this->items as $index => $groupItem) {
                if ($index === 0 || ! $groupItem->join_previous) {
                    $number++;
                    $currentId = $groupItem->id;
                    $groupInfo[$currentId] = ['no' => $number, 'size' => 0, 'first' => $groupItem];
                }

                $groupInfo[$currentId]['size']++;
            }
        }
    @endphp

    @if ($this->isProjectGallery() && $this->items->count() > 1)
        <div style="font-size:12px;opacity:.75;line-height:1.5">
            Foto dikelompokkan: tiap grup punya <strong>satu caption</strong> yang tampil di bawah foto-fotonya. Centang
            <em>"Satu grup dengan foto di atas"</em> pada sebuah foto untuk menggabungkannya ke grup sebelumnya, atau hilangkan centang untuk memulai grup baru.
        </div>
    @endif

    {{-- Daftar gambar --}}
    <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax({{ $this->isTeamPhoto() ? '240px' : ($this->isCover() ? '320px' : '220px') }},1fr))">
        @foreach ($this->items as $item)
            @php
                $file = $item->file;
                $ready = $file->status === MediaStatus::Ready;
                $focal = $item->focal ?? ['x' => 50, 'y' => 50];
                $thumb = $ready ? $file->url(800) : null;
            @endphp

            @php $joined = $this->isProjectGallery() && $item->join_previous && ! $loop->first; @endphp

            @if (isset($groupInfo[$item->id]))
                {{-- Awal grup: caption grup (dan pengucap kutipan) disimpan pada foto pertama grup. --}}
                <div wire:key="group-{{ $item->id }}" style="grid-column:1 / -1;display:flex;flex-direction:column;gap:6px;padding:10px 12px;border-radius:8px;background:rgba(128,128,128,.08);{{ $loop->first ? '' : 'margin-top:8px;' }}">
                    <div style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600">
                        <span>Grup {{ $groupInfo[$item->id]['no'] }}</span>
                        <span style="font-weight:400;opacity:.7">{{ $groupInfo[$item->id]['size'] }} foto</span>
                    </div>
                    <textarea placeholder="Caption untuk grup ini (tampil di bawah foto-fotonya)" rows="2" maxlength="3000" data-autosave
                              wire:input.debounce.700ms="updateMeta({{ $item->id }}, 'note', $event.target.value)"
                              style="width:100%;font-size:13px;padding:6px 8px;border:1px solid rgba(128,128,128,.4);border-radius:6px;background:transparent;resize:vertical">{{ $item->note }}</textarea>
                    <input type="text" placeholder="Pengucap kutipan (opsional): bila diisi, caption tampil sebagai kutipan besar. Mis. Nama, Jabatan" maxlength="255" data-autosave
                           value="{{ $item->note_by }}"
                           wire:input.debounce.700ms="updateMeta({{ $item->id }}, 'note_by', $event.target.value)"
                           style="width:100%;font-size:13px;padding:6px 8px;border:1px solid rgba(128,128,128,.4);border-radius:6px;background:transparent">
                </div>
            @endif

            <div wire:key="usage-{{ $item->id }}" style="border:1px solid rgba(128,128,128,.3);{{ $joined ? 'border-left:4px solid var(--primary-600,#475569);' : '' }}border-radius:8px;padding:8px;display:flex;flex-direction:column;gap:6px">

                {{-- Pratinjau; pada cover, klik untuk menentukan titik fokus --}}
                @if ($thumb)
                    @if ($this->isCover())
                        <div style="position:relative;cursor:crosshair"
                             x-data
                             x-on:click="const r = $el.getBoundingClientRect(); $wire.setFocal({{ $item->id }}, (event.clientX - r.left) / r.width * 100, (event.clientY - r.top) / r.height * 100)">
                            <img src="{{ $thumb }}" alt="" style="width:100%;display:block;border-radius:4px">
                            <span style="position:absolute;left:{{ $focal['x'] }}%;top:{{ $focal['y'] }}%;width:16px;height:16px;margin:-8px 0 0 -8px;border:2px solid #fff;border-radius:50%;box-shadow:0 0 0 1px #000;pointer-events:none"></span>
                        </div>
                        <div style="font-size:12px;opacity:.7">Klik gambar untuk menentukan titik fokus.</div>
                        @if ($this->isTeamPhoto())
                            <div style="width:50%">
                                <div style="aspect-ratio:4/5;overflow:hidden;border-radius:4px">
                                    <img src="{{ $thumb }}" alt="" style="width:100%;height:100%;object-fit:cover;object-position:{{ $focal['x'] }}% {{ $focal['y'] }}%">
                                </div>
                                <div style="font-size:11px;opacity:.6">Tampil 4:5</div>
                            </div>
                        @else
                        <div style="display:flex;gap:8px;align-items:flex-start">
                            <div style="flex:2">
                                <div style="aspect-ratio:16/9;overflow:hidden;border-radius:4px">
                                    <img src="{{ $thumb }}" alt="" style="width:100%;height:100%;object-fit:cover;object-position:{{ $focal['x'] }}% {{ $focal['y'] }}%">
                                </div>
                                <div style="font-size:11px;opacity:.6">Desktop 16:9</div>
                            </div>
                            <div style="flex:1">
                                <div style="aspect-ratio:4/5;overflow:hidden;border-radius:4px">
                                    <img src="{{ $thumb }}" alt="" style="width:100%;height:100%;object-fit:cover;object-position:{{ $focal['x'] }}% {{ $focal['y'] }}%">
                                </div>
                                <div style="font-size:11px;opacity:.6">Mobile 4:5</div>
                            </div>
                        </div>
                        @endif
                    @else
                        <img src="{{ $thumb }}" alt="" style="width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:4px">
                    @endif
                @else
                    <div style="aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;background:rgba(128,128,128,.12);border-radius:4px;font-size:13px;text-align:center;padding:8px">
                        @if ($file->status === MediaStatus::Failed)
                            {{ $file->error }}
                        @else
                            {{ $file->status->getLabel() }}…
                        @endif
                    </div>
                @endif

                <div style="display:flex;align-items:center;justify-content:space-between;gap:6px">
                    <x-filament::badge :color="$file->status->getColor()" size="sm">{{ $file->status->getLabel() }}</x-filament::badge>
                    <span style="font-size:11px;opacity:.6;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $file->original_name }}">{{ $file->original_name }}</span>
                </div>

                {{-- Alt text dan caption disimpan per penggunaan (SRS 12.6). Semua isian tersimpan otomatis saat mengetik. --}}
                <input type="text" placeholder="Alt text (deskripsi gambar)" maxlength="255" data-autosave
                       value="{{ $item->alt_text }}"
                       wire:input.debounce.700ms="updateMeta({{ $item->id }}, 'alt_text', $event.target.value)"
                       style="width:100%;font-size:13px;padding:6px 8px;border:1px solid rgba(128,128,128,.4);border-radius:6px;background:transparent">
                {{-- Caption per foto hanya untuk galeri selain galeri proyek (About, dll.); galeri proyek memakai caption grup. --}}
                @if (! $this->isCover() && ! $this->isProjectGallery())
                    <input type="text" placeholder="Caption (opsional)" maxlength="1000" data-autosave
                           value="{{ $item->caption }}"
                           wire:input.debounce.700ms="updateMeta({{ $item->id }}, 'caption', $event.target.value)"
                           style="width:100%;font-size:13px;padding:6px 8px;border:1px solid rgba(128,128,128,.4);border-radius:6px;background:transparent">
                @endif

                @if ($this->isProjectGallery() && ! $loop->first)
                    <label style="display:flex;gap:6px;align-items:center;font-size:12px;cursor:pointer">
                        <input type="checkbox" @checked($joined) wire:change="setJoin({{ $item->id }}, $event.target.checked)">
                        Satu grup dengan foto di atas
                    </label>
                @endif

                <div style="display:flex;gap:6px;flex-wrap:wrap">
                    @if (! $this->isCover())
                        <x-filament::button size="xs" color="gray" wire:click="move({{ $item->id }}, 'up')" :disabled="$loop->first">↑</x-filament::button>
                        <x-filament::button size="xs" color="gray" wire:click="move({{ $item->id }}, 'down')" :disabled="$loop->last">↓</x-filament::button>
                    @endif
                    @if ($file->status === MediaStatus::Failed)
                        <x-filament::button size="xs" color="warning" wire:click="retry({{ $item->id }})">Retry</x-filament::button>
                    @endif
                    <x-filament::button size="xs" color="danger" wire:click="remove({{ $item->id }})"
                                        wire:confirm="Hapus gambar ini dari proyek?">Remove</x-filament::button>
                </div>
            </div>
        @endforeach
    </div>
</div>
