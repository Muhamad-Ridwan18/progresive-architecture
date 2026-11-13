<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 30)->nullable()->unique();
            $table->foreignId('job_vacancy_id')->constrained('job_vacancies')->cascadeOnDelete();
            $table->string('applicant_name');
            $table->string('applicant_email');
            $table->string('applicant_phone', 40)->nullable();
            $table->string('status', 20)->default('submitted');
            // Jawaban dan salinan struktur formulir saat dikirim (SRS FR-RC-023).
            $table->json('answers');
            $table->json('form_snapshot');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
            $table->index('applicant_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
