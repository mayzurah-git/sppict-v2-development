<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PP4.1: hubungan permohonan salinan dengan permohonan sumber (Salin Permohonan bagi keputusan
 * JTICT Kaji Semula; fungsi salinan dibangunkan dalam fasa berasingan).
 *
 * Tambahan sahaja: lajur nullable, rekod sedia ada kekal NULL (tiada backfill). Foreign key tanpa
 * cascade / set null supaya hubungan sejarah tidak hilang secara senyap. Tiada indeks unique pada
 * peringkat ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->foreignId('copied_from_application_id')->nullable()->after('jpict_remarks')
                ->constrained('applications');
        });
    }

    public function down(): void
    {
        // Jangan hilangkan hubungan salinan secara senyap.
        if (Schema::hasColumn('applications', 'copied_from_application_id')
            && DB::table('applications')->whereNotNull('copied_from_application_id')->exists()) {
            throw new RuntimeException('Lajur applications.copied_from_application_id mengandungi hubungan salinan; tidak boleh dibuang secara automatik.');
        }

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('copied_from_application_id');
        });
    }
};
