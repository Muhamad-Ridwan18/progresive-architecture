<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Koordinat lokasi proyek untuk peta di akhir deretan foto (seperti halaman proyek BIG). Opsional; peta hanya
     * tampil bila keduanya terisi. Mengikuti alur draft/publish seperti field lain.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->decimal('latitude', 9, 6)->nullable()->after('location');
            $table->decimal('longitude', 9, 6)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
