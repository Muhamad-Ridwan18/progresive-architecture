<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data teknis proyek seperti di company profile: klien, tipologi, luas tanah dan bangunan, jumlah lantai,
     * dan status pekerjaan. Semuanya opsional dan ikut alur draft/publish seperti field proyek lain.
     * `stage` (bukan `status`) karena `status` sudah dipakai untuk status publikasi.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('client')->nullable()->after('year');
            $table->string('typology', 80)->nullable()->after('client');
            $table->decimal('land_area', 9, 1)->nullable()->after('typology');
            $table->decimal('building_area', 9, 1)->nullable()->after('land_area');
            $table->unsignedTinyInteger('storeys')->nullable()->after('building_area');
            $table->string('stage', 40)->nullable()->after('storeys');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['client', 'typology', 'land_area', 'building_area', 'storeys', 'stage']);
        });
    }
};
