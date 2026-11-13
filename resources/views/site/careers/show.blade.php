@php
    $open = $vacancy->isOpen();
    $reference = session('application_reference');
@endphp

<x-layouts.app :title="$vacancy->title.' — Careers'" :description="\Illuminate\Support\Str::limit(strip_tags((string) $vacancy->description), 160)">
    <div class="container-page py-14 md:py-20">
        <a href="{{ route('careers') }}" class="label inline-flex min-h-11 items-center text-ink-muted hover:text-ink">&larr; Careers</a>

        <div class="mt-6 grid gap-10 md:grid-cols-12">
            <h1 class="text-display font-medium uppercase leading-[1.05] tracking-[-0.02em] md:col-span-8">{{ $vacancy->title }}</h1>

            <dl class="text-small md:col-span-4">
                @foreach ([
                    'Departemen' => $vacancy->department ?: $vacancy->category->name,
                    'Lokasi' => $vacancy->location,
                    'Tipe' => $vacancy->employment_type,
                    'Deadline' => $vacancy->deadline?->translatedFormat('d M Y') ?? 'Terbuka',
                ] as $term => $value)
                    <div class="flex justify-between border-t border-line py-3 last:border-b">
                        <dt class="text-ink-muted">{{ $term }}</dt>
                        <dd>{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="mt-16 grid gap-12 border-t border-line pt-8 md:grid-cols-12">
            <div class="space-y-12 md:col-span-7">
                @if ($vacancy->description)
                    <section>
                        <h2 class="text-h2 font-semibold">Deskripsi pekerjaan</h2>
                        <x-prose :text="$vacancy->description" class="mt-4 max-w-[68ch]" />
                    </section>
                @endif

                @if ($vacancy->qualifications)
                    <section>
                        <h2 class="text-h2 font-semibold">Kualifikasi</h2>
                        <x-prose :text="$vacancy->qualifications" class="mt-4 max-w-[68ch]" />
                    </section>
                @endif

                @if ($vacancy->required_documents)
                    <section>
                        <h2 class="text-h2 font-semibold">Dokumen yang diperlukan</h2>
                        <x-prose :text="$vacancy->required_documents" class="mt-4 max-w-[68ch]" />
                    </section>
                @endif
            </div>

            <section class="md:col-span-5" aria-labelledby="apply-title">
                <h2 id="apply-title" class="text-h2 font-semibold">Lamar posisi ini</h2>

                @if ($reference)
                    <div role="status" class="mt-6 border border-ink p-6">
                        <p class="text-h3 font-semibold">Terima kasih, lamaran Anda terkirim.</p>
                        <p class="mt-2 text-ink-muted">Simpan nomor referensi berikut:</p>
                        <p class="mt-2 text-h2 font-semibold tabular-nums">{{ $reference }}</p>
                    </div>
                @elseif (! $open)
                    <div class="mt-6 border border-line p-6">
                        <p class="font-semibold">Lowongan ini sudah ditutup.</p>
                        <p class="mt-1 text-ink-muted">Lamaran tidak lagi diterima untuk posisi ini.</p>
                        <a href="{{ route('careers', ['status' => 'open']) }}" class="label mt-4 inline-block underline underline-offset-4">Lihat lowongan terbuka</a>
                    </div>
                @else
                    @error('form')
                        <div role="alert" class="mt-6 border border-ink p-4 text-small">{{ $message }}</div>
                    @enderror

                    <form method="POST" action="{{ route('careers.apply', $vacancy) }}" enctype="multipart/form-data" class="mt-6 space-y-8" novalidate>
                        @csrf

                        <div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
                            <label>Leave this field empty
                                <input type="text" name="website_url" tabindex="-1" autocomplete="off" value="">
                            </label>
                        </div>

                        <div>
                            <p class="label text-ink-muted">Posisi</p>
                            <p class="mt-1">{{ $vacancy->title }}</p>
                        </div>

                        @foreach ($fields as $field)
                            @include('site.careers._field', ['field' => $field])
                        @endforeach

                        <div>
                            <label class="flex items-start gap-3 text-small">
                                <input type="checkbox" name="consent" value="1" class="mt-1 h-4 w-4" @checked(old('consent'))>
                                <span>Saya menyetujui data saya diproses untuk keperluan rekrutmen.</span>
                            </label>
                            @error('consent') <p class="mt-2 text-small text-[#b3261e]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <button type="submit" class="label bg-ink px-8 py-4 text-white transition-opacity duration-150 hover:opacity-80">
                                Kirim lamaran
                            </button>
                            <p class="mt-3 text-small text-ink-muted">Setelah terkirim, Anda akan menerima nomor referensi lamaran.</p>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-layouts.app>
