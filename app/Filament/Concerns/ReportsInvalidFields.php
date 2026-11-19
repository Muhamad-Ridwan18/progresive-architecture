<?php

namespace App\Filament\Concerns;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * Menyebut field mana yang belum memenuhi syarat. Dipakai halaman editor (proyek, artikel, About Us) agar subjudul
 * ("Not saved — fix: Name, Category") dan notifikasi "Tidak dapat publish" tidak hanya berkata "ada field bermasalah",
 * tetapi menyebut nama field dan alasannya.
 *
 * Membutuhkan `$this->form` (skema Filament) pada halaman pemakainya.
 */
trait ReportsInvalidFields
{
    /**
     * Nama field yang gagal divalidasi pada penyimpanan terakhir (dikunci agar tidak dapat diubah dari browser).
     *
     * @var list<string>
     */
    #[Locked]
    public array $invalidFields = [];

    /** @param array<string, array<int, string>> $errors galat validasi: kunci field => daftar pesan */
    protected function rememberInvalidFields(array $errors): void
    {
        $this->invalidFields = collect(array_keys($errors))
            ->map(fn (string $key): string => $this->fieldLabel($key))
            ->unique()
            ->values()
            ->all();
    }

    protected function clearInvalidFields(): void
    {
        $this->invalidFields = [];
    }

    /** Ringkasan satu baris, mis. "Name, Category". */
    protected function invalidFieldsSummary(): string
    {
        return implode(', ', $this->invalidFields);
    }

    /**
     * Rincian "Nama field: alasan" per field untuk isi notifikasi (maksimal 6 baris).
     *
     * @param  array<string, array<int, string>>  $errors
     * @return list<string>
     */
    protected function describeErrors(array $errors): array
    {
        $lines = [];

        foreach ($errors as $key => $messages) {
            $lines[] = $this->fieldLabel((string) $key).': '.($messages[0] ?? 'tidak valid');
        }

        $lines = array_values(array_unique($lines));

        return count($lines) > 6 ? [...array_slice($lines, 0, 6), '…dan '.(count($lines) - 6).' field lain'] : $lines;
    }

    /** @param array<string, array<int, string>> $errors */
    protected function errorsAsHtml(array $errors): HtmlString
    {
        return new HtmlString(implode('<br>', array_map(e(...), $this->describeErrors($errors))));
    }

    /** Nama tampilan field untuk kunci galat seperti `data.name` atau `credits.0.title`. */
    private function fieldLabel(string $key): string
    {
        $segments = array_values(array_filter(
            explode('.', $key),
            fn (string $segment): bool => $segment !== '' && ! ctype_digit($segment),
        ));

        if (($segments[0] ?? null) === 'data') {
            array_shift($segments);
        }

        if ($segments === []) {
            return Str::headline($key);
        }

        $top = $this->topLevelLabels()[$segments[0]] ?? Str::headline($segments[0]);

        return count($segments) > 1 ? $top.' › '.Str::headline((string) end($segments)) : $top;
    }

    /** @return array<string, string> nama field tingkat atas => label */
    private function topLevelLabels(): array
    {
        try {
            $labels = [];

            foreach ($this->form->getFlatFields(withHidden: true) as $field) {
                $label = trim(strip_tags((string) $field->getLabel()));

                if ($label !== '') {
                    $labels[$field->getName()] ??= $label;
                }
            }

            return $labels;
        } catch (Throwable) {
            return [];
        }
    }
}
