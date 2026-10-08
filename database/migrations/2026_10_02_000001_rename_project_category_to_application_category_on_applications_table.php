<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tukar "Kategori Projek" (Fasa 1) kepada "Kategori Permohonan".
     * Kategori Projek kini hanya berada di Fasa 3 (application_details.item_category).
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->renameColumn('project_category', 'application_category');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('application_category')->nullable()->change();
        });

        // Nilai lama tidak sepadan dengan senarai Kategori Permohonan baharu;
        // tetapkan null supaya pemohon memilih semula.
        DB::table('applications')->update(['application_category' => null]);
    }

    public function down(): void
    {
        DB::table('applications')
            ->whereNull('application_category')
            ->update(['application_category' => '']);

        Schema::table('applications', function (Blueprint $table) {
            $table->string('application_category')->nullable(false)->change();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->renameColumn('application_category', 'project_category');
        });
    }
};
