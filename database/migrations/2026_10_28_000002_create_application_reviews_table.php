<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PP2: sejarah semakan kelengkapan oleh Urus Setia (Lengkap / Tidak Lengkap) dan penghantaran
 * semula oleh pemohon. Setiap tindakan = satu rekod baharu; rekod tidak dikemas kini atau dipadam
 * (lihat App\Models\ApplicationReview). Log audit owen-it kekal sebagai jejak teknikal berasingan.
 *
 * Masa tindakan = created_at (tiada updated_at kerana rekod tidak boleh diubah).
 * Foreign key tanpa cascade: rekod sejarah menghalang pemadaman kekal permohonan/pengguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('application_id')->constrained('applications');
            $table->string('from_status', 30);  // App\Enums\ApplicationStatus
            $table->string('to_status', 30);    // COMPLETE | NEEDS_AMENDMENT (semakan) / SUBMITTED (hantar semula)
            $table->text('remarks')->nullable(); // Wajib bagi Tidak Lengkap
            $table->foreignId('user_id')->constrained('users'); // Penyemak (Urus Setia) atau pemohon yang menghantar semula
            $table->foreignId('jtict_meeting_id')->nullable()->constrained('meetings'); // Mesyuarat JTICT permohonan ketika tindakan
            $table->timestamp('created_at')->useCurrent();

            $table->index(['application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        // Jangan musnahkan sejarah semakan secara senyap.
        if (Schema::hasTable('application_reviews') && DB::table('application_reviews')->exists()) {
            throw new RuntimeException('Jadual application_reviews mengandungi sejarah semakan; tidak boleh dibuang secara automatik.');
        }

        Schema::dropIfExists('application_reviews');
    }
};
