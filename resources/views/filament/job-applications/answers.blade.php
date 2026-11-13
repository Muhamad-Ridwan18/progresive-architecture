{{--
    Jawaban lamaran dirender dari SNAPSHOT formulir saat dikirim (SRS FR-RC-023), bukan dari formulir
    lowongan yang sekarang, sehingga perubahan formulir tidak merusak lamaran lama.
--}}
@include('filament.job-applications._styles')

@php
    $application = $getRecord();
    $answers = $application->answers ?? [];
    $files = $application->files->keyBy('field_key');
@endphp

<dl class="pa-answers">
    @forelse ($application->form_snapshot ?? [] as $field)
        @continue(($field['type'] ?? null) === 'section')

        @php
            $key = $field['key'];
            $value = $answers[$key] ?? null;
        @endphp

        <div class="pa-q">
            <dt>{{ $field['label'] }}</dt>
            <dd>
                @if (($field['type'] ?? null) === 'file')
                    @if ($files->has($key))
                        {{ $files[$key]->original_name }} ({{ $files[$key]->humanSize() }})
                    @else
                        —
                    @endif
                @elseif (($field['type'] ?? null) === 'repeater')
                    @php $columns = preg_split('/\R/', trim((string) ($field['columns'] ?? ''))) ?: []; @endphp
                    @forelse ((array) $value as $row)
                        <p>
                            @foreach ($columns as $c => $column)
                                <span class="pa-muted">{{ $column }}:</span> {{ $row[$c] ?? '—' }}@unless ($loop->last) · @endunless
                            @endforeach
                        </p>
                    @empty
                        —
                    @endforelse
                @elseif (is_array($value))
                    {{ $value ? implode(', ', $value) : '—' }}
                @elseif (is_bool($value))
                    {{ $value ? 'Ya' : 'Tidak' }}
                @else
                    <span style="white-space: pre-line">{{ filled($value) ? $value : '—' }}</span>
                @endif
            </dd>
        </div>
    @empty
        <p class="pa-muted">Tidak ada jawaban.</p>
    @endforelse
</dl>
