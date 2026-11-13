<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
        Settings::setMany(['contact_email' => 'hello@acme.test']);
    }

    /** Ambil token waktu dari formulir, lalu majukan jam agar tidak dianggap terlalu cepat. */
    private function token(int $waitSeconds = 5): string
    {
        // Setelah kiriman sukses, kunjungan pertama menampilkan ucapan terima kasih (tanpa formulir).
        // Kunjungan berikutnya menampilkan formulir lagi, jadi coba sekali lagi bila perlu.
        foreach (range(1, 2) as $attempt) {
            $html = $this->get('/contact')->getContent();

            if (preg_match('/name="_t" value="([^"]+)"/', $html, $m)) {
                $this->travel($waitSeconds)->seconds();

                return $m[1];
            }
        }

        $this->fail('Formulir kontak tidak menampilkan token waktu.');
    }

    /** Setiap percobaan dari alamat IP berbeda agar pembatas laju tidak ikut menghitung. */
    private function fromIp(int $n): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$n}"]);
    }

    private function valid(array $override = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'phone' => '+62 812-3456-7890',
            'subject' => 'project',
            'message' => 'Kami ingin membangun gudang seluas 2.000 m2 di Cikarang.',
            'website_url' => '',
        ], $override);
    }

    private function submit(array $data, ?string $token = null)
    {
        return $this->post('/contact', $data + ['_t' => $token ?? $this->token()]);
    }

    // ---------------------------------------------------------------- tampil

    public function test_page_shows_the_form_with_all_fields_and_company_info(): void
    {
        Settings::setMany(['contact_phone' => '021-555-0100', 'office_hours' => 'Senin–Jumat 08.00–17.00']);

        $this->get('/contact')
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('name="subject"', false)
            ->assertSee('name="message"', false)
            ->assertSee('General inquiry')
            ->assertSee('021-555-0100')
            ->assertSee('Senin–Jumat 08.00–17.00')
            ->assertSee('name="_t"', false);
    }

    // ---------------------------------------------------------------- kirim

    public function test_valid_submission_queues_an_email_to_the_company_with_reply_to(): void
    {
        $this->submit($this->valid())->assertRedirect('/contact')->assertSessionHas('contact_sent');

        Mail::assertQueued(ContactMessage::class, fn (ContactMessage $m) => $m->hasTo('hello@acme.test')
            && $m->hasReplyTo('budi@example.test')
            && str_contains($m->envelope()->subject, 'Project inquiry')
            && str_contains($m->envelope()->subject, 'Budi Santoso')
            && $m->body === 'Kami ingin membangun gudang seluas 2.000 m2 di Cikarang.');
        Mail::assertQueuedCount(1);
    }

    public function test_mail_body_contains_all_details(): void
    {
        $mailable = new ContactMessage('Budi', 'budi@example.test', '0812', 'careers', 'Halo, ada lowongan?');
        $rendered = $mailable->render();

        $this->assertStringContainsString('Budi', $rendered);
        $this->assertStringContainsString('budi@example.test', $rendered);
        $this->assertStringContainsString('0812', $rendered);
        $this->assertStringContainsString('Careers', $rendered);
        $this->assertStringContainsString('Halo, ada lowongan?', $rendered);
    }

    public function test_message_is_not_stored_anywhere_in_the_database(): void
    {
        $before = $this->rowCounts();

        $this->submit($this->valid(['message' => 'PESAN-RAHASIA-12345 tidak boleh tersimpan']));

        $this->assertSame($before, $this->rowCounts());
        $this->assertDatabaseMissing('activity_log', ['description' => 'PESAN-RAHASIA-12345']);
    }

    /** @return array<string, int> */
    private function rowCounts(): array
    {
        return collect(['users', 'settings', 'activity_log', 'jobs', 'failed_jobs', 'projects', 'categories'])
            ->mapWithKeys(fn (string $t) => [$t => \DB::table($t)->count()])
            ->all();
    }

    public function test_phone_is_optional(): void
    {
        $this->submit($this->valid(['phone' => '']))->assertSessionHasNoErrors();

        Mail::assertQueuedCount(1);
    }

    // ---------------------------------------------------------------- validasi

    public function test_validation_rejects_bad_input_and_sends_nothing(): void
    {
        $token = $this->token();
        $ip = 100;

        foreach ([
            ['name' => '', 'field' => 'name'],
            ['email' => 'bukan-email', 'field' => 'email'],
            ['email' => '', 'field' => 'email'],
            ['phone' => 'abc-def', 'field' => 'phone'],
            ['subject' => 'hack', 'field' => 'subject'],
            ['subject' => '', 'field' => 'subject'],
            ['message' => 'pendek', 'field' => 'message'],
            ['message' => str_repeat('x', 5001), 'field' => 'message'],
        ] as $case) {
            $field = array_pop($case);
            $this->fromIp($ip++)->post('/contact', $this->valid($case) + ['_t' => $token])->assertSessionHasErrors($field);
        }

        Mail::assertNothingQueued();
    }

    public function test_header_injection_is_neutralised(): void
    {
        $this->submit($this->valid(['name' => "Budi\r\nBcc: korban@example.test"]));

        Mail::assertQueued(ContactMessage::class, function (ContactMessage $m) {
            $this->assertStringNotContainsString("\n", $m->senderName);
            $this->assertStringNotContainsString("\r", $m->envelope()->subject);
            $this->assertFalse($m->hasBcc('korban@example.test'));

            return true;
        });
    }

    public function test_old_input_is_escaped_when_redisplayed(): void
    {
        $token = $this->token();

        $response = $this->followingRedirects()->post('/contact', [
            'name' => '"><script>alert(1)</script>', 'email' => 'bad', 'subject' => 'general',
            'message' => 'pesan yang cukup panjang', '_t' => $token,
        ]);

        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    // ---------------------------------------------------------------- anti-spam

    public function test_honeypot_gets_a_fake_success_but_sends_nothing(): void
    {
        $this->submit($this->valid(['website_url' => 'http://spam.example']))
            ->assertRedirect('/contact')
            ->assertSessionHas('contact_sent');

        Mail::assertNothingQueued();
    }

    public function test_submissions_that_are_too_fast_are_rejected(): void
    {
        $this->submit($this->valid(), $this->token(waitSeconds: 0))->assertSessionHasErrors('form');

        Mail::assertNothingQueued();
    }

    public function test_expired_missing_and_forged_tokens_are_rejected(): void
    {
        $expired = $this->token(waitSeconds: 7300);

        $this->fromIp(1)->post('/contact', $this->valid() + ['_t' => $expired])->assertSessionHasErrors('form');
        $this->fromIp(2)->post('/contact', $this->valid())->assertSessionHasErrors('form');
        $this->fromIp(3)->post('/contact', $this->valid() + ['_t' => 'dipalsukan'])->assertSessionHasErrors('form');
        // Terenkripsi sah tetapi isinya bukan waktu.
        $this->fromIp(4)->post('/contact', $this->valid() + ['_t' => Crypt::encryptString('bukan-angka')])->assertSessionHasErrors('form');

        Mail::assertNothingQueued();
    }

    public function test_rate_limit_blocks_repeated_submissions_from_one_ip(): void
    {
        foreach (range(1, 5) as $i) {
            $this->submit($this->valid())->assertRedirect();
        }

        $this->submit($this->valid())->assertStatus(429);
        Mail::assertQueuedCount(5);

        // IP lain tidak terpengaruh.
        $this->fromIp(77)->post('/contact', $this->valid() + ['_t' => $this->token()])->assertRedirect();
        Mail::assertQueuedCount(6);
    }

    // ---------------------------------------------------------------- konfigurasi

    public function test_form_refuses_honestly_when_no_company_email_is_configured(): void
    {
        Settings::setMany(['contact_email' => '']);

        $this->submit($this->valid())
            ->assertSessionHasErrors('form')
            ->assertSessionMissing('contact_sent');

        Mail::assertNothingQueued();
    }
}
