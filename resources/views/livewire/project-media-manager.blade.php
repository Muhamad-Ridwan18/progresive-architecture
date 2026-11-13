@php
    use App\Enums\MediaStatus;
@endphp

{{-- Gaya memakai komponen Filament + inline style agar tidak perlu membangun tema khusus. --}}
<div style="display:flex;flex-direction:column;gap:12px">

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
                   style="display:none">
        </label>
        <span wire:loading wire:target="uploads" style="margin-left:8px;font-size:13px;opacity:.7">Uploading…</span>
        <span style="margin-left:8px;font-size:12px;opacity:.6">
            JPG, PNG, atau WebP · maks {{ (int) (config('media.max_size_kb') / 1024) }} MB per gambar
            @if (! $this->isCover())
                · maks {{ config('media.max_files_per_batch') }} sekali unggah
            @endif
        </span>
    </div>

    @foreach ($uploadErrors as $message)
        <div style="font-size:13px;color:#b3261e">{{ $message }}</div>
    @endforeach

    @if ($this->items->isEmpty())
        <div style="font-size:13px;opacity:.6">Belum ada gambar.</div>
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

            <div wire:key="usage-{{ $item->id }}" style="border:1px solid rgba(128,128,128,.3);border-radius:8px;padding:8px;display:flex;flex-direction:column;gap:6px">

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

                {{-- Alt text dan caption disimpan per penggunaan (SRS 12.6) --}}
                <input type="text" placeholder="Alt text (deskripsi gambar)" maxlength="255"
                       value="{{ $item->alt_text }}"
                       wire:change="updateMeta({{ $item->id }}, 'alt_text', $event.target.value)"
                       style="width:100%;font-size:13px;padding:6px 8px;border:1px solid rgba(128,128,128,.4);border-radius:6px;background:transparent">
                @if (! $this->isCover())
                    <input type="text" placeholder="Caption (opsional)" maxlength="1000"
                           value="{{ $item->caption }}"
                           wire:change="updateMeta({{ $item->id }}, 'caption', $event.target.value)"
                           style="width:100%;font-size:13px;padding:6px 8px;border:1px solid rgba(128,128,128,.4);border-radius:6px;background:transparent">
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
