<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->foreignId('service_scheme_id')
                ->nullable()
                ->after('title')
                ->constrained('service_schemes')
                ->restrictOnDelete();
            $table->foreignId('grade_id')
                ->nullable()
                ->after('service_scheme_id')
                ->constrained('grades')
                ->restrictOnDelete();
            $table->text('description')->nullable()->after('grade_id');
            $table->boolean('is_active')->default(false)->index()->after('description');
            $table->softDeletes();
            $table->index(['service_scheme_id', 'grade_id']);
        });

        DB::table('positions')
            ->whereNull('uuid')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $position): void {
                DB::table('positions')
                    ->where('id', $position->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });

        Schema::table('positions', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table): void {
            $table->dropForeign(['service_scheme_id']);
            $table->dropForeign(['grade_id']);
            $table->dropUnique('positions_uuid_unique');
            $table->dropIndex('positions_is_active_index');
            $table->dropIndex('positions_service_scheme_id_grade_id_index');
            $table->dropColumn([
                'uuid',
                'service_scheme_id',
                'grade_id',
                'description',
                'is_active',
                'deleted_at',
            ]);
        });
    }
};
