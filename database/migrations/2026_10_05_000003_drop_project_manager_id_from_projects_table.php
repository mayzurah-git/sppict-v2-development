<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pegawai bertanggungjawab projek kini diambil daripada application_officers
     * (satu sumber maklumat). Lihat Project::responsibleOfficer().
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_manager_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_manager_id')->nullable()->after('agency_id')->constrained('users');
        });
    }
};
