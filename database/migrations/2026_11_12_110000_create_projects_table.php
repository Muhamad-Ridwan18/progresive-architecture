<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            // Kolom berikut = versi yang DIPUBLIKASIKAN (yang dibaca frontend). Selama proyek
            // masih Draft, isinya hanya nilai awal dan belum tampil di publik.
            $table->string('name');
            $table->string('slug')->unique();
            // Kategori yang dipakai tidak boleh terhapus (SRS FR-CM-004), dijaga juga di database.
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->text('description')->nullable();

            // Hasil editan yang belum dipublikasikan (SRS 11.2). `draft_version` naik setiap
            // simpan draft dan dipakai untuk mendeteksi dua editor yang saling menimpa.
            $table->json('draft_data')->nullable();
            $table->unsignedInteger('draft_version')->default(1);

            $table->string('status', 30)->default('draft'); // draft | published | unpublished_changes | archived
            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index('category_id');
        });

        // Gambar milik draft dan gambar milik versi published dipisah (baris berbeda), agar galeri
        // yang sedang diedit tidak mengubah tampilan publik sebelum dipublikasikan.
        Schema::table('media_usages', function (Blueprint $table) {
            $table->boolean('is_draft')->default(true)->after('role');
            $table->index(['usable_type', 'usable_id', 'is_draft', 'role', 'sort_order'], 'media_usages_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('media_usages', function (Blueprint $table) {
            $table->dropIndex('media_usages_lookup');
            $table->dropColumn('is_draft');
        });

        Schema::dropIfExists('projects');
    }
};
