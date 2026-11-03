<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PP4.1: sejarah keputusan Pra JTICT dan JTICT (sumber rasmi keputusan; bukan application_reviews
 * dan bukan lajur warisan applications.jtict_decision). Setiap keputusan = satu rekod baharu;
 * rekod tidak dikemas kini atau dipadam (lihat App\Models\ApplicationDecision).
 *
 * stage / decision disimpan sebagai VARCHAR (enum PHP App\Enums\DecisionStage dan
 * App\Enums\ApplicationDecision), selaras dengan applications.status; pasangan yang sah
 * disahkan dalam model dan service.
 *
 * Masa rekod = created_at (tiada updated_at). Foreign key tanpa cascade: sejarah keputusan
 * menghalang pemadaman kekal permohonan, mesyuarat atau pengguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('application_id')->constrained('applications');
            $table->string('stage', 20);         // PRE_JTICT | JTICT
            $table->string('decision', 30);      // ACCEPTED | AMENDMENT_REQUIRED | REJECTED | APPROVED | NOT_APPROVED | REVIEW
            $table->string('from_status', 30);   // App\Enums\ApplicationStatus
            $table->string('to_status', 30);
            $table->text('remarks')->nullable(); // Wajib mengikut ApplicationDecision::requiresRemarks()
            $table->date('decision_date');       // Tarikh keputusan (berasingan daripada created_at)
            $table->foreignId('meeting_id')->nullable()->constrained('meetings'); // Mesyuarat JTICT permohonan ketika keputusan
            $table->foreignId('decided_by')->constrained('users'); // Urus Setia yang merekod
            $table->timestamp('created_at')->useCurrent();

            $table->index(['application_id', 'stage', 'created_at']);
            $table->index(['stage', 'decision']);
        });
    }

    public function down(): void
    {
        // Jangan musnahkan sejarah keputusan secara senyap.
        if (Schema::hasTable('application_decisions') && DB::table('application_decisions')->exists()) {
            throw new RuntimeException('Jadual application_decisions mengandungi sejarah keputusan; tidak boleh dibuang secara automatik.');
        }

        Schema::dropIfExists('application_decisions');
    }
};
