<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Modul Pengurusan Mesyuarat: hubungkan mesyuarat kepada data selenggara (Nama/Bil./Tahun)
 * dan tambah medan borang Daftar Mesyuarat. Kolum lama (type, series_number, status) dikekalkan
 * buat sementara: type & series_number disegerakkan oleh model; status tidak lagi digunakan.
 */
return new class extends Migration
{
    private const LEGACY_NAMES = [
        'JTICTNS' => ['name' => 'Jawatankuasa Teknikal ICT Negeri Sembilan', 'accepts_applications' => true, 'sort_order' => 1],
        'JPICTNS' => ['name' => 'Jawatankuasa Pemandu ICT Negeri Sembilan', 'accepts_applications' => false, 'sort_order' => 2],
    ];

    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->foreignId('meeting_name_id')->nullable()->after('uuid')->constrained('meeting_names');
            $table->foreignId('meeting_number_id')->nullable()->after('meeting_name_id')->constrained('meeting_numbers');
            $table->foreignId('meeting_year_id')->nullable()->after('meeting_number_id')->constrained('meeting_years');
            $table->boolean('is_active')->default(false)->after('status')->index();
            $table->date('progress_update_deadline')->nullable()->after('submission_closes_at');
            $table->longText('agenda')->nullable()->after('progress_update_deadline');
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users');
        });

        $this->backfill();

        // Nama Mesyuarat kini datang daripada jadual selenggara (bukan enum tetap).
        Schema::table('meetings', function (Blueprint $table): void {
            $table->string('type', 50)->nullable()->change();
            $table->string('series_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        $foreignKeys = ['meeting_name_id', 'meeting_number_id', 'meeting_year_id', 'updated_by'];

        Schema::table('meetings', function (Blueprint $table) use ($foreignKeys): void {
            foreach ($foreignKeys as $column) {
                $table->dropForeign([$column]);
            }
        });

        Schema::table('meetings', function (Blueprint $table) use ($foreignKeys): void {
            $table->dropIndex(['is_active']);
            $table->dropColumn([...$foreignKeys, 'is_active', 'progress_update_deadline', 'agenda']);
        });
    }

    private function backfill(): void
    {
        $now = now();

        DB::table('meetings')->orderBy('id')->each(function (object $meeting) use ($now): void {
            $nameId = $this->masterId('meeting_names', ['code' => $meeting->type], [
                'name' => self::LEGACY_NAMES[$meeting->type]['name'] ?? $meeting->type,
                'accepts_applications' => self::LEGACY_NAMES[$meeting->type]['accepts_applications'] ?? false,
                'sort_order' => self::LEGACY_NAMES[$meeting->type]['sort_order'] ?? 0,
            ], $now);

            // "Bil. 3/2026" → 3
            $numberId = preg_match('/(\d+)/', (string) $meeting->series_number, $match)
                ? $this->masterId('meeting_numbers', ['number' => (int) $match[1]], [], $now)
                : null;

            $yearId = $meeting->year
                ? $this->masterId('meeting_years', ['year' => (int) $meeting->year], [], $now)
                : null;

            DB::table('meetings')->where('id', $meeting->id)->update([
                'meeting_name_id' => $nameId,
                'meeting_number_id' => $numberId,
                'meeting_year_id' => $yearId,
                'is_active' => $meeting->status === 'SCHEDULED',
            ]);
        });
    }

    private function masterId(string $table, array $key, array $values, $now): int
    {
        $existing = DB::table($table)->where($key)->value('id');

        if ($existing) {
            return $existing;
        }

        return DB::table($table)->insertGetId($key + $values + [
            'uuid' => (string) Str::uuid(),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
