<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Pengurusan Mesyuarat (Fasa 4): buang kolum lama yang telah digantikan oleh data selenggara.
 *   type          → meeting_name_id
 *   series_number → meeting_number_id + meeting_year_id
 *   year          → meeting_year_id
 *   status        → is_active
 */
return new class extends Migration
{
    private const MASTER_COLUMNS = ['meeting_name_id', 'meeting_number_id', 'meeting_year_id'];

    public function up(): void
    {
        $unmapped = DB::table('meetings')
            ->where(function ($query): void {
                foreach (self::MASTER_COLUMNS as $column) {
                    $query->orWhereNull($column);
                }
            })
            ->pluck('id');

        if ($unmapped->isNotEmpty()) {
            throw new RuntimeException(
                'Mesyuarat berikut belum mempunyai Nama/Bil./Tahun Mesyuarat (ID: ' . $unmapped->join(', ') . '). '
                . 'Lengkapkan melalui skrin Kemaskini Mesyuarat sebelum menjalankan migrasi ini.'
            );
        }

        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropColumn(['type', 'series_number', 'year', 'status']);
        });

        Schema::table('meetings', function (Blueprint $table): void {
            foreach (self::MASTER_COLUMNS as $column) {
                $table->unsignedBigInteger($column)->nullable(false)->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            foreach (self::MASTER_COLUMNS as $column) {
                $table->unsignedBigInteger($column)->nullable()->change();
            }
        });

        Schema::table('meetings', function (Blueprint $table): void {
            $table->string('type', 50)->nullable()->after('meeting_year_id');
            $table->string('series_number')->nullable()->after('type');
            $table->integer('year')->nullable()->after('series_number');
            $table->enum('status', ['DRAFT', 'SCHEDULED', 'ONGOING', 'COMPLETED', 'CANCELLED'])->default('DRAFT')->after('venue');
        });

        DB::table('meetings')
            ->join('meeting_names', 'meeting_names.id', '=', 'meetings.meeting_name_id')
            ->join('meeting_numbers', 'meeting_numbers.id', '=', 'meetings.meeting_number_id')
            ->join('meeting_years', 'meeting_years.id', '=', 'meetings.meeting_year_id')
            ->select('meetings.id', 'meetings.is_active', 'meeting_names.code', 'meeting_numbers.number', 'meeting_years.year')
            ->orderBy('meetings.id')
            ->get()
            ->each(fn (object $meeting) => DB::table('meetings')->where('id', $meeting->id)->update([
                'type' => $meeting->code,
                'series_number' => "Bil. {$meeting->number}/{$meeting->year}",
                'year' => $meeting->year,
                'status' => $meeting->is_active ? 'SCHEDULED' : 'DRAFT',
            ]));
    }
};
