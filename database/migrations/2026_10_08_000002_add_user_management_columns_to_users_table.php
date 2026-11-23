<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // No. KP disimpan sebagai string supaya sifar di hadapan tidak hilang.
            $table->string('no_kad_pengenalan', 12)->nullable()->unique()->after('uuid');

            $table->foreignId('grade_id')
                ->nullable()
                ->after('position_id')
                ->constrained('grades')
                ->nullOnDelete();

            // Telefon disimpan sebagai string (digit sahaja). `phone_number` dikekalkan sebagai legacy.
            $table->string('phone_office', 20)->nullable()->after('phone_number');
            $table->string('phone_mobile', 20)->nullable()->after('phone_office');

            // Status akaun: Self Register bermula `pending` sehingga diluluskan Urus Setia.
            $table->string('status', 20)->default('active')->index()->after('phone_mobile');
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            // Kata laluan sementara (didaftar oleh admin) wajib ditukar semasa log masuk pertama.
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        // Gred pengguna sedia ada diambil daripada Gred legacy jawatan mereka (jika ada).
        DB::table('users')
            ->whereNotNull('position_id')
            ->whereNull('grade_id')
            ->orderBy('id')
            ->get(['id', 'position_id'])
            ->each(function (object $user): void {
                $gradeId = DB::table('positions')->where('id', $user->position_id)->value('grade_id');

                if ($gradeId) {
                    DB::table('users')->where('id', $user->id)->update(['grade_id' => $gradeId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['grade_id']);
            $table->dropForeign(['approved_by']);
            $table->dropUnique(['no_kad_pengenalan']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'no_kad_pengenalan',
                'grade_id',
                'phone_office',
                'phone_mobile',
                'status',
                'approved_by',
                'approved_at',
                'must_change_password',
            ]);
        });
    }
};
