<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SP3: sejarah tanda siap aktiviti, snapshot aktiviti dalam laporan bulanan dan dokumen sokongan kemajuan
 * (audit SP0 F.5, F.6, F.7; keputusan L.1, I.8).
 *
 * Nama indeks/FK yang panjang dinamakan secara eksplisit (had MySQL 64 aksara).
 */
return new class extends Migration
{
    public function up(): void
    {
        // F.6: sejarah tanda siap / batal tanda (append-only; tiada updated_at, tiada soft delete)
        Schema::create('project_progress_updates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('project_progress_activity_id')
                ->constrained('project_progress_activities', 'id', 'ppu_activity_foreign')->restrictOnDelete();
            $table->string('change_type', 20);              // COMPLETED | UNCOMPLETED
            $table->date('completion_date')->nullable();   // tarikh siap ditetapkan / tarikh siap yang dibatalkan
            $table->text('remarks')->nullable();           // wajib bagi UNCOMPLETED
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->index(['project_progress_activity_id', 'created_at'], 'ppu_activity_created_index');
        });

        // F.5: snapshot aktiviti pada tarikh penilaian laporan
        Schema::create('project_progress_report_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_progress_report_id')
                ->constrained('project_progress_reports', 'id', 'ppra_report_foreign')->cascadeOnDelete();
            $table->foreignId('project_progress_activity_id')
                ->constrained('project_progress_activities', 'id', 'ppra_activity_foreign')->restrictOnDelete();
            $table->unsignedTinyInteger('phase_number');
            $table->unsignedSmallInteger('sequence');
            $table->string('name');
            $table->decimal('weight', 5, 2);
            $table->date('target_date')->nullable();
            $table->boolean('is_planned_due')->default(false);   // tarikh sasaran <= tarikh penilaian
            $table->boolean('is_completed')->default(false);     // siap pada / sebelum tarikh penilaian
            $table->date('actual_completion_date')->nullable();
            $table->timestamps();

            $table->unique(['project_progress_report_id', 'project_progress_activity_id'], 'ppra_report_activity_unique');
        });

        // F.7: dokumen sokongan kemajuan (storan private)
        Schema::create('project_progress_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('project_progress_activity_id')->nullable()
                ->constrained('project_progress_activities', 'id', 'ppat_activity_foreign')->restrictOnDelete();
            $table->foreignId('project_progress_report_id')->nullable()
                ->constrained('project_progress_reports', 'id', 'ppat_report_foreign')->restrictOnDelete();
            $table->string('document_label');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size_kb');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_progress_attachments');
        Schema::dropIfExists('project_progress_report_activities');
        Schema::dropIfExists('project_progress_updates');
    }
};
