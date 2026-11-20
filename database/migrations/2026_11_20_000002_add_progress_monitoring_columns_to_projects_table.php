<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SP1: jadual sedia ada `projects` diguna semula sebagai rekod pemantauan projek (keputusan I.2, audit SP0 F.0).
 *
 * - application_id unik (satu permohonan = satu projek) dan nullable (projek SPPICT v1 / Bank Projek tiada
 *   permohonan v2; banyak NULL dibenarkan dalam indeks unik).
 * - project_manager_id telah dibuang oleh 2026_10_05_000003; pegawai bertanggungjawab diambil daripada
 *   application_officers.
 * - Kolum lama (status_indicator, physical/financial_progress_percent, is_completed) dikekalkan untuk
 *   keserasian papan pemuka sedia ada sehingga SP5.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pra-syarat (UAT/produksi): indeks unik tidak boleh dicipta jika ada projek berganda.
        $duplicates = DB::table('projects')
            ->whereNotNull('application_id')
            ->select('application_id')
            ->groupBy('application_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates > 0) {
            throw new RuntimeException("Terdapat {$duplicates} permohonan dengan lebih daripada satu rekod projek. Selesaikan pendua sebelum migration ini dijalankan.");
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('application_id')->nullable()->change();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->unique('application_id');

            $table->foreignId('progress_template_id')->nullable()->after('agency_id')
                ->constrained('progress_templates')->restrictOnDelete();
            $table->boolean('is_phased')->default(false)->after('progress_template_id');
            $table->string('source', 10)->default('V2')->after('is_phased');          // V2 | V1
            $table->string('legacy_reference', 100)->nullable()->unique()->after('source');
            $table->string('status', 30)->default('NOT_STARTED')->after('legacy_reference');
            $table->timestamp('structure_locked_at')->nullable()->after('status');

            $table->date('planned_start_date')->nullable()->after('end_date');
            $table->date('planned_end_date')->nullable()->after('planned_start_date');

            $table->decimal('actual_progress_percent', 5, 2)->default(0)->after('is_completed');
            $table->decimal('planned_progress_percent', 5, 2)->nullable()->after('actual_progress_percent');
            $table->decimal('variance_percent', 6, 2)->nullable()->after('planned_progress_percent');
            $table->date('progress_evaluated_at')->nullable()->after('variance_percent');

            $table->foreignId('started_by')->nullable()->after('progress_evaluated_at')->constrained('users');
            $table->timestamp('started_at')->nullable()->after('started_by');
            $table->foreignId('closed_by')->nullable()->after('started_at')->constrained('users');
            $table->timestamp('closed_at')->nullable()->after('closed_by');
            $table->text('closure_remarks')->nullable()->after('closed_at');

            $table->index(['agency_id', 'status']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        if (DB::table('projects')->whereNull('application_id')->exists()) {
            throw new RuntimeException('Terdapat projek tanpa permohonan (SPPICT v1); application_id tidak boleh dijadikan wajib semula secara automatik.');
        }

        // MySQL membuang indeks asal foreign key agency_id / application_id apabila indeks baharu boleh
        // menggantikannya; cipta semula sebelum indeks baharu dibuang.
        Schema::table('projects', function (Blueprint $table): void {
            if (! Schema::hasIndex('projects', 'projects_agency_id_foreign')) {
                $table->index('agency_id', 'projects_agency_id_foreign');
            }
            if (! Schema::hasIndex('projects', 'projects_application_id_foreign')) {
                $table->index('application_id', 'projects_application_id_foreign');
            }
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['agency_id', 'status']);
            $table->dropIndex(['source']);
            $table->dropUnique(['application_id']);
            $table->dropUnique(['legacy_reference']);
            $table->dropConstrainedForeignId('progress_template_id');
            $table->dropConstrainedForeignId('started_by');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn([
                'is_phased', 'source', 'legacy_reference', 'status', 'structure_locked_at',
                'planned_start_date', 'planned_end_date',
                'actual_progress_percent', 'planned_progress_percent', 'variance_percent', 'progress_evaluated_at',
                'started_at', 'closed_at', 'closure_remarks',
            ]);
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('application_id')->nullable(false)->change();
        });
    }
};
