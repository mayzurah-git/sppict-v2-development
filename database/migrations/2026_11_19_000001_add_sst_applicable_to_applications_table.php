<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PP4.5: SST hanya dikira apabila pemohon menanda kotak SST (audit PP0 §8).
 * Lalai `false` untuk permohonan baharu; semua rekod sedia ada telah dikenakan SST,
 * jadi ditanda `true` supaya nilai tersimpan kekal tanpa pengiraan semula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->boolean('sst_applicable')->default(false)->after('subtotal_cost');
        });

        DB::table('applications')->update(['sst_applicable' => true]);
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn('sst_applicable');
        });
    }
};
