<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Position ↔ Grade many-to-many: satu Jawatan boleh mempunyai beberapa Gred yang sah.
        Schema::create('grade_position', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['position_id', 'grade_id']);
        });

        // Pindahkan hubungan 1:1 sedia ada (positions.grade_id) ke pivot. Kolum asal dikekalkan sebagai legacy.
        $now = now();
        DB::table('positions')
            ->whereNotNull('grade_id')
            ->orderBy('id')
            ->get(['id', 'grade_id'])
            ->each(function (object $position) use ($now): void {
                DB::table('grade_position')->insertOrIgnore([
                    'position_id' => $position->id,
                    'grade_id' => $position->grade_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_position');
    }
};
