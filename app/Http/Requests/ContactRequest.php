<?php

namespace App\Http\Requests;

use App\Support\ContactSubjects;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validasi formulir kontak (SRS 3.8). */
class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'subject' => ['required', Rule::in(ContactSubjects::keys())],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka dan tanda + - ( ) .',
            'subject.in' => 'Pilih salah satu subjek yang tersedia.',
            'message.min' => 'Pesan terlalu singkat (minimal 10 karakter).',
        ];
    }

    /** Hapus karakter kontrol agar nama/email tidak dapat dipakai menyisipkan header email. */
    protected function prepareForValidation(): void
    {
        $clean = fn (?string $v): ?string => $v === null ? null : trim(preg_replace('/[\r\n\t]+/', ' ', $v));

        $this->merge([
            'name' => $clean($this->input('name')),
            'email' => $clean($this->input('email')),
            'phone' => $clean($this->input('phone')),
        ]);
    }
}
