<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            // Kolom berikut = versi yang DIPUBLIKASIKAN (dibaca frontend). Pola sama dengan `projects`.
            $table->string('title');
            $table->string('slug')->unique();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            // Hasil editan yang belum dipublikasikan (SRS 11.2) dan pengaman tabrakan edit (FR-WF-004).
            $table->json('draft_data')->nullable();
            $table->unsignedInteger('draft_version')->default(1);
            $table->string('draft_editor', 32)->nullable();

            // draft | scheduled | published | unpublished_changes (SRS matriks status).
            $table->string('status', 30)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();

            // Featured berlaku langsung (bukan bagian draft); hanya satu artikel pada satu waktu (FR-FE-072).
            $table->boolean('is_featured')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['status', 'scheduled_at']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
