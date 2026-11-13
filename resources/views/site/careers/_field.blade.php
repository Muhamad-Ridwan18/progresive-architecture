{{--
    Satu field formulir lamaran dinamis (SRS 6.2). Nama input: answers[key] untuk jawaban, files[key] untuk berkas.
    Error validasi memakai notasi titik: answers.key.
--}}
@php
    use App\Enums\FormFieldType;
    use App\Support\ApplicationForm;

    $type = FormFieldType::from($field['type']);
    $key = $field['key'];
    $id = 'field-'.$key;
    $errorKey = ($type === FormFieldType::File ? 'files.' : 'answers.').$key;
    $required = (bool) ($field['required'] ?? false);
    $options = ApplicationForm::lines($field['options'] ?? null);
    $inputClass = 'mt-2 block w-full border-0 border-b border-ink bg-transparent px-0 py-3 text-body placeholder:text-ink-muted/60';
    $boxClass = 'mt-2 block w-full border border-ink bg-transparent px-3 py-3 text-body';
    $describedBy = trim(($field['description'] ?? null ? $id.'-hint ' : '').($errors->has($errorKey) ? $id.'-error' : ''));
@endphp

@if ($type === FormFieldType::Section)
    <div class="border-t border-line pt-6">
        <h3 class="text-h3 font-semibold">{{ $field['label'] }}</h3>
        @if ($field['description'] ?? null)
            <p class="mt-1 text-small text-ink-muted">{{ $field['description'] }}</p>
        @endif
    </div>
