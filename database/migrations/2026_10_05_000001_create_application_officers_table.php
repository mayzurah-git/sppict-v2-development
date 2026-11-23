<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Sejarah tugasan Pegawai Bertanggungjawab (Fasa 5).
     * Setiap baris ialah satu tempoh tugasan; ended_at = null bermaksud pegawai aktif.
     * Maklumat pegawai disimpan sebagai snapshot supaya sejarah kekal walaupun profil pengguna berubah.
     */
    public function up(): void
    {
        Schema::create('application_officers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 20); // PRIMARY / SECONDARY

            // Snapshot maklumat pegawai pada masa tugasan
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->nullOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('grade')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Tempoh tugasan (started_at null semasa Draf; ditetapkan apabila permohonan dihantar)
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->string('end_reason', 30)->nullable();
            $table->text('handover_remarks')->nullable();

            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['application_id', 'role', 'ended_at']);
            $table->index(['user_id', 'ended_at']);
        });

        $this->backfillFromApplicationColumns();
    }

    /**
     * Pindahkan data pegawai sedia ada (kolum primary_/secondary_officer_* pada applications).
     * Kolum lama dikekalkan buat sementara dan akan dibuang dalam migration berasingan.
     */
    private function backfillFromApplicationColumns(): void
    {
        $editableStatuses = ['DRAFT', 'NEEDS_AMENDMENT'];

        DB::table('applications')->orderBy('id')->each(function ($application) use ($editableStatuses) {
            foreach (['PRIMARY' => 'primary', 'SECONDARY' => 'secondary'] as $role => $prefix) {
                $name = $application->{"{$prefix}_officer_name"};

                if (blank($name)) {
                    continue;
                }

                $email = $application->{"{$prefix}_officer_email"};
                $userId = $email ? DB::table('users')->where('email', $email)->value('id') : null;

                DB::table('application_officers')->insert([
                    'uuid' => (string) Str::uuid(),
                    'application_id' => $application->id,
                    'user_id' => $userId,
                    'role' => $role,
                    'agency_id' => $application->agency_id,
                    'name' => $name,
                    'position' => $application->{"{$prefix}_officer_position"},
                    'email' => $email,
                    'phone' => $application->{"{$prefix}_officer_phone"},
                    'started_at' => in_array($application->status, $editableStatuses, true)
                        ? null
                        : substr((string) $application->created_at, 0, 10),
                    'assigned_by' => $application->applicant_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_officers');
    }
};
