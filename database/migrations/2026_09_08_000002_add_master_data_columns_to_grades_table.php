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
        Schema::table('grades', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('code')->nullable()->unique()->after('name');
            $table->text('description')->nullable()->after('scheme');
            $table->boolean('is_active')->default(false)->index()->after('description');
            $table->softDeletes();
        });

        DB::table('grades')
            ->whereNull('uuid')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $grade): void {
                DB::table('grades')
                    ->where('id', $grade->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });

        Schema::table('grades', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table): void {
            $table->dropUnique('grades_uuid_unique');
            $table->dropUnique('grades_code_unique');
            $table->dropIndex('grades_is_active_index');
            $table->dropColumn(['uuid', 'code', 'description', 'is_active', 'deleted_at']);
        });
    }
};