@else
    <div>
        @if (in_array($type, [FormFieldType::Radio, FormFieldType::Checkbox], true) && $options !== [])
            <fieldset @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
                <legend class="label text-ink-muted">{{ $field['label'] }}@unless ($required) <span class="normal-case tracking-normal">(opsional)</span>@endunless</legend>
        @else
            <label for="{{ $id }}" class="label text-ink-muted">{{ $field['label'] }}@unless ($required) <span class="normal-case tracking-normal">(opsional)</span>@endunless</label>
        @endif

        @if ($field['description'] ?? null)
            <p id="{{ $id }}-hint" class="mt-1 text-small text-ink-muted">{{ $field['description'] }}</p>
        @endif

        @switch($type)
            @case(FormFieldType::Text)
            @case(FormFieldType::Email)
            @case(FormFieldType::Phone)
            @case(FormFieldType::Number)
            @case(FormFieldType::Date)
                <input id="{{ $id }}" name="answers[{{ $key }}]" @required($required)
                       type="{{ ['email' => 'email', 'phone' => 'tel', 'number' => 'number', 'date' => 'date'][$type->value] ?? 'text' }}"
                       @if ($type === FormFieldType::Number) step="any" @endif
                       placeholder="{{ $field['placeholder'] ?? '' }}" value="{{ old('answers.'.$key) }}" class="{{ $inputClass }}"
                       @if ($errors->has($errorKey)) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
                @break

            @case(FormFieldType::Textarea)
                <textarea id="{{ $id }}" name="answers[{{ $key }}]" rows="5" @required($required) maxlength="5000"
                          placeholder="{{ $field['placeholder'] ?? '' }}" class="{{ $boxClass }}"
                          @if ($errors->has($errorKey)) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>{{ old('answers.'.$key) }}</textarea>
                @break

            @case(FormFieldType::Dropdown)
                <select id="{{ $id }}" name="answers[{{ $key }}]" @required($required) class="{{ $inputClass }}"
                        @if ($errors->has($errorKey)) aria-invalid="true" @endif>
                    <option value="">{{ $field['placeholder'] ?: 'Pilih salah satu' }}</option>
                    @foreach ($options as $option)
                        <option value="{{ $option }}" @selected(old('answers.'.$key) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @break

            @case(FormFieldType::Radio)
                <div class="mt-3 space-y-2">
                    @foreach ($options as $option)
                        <label class="flex items-center gap-3">
                            <input type="radio" name="answers[{{ $key }}]" value="{{ $option }}" class="h-4 w-4" @checked(old('answers.'.$key) === $option)>
                            <span>{{ $option }}</span>
                        </label>
                    @endforeach
                </div>
                @break

            @case(FormFieldType::Checkbox)
                @if ($options === [])
                    <label class="mt-3 flex items-start gap-3 text-small">
                        <input id="{{ $id }}" type="checkbox" name="answers[{{ $key }}]" value="1" class="mt-1 h-4 w-4" @checked(old('answers.'.$key))>
                        <span>{{ $field['placeholder'] ?: 'Ya' }}</span>
                    </label>
                @else
                    <div class="mt-3 space-y-2">
                        @foreach ($options as $option)
                            <label class="flex items-center gap-3">
                                <input type="checkbox" name="answers[{{ $key }}][]" value="{{ $option }}" class="h-4 w-4"
                                       @checked(in_array($option, (array) old('answers.'.$key), true))>
                                <span>{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                @break

            @case(FormFieldType::File)
                @php
                    $accept = ApplicationForm::acceptedTypes($field);
                    $maxMb = ApplicationForm::maxMb($field);
                @endphp
                <input id="{{ $id }}" type="file" name="files[{{ $key }}]" @required($required)
                       accept="{{ collect($accept)->map(fn ($ext) => '.'.$ext)->implode(',') }}"
                       class="mt-2 block w-full text-small file:mr-4 file:border file:border-ink file:bg-transparent file:px-4 file:py-2 file:text-small"
                       @if ($errors->has($errorKey)) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
                <p class="mt-1 text-small text-ink-muted">{{ strtoupper(implode(', ', $accept)) }}, maks. {{ $maxMb }} MB.</p>
                @break

            @case(FormFieldType::Repeater)
                @php
                    $columns = ApplicationForm::lines($field['columns'] ?? null) ?: [$field['label']];
                    $rows = array_values(old('answers.'.$key) ?: [array_fill(0, count($columns), '')]);
                    $rowFields = fn (string|int $index, array $values) => collect($columns)->map(fn ($column, $c) => [$column, $c, $values[$c] ?? '']);
                @endphp
                {{-- Baris dirender server; tombol tambah/hapus diaktifkan js/careers.js (tanpa JS, baris yang ada tetap bisa diisi). --}}
                <div data-repeater data-max="20" class="mt-3 space-y-4">
                    <div data-repeater-rows class="space-y-4">
                        @foreach ($rows as $index => $values)
                            <div data-repeater-row class="grid gap-3 border border-line p-4">
                                @foreach ($rowFields($index, (array) $values) as [$column, $c, $value])
                                    <label class="text-small">
                                        <span class="text-ink-muted">{{ $column }}</span>
                                        <input type="text" maxlength="500" name="answers[{{ $key }}][{{ $index }}][{{ $c }}]" value="{{ $value }}"
                                               class="mt-1 block w-full border-0 border-b border-ink bg-transparent px-0 py-2">
                                    </label>
                                @endforeach
                                <button type="button" data-repeater-remove hidden class="label w-fit text-ink-muted hover:text-ink">Hapus</button>
                            </div>
                        @endforeach
                    </div>
                    <template data-repeater-template>
                        <div data-repeater-row class="grid gap-3 border border-line p-4">
                            @foreach ($columns as $c => $column)
                                <label class="text-small">
                                    <span class="text-ink-muted">{{ $column }}</span>
                                    <input type="text" maxlength="500" name="answers[{{ $key }}][__INDEX__][{{ $c }}]" value=""
                                           class="mt-1 block w-full border-0 border-b border-ink bg-transparent px-0 py-2">
                                </label>
                            @endforeach
                            <button type="button" data-repeater-remove class="label w-fit text-ink-muted hover:text-ink">Hapus</button>
                        </div>
                    </template>
                    <button type="button" data-repeater-add hidden class="label underline underline-offset-4">+ Tambah baris</button>
                </div>
                @break
        @endswitch

        @if (in_array($type, [FormFieldType::Radio, FormFieldType::Checkbox], true) && $options !== [])
            </fieldset>
        @endif

        @error($errorKey) <p id="{{ $id }}-error" class="mt-2 text-small text-[#b3261e]">{{ $message }}</p> @enderror
        @foreach ($errors->get($errorKey.'.*') as $messages)
            <p class="mt-2 text-small text-[#b3261e]">{{ $messages[0] }}</p>
        @endforeach
    </div>
@endif
