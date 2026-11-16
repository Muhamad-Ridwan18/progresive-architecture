<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kredit proyek (seperti slide terakhir di halaman proyek BIG): daftar kelompok, masing-masing berjudul
     * (mis. "Project Team") dan berisi nama-nama satu per baris. Mengikuti alur draft/publish seperti field lain.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->json('credits')->nullable()->after('stage');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('credits');
        });
    }
};
