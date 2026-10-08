<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rekod pegawai yang menghantar setiap laporan kemajuan supaya kekal
     * walaupun pegawai bertanggungjawab kemudiannya bertukar.
     */
    public function up(): void
    {
        Schema::table('project_progress_reports', function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->after('report_month')->constrained('users')->nullOnDelete();
            $table->foreignId('application_officer_id')->nullable()->after('submitted_by')->constrained('application_officers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_progress_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('application_officer_id');
            $table->dropConstrainedForeignId('submitted_by');
        });
    }
};
