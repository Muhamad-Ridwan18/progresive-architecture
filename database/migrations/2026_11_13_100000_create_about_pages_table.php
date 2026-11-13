<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Halaman About Us hanya satu (satu baris, id = 1). Strukturnya tetap dengan enam bagian
        // (SRS 3.5), jadi seluruh isinya disimpan sebagai satu dokumen JSON per versi.
        Schema::create('about_pages', function (Blueprint $table) {
            $table->id();
            $table->json('draft')->nullable();      // hasil editan yang belum dipublikasikan
            $table->json('published')->nullable();  // versi yang tampil di website; null = belum pernah terbit
            $table->unsignedInteger('draft_version')->default(1);
            $table->string('status', 30)->default('draft'); // draft | published | unpublished_changes
            $table->timestamp('published_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('about_pages');
    }
};
