{{-- Riwayat aktivitas (SRS FR-RC-033, FR-RC-035): perubahan status dan catatan internal digabung, terbaru di atas. --}}
@include('filament.job-applications._styles')

@php
    $application = $getRecord()->loadMissing(['statusChanges.author', 'notes.author']);

    $events = $application->statusChanges
        ->map(fn ($change): array => [
            'time' => $change->created_at,
            'status' => true,
            'text' => $change->from_status
                ? 'Status diubah ke '.$change->to_status->getLabel()
                : 'Lamaran diterima melalui website',
            'who' => $change->author?->name ?? 'Sistem',
            'note' => null,
        ])
        ->concat($application->notes->map(fn ($note): array => [
            'time' => $note->created_at,
            'status' => false,
            'text' => 'Catatan internal ditambahkan',
            'who' => $note->author?->name ?? 'Pengguna dihapus',
            'note' => $note->body,
        ]))
        ->sortByDesc('time')
        ->values();
@endphp

@if ($events->isEmpty())
    <p class="pa-muted">Belum ada aktivitas.</p>
@else
    <ol class="pa-tl">
        @foreach ($events as $event)
            <li>
                <span class="dot {{ $event['status'] ? 'is-status' : '' }}"></span>
                <div>{{ $event['text'] }}</div>
                @if ($event['note'])
                    <div class="note">{{ $event['note'] }}</div>
                @endif
                <div class="meta">{{ $event['who'] }} &middot; {{ $event['time']->format('d M Y, H:i') }}</div>
            </li>
        @endforeach
    </ol>
@endif
