<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SP3: laporan kemajuan bulanan (audit SP0 F.4; keputusan I.10, L.7).
 *
 * - Satu laporan bagi setiap projek setiap bulan (indeks unik).
 * - Status DRAFT / SUBMITTED; laporan dihantar dikunci (kawalan model).
 * - Tarikh akhir disalin daripada meetings.progress_update_deadline semasa laporan dicipta; is_late
 *   ditetapkan semasa dihantar.
 * - Peratus sebenar / dirancang / varians ialah snapshot pada tarikh penilaian, diisi oleh pengiraan SP4.
 * - Kolum lama physical_* / financial_* dijadikan nullable (tidak digunakan; I.4 kemajuan kewangan tertangguh).
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('project_progress_reports')
            ->select('project_id', 'report_year', 'report_month')
            ->groupBy('project_id', 'report_year', 'report_month')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates > 0) {
            throw new RuntimeException("Terdapat {$duplicates} laporan kemajuan berganda bagi bulan yang sama. Selesaikan pendua sebelum migration ini dijalankan.");
        }

        Schema::table('project_progress_reports', function (Blueprint $table): void {
            $table->decimal('physical_actual', 5, 2)->nullable()->change();
            $table->decimal('physical_planned', 5, 2)->nullable()->change();
            $table->decimal('financial_actual', 5, 2)->nullable()->change();
            $table->decimal('financial_planned', 5, 2)->nullable()->change();
        });

        Schema::table('project_progress_reports', function (Blueprint $table): void {
            $table->string('status', 20)->default('DRAFT')->after('report_month');
            $table->date('evaluation_date')->nullable()->after('status');
            $table->date('due_date')->nullable()->after('evaluation_date');
            $table->foreignId('due_meeting_id')->nullable()->after('due_date')->constrained('meetings');
            $table->boolean('is_late')->default(false)->after('due_meeting_id');
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->decimal('actual_progress_percent', 5, 2)->nullable()->after('financial_planned');
            $table->decimal('planned_progress_percent', 5, 2)->nullable()->after('actual_progress_percent');
            $table->decimal('variance_percent', 6, 2)->nullable()->after('planned_progress_percent');
            $table->foreignId('created_by')->nullable()->after('verified_at')->constrained('users');

            $table->unique(['project_id', 'report_year', 'report_month'], 'ppr_project_period_unique');
            $table->index('status');
        });
    }

    public function down(): void
    {
        // MySQL membuang indeks asal FK project_id apabila indeks unik baharu boleh menggantikannya.
        Schema::table('project_progress_reports', function (Blueprint $table): void {
            if (! Schema::hasIndex('project_progress_reports', 'project_progress_reports_project_id_foreign')) {
                $table->index('project_id', 'project_progress_reports_project_id_foreign');
            }
        });

        Schema::table('project_progress_reports', function (Blueprint $table): void {
            $table->dropUnique('ppr_project_period_unique');
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('due_meeting_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'status', 'evaluation_date', 'due_date', 'is_late', 'submitted_at',
                'actual_progress_percent', 'planned_progress_percent', 'variance_percent',
            ]);
        });

        // Laporan SP3 tidak mengisi kolum lama; nilai 0 diisi supaya kolum boleh dijadikan wajib semula.
        foreach (['physical_actual', 'physical_planned', 'financial_actual', 'financial_planned'] as $column) {
            DB::table('project_progress_reports')->whereNull($column)->update([$column => 0]);
        }

        Schema::table('project_progress_reports', function (Blueprint $table): void {
            $table->decimal('physical_actual', 5, 2)->nullable(false)->change();
            $table->decimal('physical_planned', 5, 2)->nullable(false)->change();
            $table->decimal('financial_actual', 5, 2)->nullable(false)->change();
            $table->decimal('financial_planned', 5, 2)->nullable(false)->change();
        });
    }
};
