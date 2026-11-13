<?php

namespace App\Mail;

use App\Support\ContactSubjects;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Pesan dari formulir kontak, dikirim ke email perusahaan (SRS 3.8). Tidak disimpan di database.
 *
 * Dikirim lewat antrean dengan percobaan ulang. Bila semua percobaan gagal, job (berikut isi pesan)
 * tetap tersimpan di tabel `failed_jobs` sehingga tidak hilang diam-diam.
 */
class ContactMessage extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    /** @return list<int> detik antar percobaan */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public ?string $senderPhone,
        public string $subjectKey,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // Balas langsung ke pengirim.
            replyTo: [new Address($this->senderEmail, $this->senderName)],
            subject: '[Website] '.ContactSubjects::label($this->subjectKey).' — '.$this->senderName,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact-message');
    }
}
