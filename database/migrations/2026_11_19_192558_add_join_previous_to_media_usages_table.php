<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto galeri proyek dapat digabung dengan foto di atasnya menjadi satu grup yang berbagi satu deskripsi
     * (mis. 2 atau 3 foto dengan 1 teks). Foto pertama galeri selalu memulai grup.
     */
    public function up(): void
    {
        Schema::table('media_usages', function (Blueprint $table): void {
            $table->boolean('join_previous')->default(false)->after('note_by');
        });
    }

    public function down(): void
    {
        Schema::table('media_usages', function (Blueprint $table): void {
            $table->dropColumn('join_previous');
        });
    }
};
