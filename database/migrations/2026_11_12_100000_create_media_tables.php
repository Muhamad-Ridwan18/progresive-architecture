<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // File fisik. Satu baris per isi gambar unik (deduplikasi lewat SHA-256, SRS 12.7).
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->char('sha256', 64)->unique();
            $table->string('original_name');
            $table->string('mime', 50);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('status', 20)->default('pending'); // pending | processing | ready | failed
            $table->text('error')->nullable();
            $table->json('variants')->nullable(); // daftar lebar varian yang berhasil dibuat
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('committed_at')->nullable(); // pertama kali dipakai oleh konten
            $table->timestamp('orphaned_at')->nullable(); // sejak kapan tanpa referensi (antrean hapus)
            $table->timestamps();

            $table->index('status');
            $table->index('orphaned_at');
        });

        // Penggunaan gambar oleh konten. Alt text, caption, dan focal point disimpan per penggunaan
        // sehingga boleh berbeda di tiap konten (SRS 12.6 dan 12.7).
        Schema::create('media_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_file_id')->constrained('media_files')->restrictOnDelete();
            $table->morphs('usable'); // proyek, artikel, dst.
            $table->string('role', 30); // cover | gallery | inline
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->json('focal')->nullable(); // {desktop:{x,y}, mobile:{x,y}}
            $table->timestamps();

            $table->index(['usable_type', 'usable_id', 'role', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media_files');
    }
};
