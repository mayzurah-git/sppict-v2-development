<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori Projek kini diselenggara melalui jadual project_categories, jadi senarai
 * ENUM tetap pada item_category tidak lagi sesuai. Tukar kepada VARCHAR: nilai sedia ada
 * dikekalkan tanpa perubahan (lajur ini menjadi snapshot teks bersama project_category_id).
 */
return new class extends Migration
{
    private const LEGACY_VALUES = [
        'Projek Baharu',
        'Peningkatan Sistem',
        'Peluasan Sistem/Projek',
        'Penambahbaikan Peralatan',
        'Penyelenggaraan',
        'Khidmat Perunding ICT',
    ];

    public function up(): void
    {
        Schema::table('application_details', function (Blueprint $table): void {
            $table->string('item_category')->change();
        });
    }

    public function down(): void
    {
        // Elak kehilangan data: jangan kembali kepada ENUM jika ada nilai di luar senarai lama
        // (contoh selepas pemetaan label baharu pada Fasa U4).
        $unmatched = DB::table('application_details')
            ->whereNotIn('item_category', self::LEGACY_VALUES)
            ->distinct()
            ->pluck('item_category');

        if ($unmatched->isNotEmpty()) {
            throw new RuntimeException(
                'Tidak boleh menukar item_category kembali kepada ENUM; nilai di luar senarai lama: ' .
                $unmatched->implode(', ')
            );
        }

        Schema::table('application_details', function (Blueprint $table): void {
            $table->enum('item_category', self::LEGACY_VALUES)->change();
        });
    }
};
