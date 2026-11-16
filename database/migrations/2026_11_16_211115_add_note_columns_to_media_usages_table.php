<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teks yang tampil tepat setelah sebuah foto di deretan galeri proyek (seperti halaman proyek BIG).
     * `note_by` diisi bila teks itu kutipan: nama dan jabatan pengucapnya.
     */
    public function up(): void
    {
        Schema::table('media_usages', function (Blueprint $table): void {
            $table->text('note')->nullable()->after('caption');
            $table->string('note_by', 255)->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('media_usages', function (Blueprint $table): void {
            $table->dropColumn(['note', 'note_by']);
        });
    }
};
