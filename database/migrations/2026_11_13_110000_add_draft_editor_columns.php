<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `draft_editor` = identitas editor (tab) yang terakhir menyimpan draft. Dipakai agar permintaan
     * simpan yang tumpang tindih dari editor yang SAMA tidak dianggap tabrakan, sementara tab lain
     * atau administrator lain tetap terdeteksi lewat nomor versi.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('draft_editor', 32)->nullable()->after('draft_version');
        });

        Schema::table('about_pages', function (Blueprint $table) {
            $table->string('draft_editor', 32)->nullable()->after('draft_version');
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn('draft_editor'));
        Schema::table('about_pages', fn (Blueprint $t) => $t->dropColumn('draft_editor'));
    }
};
