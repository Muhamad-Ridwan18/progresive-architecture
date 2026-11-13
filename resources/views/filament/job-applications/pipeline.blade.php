{{-- Tahap lamaran: Submitted -> Reviewing -> Shortlisted -> Interview -> Decision (Accepted/Rejected). --}}
@include('filament.job-applications._styles')

@php
    use App\Enums\ApplicationStatus;

    $status = $getRecord()->status;
    $order = [ApplicationStatus::Submitted, ApplicationStatus::Reviewing, ApplicationStatus::Shortlisted, ApplicationStatus::Interview];
    $current = in_array($status, [ApplicationStatus::Accepted, ApplicationStatus::Rejected], true) ? 4 : array_search($status, $order, true);
    $steps = array_map(fn (ApplicationStatus $s): string => $s->getLabel(), $order);
    $steps[] = match ($status) {
        ApplicationStatus::Accepted => 'Accepted',
        ApplicationStatus::Rejected => 'Rejected',
        default => 'Decision',
    };
@endphp

<div class="pa-pipeline" role="list" aria-label="Tahap lamaran">
    @foreach ($steps as $index => $label)
        @php
            $state = $index < $current ? 'is-done' : ($index === $current ? 'is-now' : '');
            if ($index === 4 && $status === ApplicationStatus::Rejected) {
                $state = 'is-bad';
            } elseif ($index === 4 && $status === ApplicationStatus::Accepted) {
                $state = 'is-done';
            }
        @endphp
        <div class="pa-step {{ $state }}" role="listitem" @if ($index === $current) aria-current="step" @endif>
            <i>{{ $state === 'is-done' ? '✓' : ($state === 'is-bad' ? '×' : $index + 1) }}</i>
            <span>{{ $label }}</span>
        </div>
    @endforeach
</div>
