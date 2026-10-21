<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UM11: maklumat status akaun Tidak Aktif. Tambahan sahaja (tiada data diubah).
 *
 * - `inactive_reason`   : retired / transferred_out / inactive_over_6_months (App\Enums\InactiveReason)
 * - `status_changed_at` : masa status akaun terakhir ditukar oleh pentadbir
 * - `status_changed_by` : pentadbir (Urus Setia / Superadmin) yang menukar status
 *
 * `users.status` sedia ada (varchar 20) menampung nilai baharu `inactive` tanpa perubahan skema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('inactive_reason', 30)->nullable()->after('status');
            $table->timestamp('status_changed_at')->nullable()->after('inactive_reason');
            $table->foreignId('status_changed_by')->nullable()->after('status_changed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['status_changed_by']);
            $table->dropColumn(['inactive_reason', 'status_changed_at', 'status_changed_by']);
        });
    }
};
