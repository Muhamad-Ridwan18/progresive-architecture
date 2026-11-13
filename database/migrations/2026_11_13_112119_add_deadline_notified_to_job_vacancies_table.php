<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda notifikasi Job Deadline yang sudah dikirim (SRS FR-NT-006), agar scheduler yang berjalan
     * setiap hari tidak mengirim notifikasi yang sama berulang. Dua tahap: H-3 dan hari-H.
     */
    public function up(): void
    {
        Schema::table('job_vacancies', function (Blueprint $table) {
            $table->timestamp('deadline_h3_notified_at')->nullable()->after('published_at');
            $table->timestamp('deadline_h0_notified_at')->nullable()->after('deadline_h3_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('job_vacancies', function (Blueprint $table) {
            $table->dropColumn(['deadline_h3_notified_at', 'deadline_h0_notified_at']);
        });
    }
};
