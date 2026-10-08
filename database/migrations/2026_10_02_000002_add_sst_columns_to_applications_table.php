<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->decimal('subtotal_cost', 15, 2)->nullable()->after('estimated_cost'); // Jumlah Kecil perincian (sebelum SST)
            $table->decimal('sst_rate', 5, 2)->nullable()->after('subtotal_cost');        // Kadar SST (%) semasa permohonan dibuat
            $table->decimal('sst_amount', 15, 2)->nullable()->after('sst_rate');          // Amaun SST
            $table->decimal('total_cost', 15, 2)->nullable()->after('sst_amount');        // Jumlah Keseluruhan (termasuk SST)
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['subtotal_cost', 'sst_rate', 'sst_amount', 'total_cost']);
        });
    }
};
