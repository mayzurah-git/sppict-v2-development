<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SP2: fasa projek dan aktiviti projek (audit SP0 F.2A, F.3; keputusan L.1–L.3).
 *
 * - Setiap projek mempunyai sekurang-kurangnya satu fasa; projek Tidak Berfasa = satu fasa.
 * - Aktiviti projek ialah snapshot aktiviti templat (nama, pemberat, dokumen) supaya pemberat projek
 *   tidak berubah walaupun templat diubah.
 * - Satu tarikh sasaran bagi setiap aktiviti (I.7); tanda siap binari + tarikh sebenar siap (L.1, diisi dalam SP3).
 * - Nama indeks/FK yang panjang dinamakan secara eksplisit (had MySQL 64 aksara).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_phases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->unsignedTinyInteger('phase_number');
            $table->string('name', 100)->nullable();
            $table->decimal('actual_progress_percent', 5, 2)->default(0);
            $table->decimal('planned_progress_percent', 5, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'phase_number']);
        });

        Schema::create('project_progress_activities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('project_phase_id')->constrained('project_phases')->restrictOnDelete();
            $table->foreignId('progress_template_activity_id')
                ->constrained('progress_template_activities', 'id', 'ppa_template_activity_foreign')
                ->restrictOnDelete();

            // Snapshot daripada templat
            $table->unsignedSmallInteger('sequence');
            $table->string('name');
            $table->decimal('weight', 5, 2);
            $table->boolean('requires_document')->default(false);
            $table->string('document_label')->nullable();

            $table->date('target_date')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->date('actual_completion_date')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_phase_id', 'progress_template_activity_id'], 'ppa_phase_template_activity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_progress_activities');
        Schema::dropIfExists('project_phases');
    }
};
