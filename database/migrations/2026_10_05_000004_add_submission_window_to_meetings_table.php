<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tempoh penerimaan permohonan bagi Mesyuarat JTICT.
     * Permohonan hanya boleh dihantar apabila tarikh semasa berada dalam tempoh ini
     * (kedua-dua tarikh inklusif). Lihat Meeting::scopeOpenForSubmission().
     */
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->date('submission_opens_at')->nullable()->after('venue');
            $table->date('submission_closes_at')->nullable()->after('submission_opens_at');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['submission_opens_at', 'submission_closes_at']);
        });
    }
};
