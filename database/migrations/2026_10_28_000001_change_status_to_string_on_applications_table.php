<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PP1: status proses permohonan kini ditakrif oleh App\Enums\ApplicationStatus (satu sumber
 * rujukan), jadi senarai ENUM tetap pada applications.status tidak lagi sesuai (ia tidak
 * mengandungi COMPLETE / IN_JTICT / CLOSED). Tukar kepada VARCHAR: semua nilai sedia ada
 * dikekalkan tanpa pemetaan. Indeks ditambah kerana senarai & papan pemuka menapis ikut status.
 *
 * Keputusan Pra JTICT / JTICT TIDAK disimpan dalam lajur ini.
 */
return new class extends Migration
{
    private const LEGACY_VALUES = [
        'DRAFT',
        'SUBMITTED',
        'UNDER_REVIEW',
        'NEEDS_AMENDMENT',
        'APPROVED_URUS_SETIA',
        'REJECTED_URUS_SETIA',
    ];

    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->string('status', 30)->default('DRAFT')->change();
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->index('status');
        });
    }

    public function down(): void
    {
        // Elak kehilangan data: jangan kembali kepada ENUM jika ada nilai di luar senarai lama.
        $unmatched = DB::table('applications')
            ->whereNotIn('status', self::LEGACY_VALUES)
            ->distinct()
            ->pluck('status');

        if ($unmatched->isNotEmpty()) {
            throw new RuntimeException(
                'Tidak boleh menukar applications.status kembali kepada ENUM; nilai di luar senarai lama: ' .
                $unmatched->implode(', ')
            );
        }

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropIndex(['status']);
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->enum('status', self::LEGACY_VALUES)->default('DRAFT')->change();
        });
    }
};
