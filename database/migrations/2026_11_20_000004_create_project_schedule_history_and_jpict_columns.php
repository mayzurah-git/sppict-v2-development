<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SP2: keputusan pemilik I.1 dan I.3 (audit SP0 L.6) serta buka kunci struktur (L.3).
 *
 * - projects.jpict_*: rekod kelulusan JPICT (pilihan) oleh Urus Setia; penanda "Menunggu JPICT" bagi
 *   projek >= RM500,000 yang belum direkod. Tidak menyekat pemantauan.
 * - project_schedule_changes: sejarah pindaan jadual (append-only). Penetapan pertama semasa Mulakan
 *   Pemantauan tidak direkodkan di sini; setiap pindaan selepas itu wajib ada justifikasi.
 * - project_structure_unlocks: sejarah buka kunci struktur fasa/aktiviti oleh Urus Setia (append-only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->date('jpict_approved_at')->nullable()->after('closure_remarks');
            $table->string('jpict_reference', 100)->nullable()->after('jpict_approved_at');
            $table->foreignId('jpict_recorded_by')->nullable()->after('jpict_reference')->constrained('users');
            $table->timestamp('jpict_recorded_at')->nullable()->after('jpict_recorded_by');
        });

        Schema::create('project_schedule_changes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('batch_uuid')->index();   // satu pindaan (satu justifikasi) boleh mengubah beberapa tarikh
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('project_progress_activity_id')->nullable()
                ->constrained('project_progress_activities', 'id', 'psc_activity_foreign')->restrictOnDelete();
            $table->string('field', 30);           // PLANNED_START_DATE | PLANNED_END_DATE | TARGET_DATE
            $table->date('old_value')->nullable();
            $table->date('new_value')->nullable();
            $table->text('justification');
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->index(['project_id', 'created_at']);
        });

        Schema::create('project_structure_unlocks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->timestamp('locked_at')->nullable();    // nilai structure_locked_at sebelum dibuka
            $table->text('remarks');
            $table->foreignId('unlocked_by')->constrained('users');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_structure_unlocks');
        Schema::dropIfExists('project_schedule_changes');

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('jpict_recorded_by');
            $table->dropColumn(['jpict_approved_at', 'jpict_reference', 'jpict_recorded_at']);
        });
    }
};
