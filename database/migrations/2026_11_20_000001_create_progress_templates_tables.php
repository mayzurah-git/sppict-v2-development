<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SP1: Templat pemberat kemajuan projek (audit SP0 F.1 & F.2).
 *
 * Satu templat bagi setiap Jenis Perolehan (pemetaan melalui procurement_types, bukan nama). Templat berversi:
 * templat yang telah digunakan oleh projek tidak diubah; perubahan = versi baharu. Data templat dimuatkan oleh
 * Database\Seeders\ProgressTemplateSeeder (idempoten).
 *
 * Keputusan pemilik 20/11/2026: setiap aktiviti mempunyai satu tarikh sasaran (tiada jenis jadual) dan hanya
 * Templat D (Pembangunan Sistem / Aplikasi) membenarkan projek berfasa (allows_phases).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 20);                 // A | B | C | D
            $table->unsignedInteger('version')->default(1);
            $table->string('name');
            $table->foreignId('procurement_type_id')->constrained('procurement_types')->restrictOnDelete();
            $table->boolean('allows_phases')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['code', 'version']);
            $table->index(['procurement_type_id', 'is_active']);
        });

        Schema::create('progress_template_activities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('progress_template_id')->constrained('progress_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('name');
            $table->decimal('weight', 5, 2);            // Jumlah bagi satu templat = 100.00
            $table->boolean('requires_document')->default(false);
            $table->string('document_label')->nullable();
            $table->timestamps();

            // Nama eksplisit: nama lalai melebihi had 64 aksara MySQL
            $table->unique(['progress_template_id', 'sequence'], 'pt_activities_template_sequence_unique');
        });
    }

    public function down(): void
    {
        // Jangan buang templat yang telah dipautkan kepada projek.
        if (Schema::hasColumn('projects', 'progress_template_id')
            && DB::table('projects')->whereNotNull('progress_template_id')->exists()) {
            throw new RuntimeException('Templat kemajuan telah digunakan oleh projek; tidak boleh dibuang secara automatik.');
        }

        Schema::dropIfExists('progress_template_activities');
        Schema::dropIfExists('progress_templates');
    }
};
