<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessage;
use App\Services\Settings;
use App\Support\ContactSubjects;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Formulir kontak, hanya email (SRS 3.8): pesan dikirim ke email perusahaan, tidak disimpan.
 *
 * Perlindungan spam berlapis: pembatasan per IP (lihat RateLimiter 'contact'), kolom jebakan
 * (honeypot), dan token waktu terenkripsi yang menolak kiriman terlalu cepat atau kedaluwarsa.
 */
class ContactController extends Controller
{
    /** Kiriman lebih cepat dari ini dianggap bot. Cukup longgar agar isian otomatis browser tidak tertolak. */
    private const MIN_SECONDS = 3;

    /** Token lebih tua dari ini dianggap kedaluwarsa (formulir dibiarkan terbuka berjam-jam). */
    private const MAX_SECONDS = 7200;

    public function show()
    {
        return view('site.contact', [
            'subjects' => ContactSubjects::all(),
            'token' => Crypt::encryptString((string) now()->timestamp),
            'office' => Settings::get('office_hours'),
        ]);
    }

    public function send(ContactRequest $request)
    {
        // Honeypot: manusia tidak melihat kolom ini. Bot diberi "sukses" palsu agar tidak mencoba lagi.
        if (filled($request->input('website_url'))) {
            return redirect()->route('contact')->with('contact_sent', true);
        }

        $age = $this->formAge($request->input('_t'));

        if ($age === null || $age > self::MAX_SECONDS) {
            return $this->failure('Formulir sudah kedaluwarsa. Silakan kirim ulang.');
        }

        if ($age < self::MIN_SECONDS) {
            return $this->failure('Mohon tunggu sebentar sebelum mengirim, lalu coba lagi.');
        }

        $recipient = Settings::get('contact_email');

        // Tanpa alamat tujuan, "sukses" akan berbohong: pesan tidak pernah sampai. Tolak dengan jujur.
        if (blank($recipient)) {
            Log::error('Formulir kontak dikirim tetapi email perusahaan belum diatur di Website Settings.');

            return $this->failure('Formulir kontak belum dapat dipakai saat ini. Silakan hubungi kami melalui telepon.');
        }

        $data = $request->validated();

        Mail::to($recipient)->send(new ContactMessage(
            senderName: $data['name'],
            senderEmail: $data['email'],
            senderPhone: $data['phone'] ?? null,
            subjectKey: $data['subject'],
            body: $data['message'],
        ));

        return redirect()->route('contact')->with('contact_sent', true);
    }

    /** Umur token dalam detik; null bila hilang, rusak, atau dipalsukan. */
    private function formAge(?string $token): ?int
    {
        if (blank($token)) {
            return null;
        }

        try {
            return now()->timestamp - (int) Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }
    }

    private function failure(string $message)
    {
        return back()->withInput()->withErrors(['form' => $message]);
    }
}
