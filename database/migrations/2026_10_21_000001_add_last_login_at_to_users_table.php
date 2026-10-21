<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UM11A: rekod log masuk terakhir untuk penyahaktifan automatik "Tidak Aktif Melebihi 6 Bulan".
 * Tambahan sahaja; rekod sedia ada kekal NULL (tempoh dikira dari tarikh ciri dilancar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_login_at')->nullable()->index()->after('status_changed_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['last_login_at']);
            $table->dropColumn('last_login_at');
        });
    }
};
