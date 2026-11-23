<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mesyuarat Pra-JTICT tidak lagi sebahagian daripada proses.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pra_jtict_meeting_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('pra_jtict_meeting_id')->nullable()->after('review_remarks')->constrained('meetings');
        });
    }
};
