<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('position_id')
                ->nullable()
                ->after('position')
                ->constrained('positions')
                ->nullOnDelete();

            $table->index('position_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['position_id']);
            $table->dropColumn('position_id');
        });
    }
};
