<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pustaka isian formulir lamaran (SRS 6.2): admin mencentang isian yang dipakai per lowongan, bukan menyusun
     * dari nol. Template kategori kini berupa daftar centang bawaan (`default_field_keys`), bukan daftar field JSON.
     * Lowongan tetap menyimpan salinan formulirnya sendiri (`job_vacancies.form_fields`) dan lamaran menyimpan
     * snapshot, jadi data yang sudah ada tidak berubah.
     */
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('type', 20);
            $table->string('label');
            $table->string('description')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('options')->nullable();
            $table->text('columns')->nullable();
            $table->json('accept')->nullable();
            $table->unsignedTinyInteger('max_mb')->nullable();
            $table->boolean('required_default')->default(false);
            // Isian inti (nama, email, telepon) selalu ada di setiap formulir dan tidak dapat dihapus.
            $table->boolean('is_core')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('job_categories', function (Blueprint $table) {
            $table->json('default_field_keys')->nullable()->after('sort_order');
            $table->json('default_required_keys')->nullable()->after('default_field_keys');
        });

        $this->seedStandardFields();
        $this->convertCategoryTemplates();

        Schema::table('job_categories', function (Blueprint $table) {
            $table->dropColumn('form_fields');
        });
    }

    public function down(): void
    {
        Schema::table('job_categories', function (Blueprint $table) {
            $table->json('form_fields')->nullable()->after('sort_order');
            $table->dropColumn(['default_field_keys', 'default_required_keys']);
        });

        Schema::dropIfExists('form_fields');
    }

    /** Isian standar. Kunci (`key`) tidak berubah setelah dibuat; label boleh diubah admin. */
    private function seedStandardFields(): void
    {
        $fields = [
            ['full_name', 'text', 'Nama lengkap', ['required_default' => true, 'is_core' => true]],
            ['email', 'email', 'Email', ['required_default' => true, 'is_core' => true]],
            ['phone', 'phone', 'Nomor telepon', ['required_default' => true, 'is_core' => true]],
            ['cv', 'file', 'CV (PDF)', ['required_default' => true, 'accept' => ['pdf'], 'max_mb' => 5]],
            ['portfolio', 'file', 'Portofolio (PDF)', ['accept' => ['pdf'], 'max_mb' => 10]],
            ['certificate', 'file', 'Ijazah atau sertifikat (PDF)', ['accept' => ['pdf'], 'max_mb' => 5]],
            ['cover_letter', 'textarea', 'Surat lamaran', []],
            ['portfolio_link', 'text', 'Tautan portofolio online', ['placeholder' => 'https://']],
            ['city', 'text', 'Domisili (kota)', []],
            ['edu', 'dropdown', 'Pendidikan terakhir', ['required_default' => true, 'options' => "SMA/SMK\nD3\nS1\nS2"]],
            ['major', 'text', 'Jurusan', []],
            ['school', 'text', 'Asal kampus atau sekolah', []],
            ['exp', 'repeater', 'Pengalaman kerja', ['columns' => "Perusahaan\nJabatan\nTahun"]],
            ['years_experience', 'number', 'Lama pengalaman kerja (tahun)', []],
            ['software', 'checkbox', 'Kemampuan software', ['options' => "AutoCAD\nSketchUp\nRevit\nLumion\nEnscape\nAdobe Photoshop"]],
            ['expected_salary', 'number', 'Ekspektasi gaji (Rp)', []],
            ['available_from', 'date', 'Tanggal bisa mulai bekerja', []],
            ['relocate', 'checkbox', 'Penempatan', ['placeholder' => 'Bersedia ditempatkan di luar kota']],
        ];

        foreach ($fields as $i => [$key, $type, $label, $extra]) {
            DB::table('form_fields')->insert([
                'key' => $key,
                'type' => $type,
                'label' => $label,
                'description' => $extra['description'] ?? null,
                'placeholder' => $extra['placeholder'] ?? null,
                'options' => $extra['options'] ?? null,
                'columns' => $extra['columns'] ?? null,
                'accept' => isset($extra['accept']) ? json_encode($extra['accept']) : null,
                'max_mb' => $extra['max_mb'] ?? null,
                'required_default' => $extra['required_default'] ?? false,
                'is_core' => $extra['is_core'] ?? false,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Template lama (daftar field JSON per kategori) menjadi daftar centang bawaan. Field yang belum ada di
     * pustaka dijadikan entri pustaka baru supaya tidak ada yang hilang.
     */
    private function convertCategoryTemplates(): void
    {
        $known = DB::table('form_fields')->pluck('key')->all();
        $next = (int) DB::table('form_fields')->max('sort_order');

        foreach (DB::table('job_categories')->get() as $category) {
            $fields = json_decode((string) $category->form_fields, true) ?: [];
            $keys = [];
            $required = [];

            foreach ($fields as $field) {
                $key = (string) ($field['key'] ?? '');
                $type = (string) ($field['type'] ?? '');

                // Section bukan isian yang dijawab; tidak masuk daftar centang.
                if ($key === '' || $type === 'section') {
                    continue;
                }

                if (! in_array($key, $known, true)) {
                    DB::table('form_fields')->insert([
                        'key' => $key,
                        'type' => $type ?: 'text',
                        'label' => (string) ($field['label'] ?? $key),
                        'description' => $field['description'] ?? null,
                        'placeholder' => $field['placeholder'] ?? null,
                        'options' => $field['options'] ?? null,
                        'columns' => $field['columns'] ?? null,
                        'accept' => isset($field['accept']) ? json_encode($field['accept']) : null,
                        'max_mb' => $field['max_mb'] ?? null,
                        'required_default' => (bool) ($field['required'] ?? false),
                        'is_core' => false,
                        'sort_order' => ++$next,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $known[] = $key;
                }

                // Isian inti selalu ada di setiap formulir, jadi tidak masuk daftar centang bawaan.
                if (in_array($key, ['full_name', 'email', 'phone'], true)) {
                    continue;
                }

                $keys[] = $key;

                if (! empty($field['required'])) {
                    $required[] = $key;
                }
            }

            DB::table('job_categories')->where('id', $category->id)->update([
                'default_field_keys' => json_encode($keys),
                'default_required_keys' => json_encode($required),
            ]);
        }
    }
};
