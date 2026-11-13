<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_vacancies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->foreignId('job_category_id')->constrained('job_categories')->restrictOnDelete();
            $table->string('department')->nullable();
            $table->string('location');
            $table->string('employment_type', 40);
            $table->text('description')->nullable();
            $table->text('qualifications')->nullable();
            $table->text('required_documents')->nullable();
            $table->date('deadline')->nullable();
            $table->string('status', 20)->default('draft');
            // Salinan formulir yang diwariskan dari template kategori dan dapat disesuaikan per lowongan (FR-RC-011).
            $table->json('form_fields')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_vacancies');
    }
};
