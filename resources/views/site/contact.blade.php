@php
    // Kelas kolom formulir: garis bawah minimal, sesuai gaya editorial (STYLE GUIDE 5.4).
    $field = 'mt-2 block w-full border-0 border-b border-ink bg-transparent px-0 py-3 text-body placeholder:text-ink-muted/60';
@endphp

<x-layouts.app title="Contact" description="Hubungi kami untuk pertanyaan proyek, kerja sama, atau karier.">
    <div class="container-page grid gap-14 py-14 md:grid-cols-12 md:py-20">

        {{-- Informasi kontak dari Website Settings (mockup): Location, Email, General information --}}
        <section class="md:col-span-5">
            <h1 class="text-display font-medium uppercase leading-[1.05] tracking-[-0.02em]">Contact</h1>

            <dl class="mt-16 space-y-10 text-lead">
                @if ($site['company_address'])
                    <div>
                        <dt class="label text-ink-muted">Location</dt>
                        <dd class="mt-3 whitespace-pre-line">{{ $site['company_address'] }}</dd>
                    </div>
                @endif
                @if ($site['contact_email'])
                    <div>
                        <dt class="label text-ink-muted">Email</dt>
                        <dd class="mt-3"><a href="mailto:{{ $site['contact_email'] }}" class="break-all hover:underline">{{ $site['contact_email'] }}</a></dd>
                    </div>
                @endif
                @if ($site['contact_phone'])
                    <div>
                        <dt class="label text-ink-muted">General information</dt>
                        <dd class="mt-3"><a href="tel:{{ preg_replace('/[^\d+]/', '', $site['contact_phone']) }}" class="hover:underline">{{ $site['contact_phone'] }}</a></dd>
                    </div>
                @endif
                @if ($office)
                    <div>
                        <dt class="label text-ink-muted">Office hours</dt>
                        <dd class="mt-3 whitespace-pre-line">{{ $office }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        <section class="md:col-span-7" aria-labelledby="contact-form-title">
            <h2 id="contact-form-title" class="sr-only">Send us a message</h2>

            @if (session('contact_sent'))
                <div role="status" class="border border-ink p-6">
                    <p class="text-h3 font-semibold">Terima kasih, pesan Anda terkirim.</p>
                    <p class="mt-2 text-ink-muted">Kami akan membalas ke email yang Anda berikan.</p>
                </div>
            @else
                @error('form')
                    <div role="alert" class="mb-8 border border-ink p-4 text-small">{{ $message }}</div>
                @enderror

                <form method="POST" action="{{ route('contact.send') }}" class="space-y-8" novalidate>
                    @csrf
                    <input type="hidden" name="_t" value="{{ $token }}">

                    {{-- Kolom jebakan untuk bot: tidak terlihat dan tidak dapat difokuskan manusia. --}}
                    <div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
                        <label>Leave this field empty
                            <input type="text" name="website_url" tabindex="-1" autocomplete="off" value="">
                        </label>
                    </div>

                    <div class="grid gap-8 md:grid-cols-2">
                        <div>
                            <label for="name" class="label text-ink-muted">Full name</label>
                            <input id="name" name="name" type="text" required autocomplete="name" maxlength="120"
                                   value="{{ old('name') }}" class="{{ $field }}"
                                   @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                            @error('name') <p id="name-error" class="mt-2 text-small text-[#b3261e]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="email" class="label text-ink-muted">Email</label>
                            <input id="email" name="email" type="email" required autocomplete="email" maxlength="190"
                                   value="{{ old('email') }}" class="{{ $field }}"
                                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            @error('email') <p id="email-error" class="mt-2 text-small text-[#b3261e]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-8 md:grid-cols-2">
                        <div>
                            <label for="phone" class="label text-ink-muted">Phone <span class="normal-case tracking-normal">(optional)</span></label>
                            <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="30"
                                   value="{{ old('phone') }}" class="{{ $field }}"
                                   @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                            @error('phone') <p id="phone-error" class="mt-2 text-small text-[#b3261e]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="subject" class="label text-ink-muted">Subject</label>
                            <select id="subject" name="subject" required class="{{ $field }}"
                                    @error('subject') aria-invalid="true" aria-describedby="subject-error" @enderror>
                                <option value="" disabled @selected(! old('subject'))>Choose a subject</option>
                                @foreach ($subjects as $key => $label)
                                    <option value="{{ $key }}" @selected(old('subject') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('subject') <p id="subject-error" class="mt-2 text-small text-[#b3261e]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="message" class="label text-ink-muted">Message</label>
                        <textarea id="message" name="message" rows="6" required maxlength="5000"
                                  class="{{ $field }}"
                                  @error('message') aria-invalid="true" aria-describedby="message-error" @enderror>{{ old('message') }}</textarea>
                        @error('message') <p id="message-error" class="mt-2 text-small text-[#b3261e]">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit"
                            class="label bg-ink px-8 py-4 text-white transition-opacity duration-150 hover:opacity-80">
                        Send message
                    </button>
                </form>
            @endif
        </section>
    </div>
</x-layouts.app>
