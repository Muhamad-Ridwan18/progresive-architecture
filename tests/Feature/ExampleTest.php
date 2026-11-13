<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /** Homepage (daftar proyek) dapat dibuka walau belum ada konten sama sekali. */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->withoutVite();

        $this->get('/')->assertStatus(200);
    }
}
