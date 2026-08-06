<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Jadual Agensi / Jabatan
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique(); // Contoh: BTM, SUK, JKR
            $table->string('name'); // Nama Penuh Agensi / Jabatan
            $table->string('category')->default('Agensi Negeri'); // Agensi Negeri / PBT / Badan Berkanun
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Kemaskini Jadual Users (Agensi FK)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'uuid')) {
                $table->uuid('uuid')->after('id')->unique();
            }
            if (!Schema::hasColumn('users', 'agency_id')) {
                $table->foreignId('agency_id')->nullable()->after('uuid')->constrained('agencies')->onDelete('cascade');
            }
            if (!Schema::hasColumn('users', 'position')) {
                $table->string('position')->nullable()->after('email'); // Jawatan Pengguna
            }
            if (!Schema::hasColumn('users', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('position');
            }
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // 3. Jadual Mesyuarat (JTICTNS, JPICTNS)
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('type', ['JTICTNS', 'JPICTNS']);
            $table->string('series_number'); // Contoh: Bil. 1/2026
            $table->integer('year');
            $table->date('meeting_date');
            $table->time('meeting_time')->nullable();
            $table->string('venue')->nullable();
            $table->enum('status', ['DRAFT', 'SCHEDULED', 'ONGOING', 'COMPLETED', 'CANCELLED'])->default('DRAFT');
            $table->string('minutes_file_path')->nullable(); // Fail Minit Mesyuarat (PDF)
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Jadual Permohonan Projek (Applications)
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference_number')->unique(); // No. Rujukan Permohonan: SPPICT/2026/001
            $table->foreignId('agency_id')->constrained('agencies');
            $table->foreignId('applicant_id')->constrained('users');
            
            // Fasa 1: Maklumat Projek
            $table->string('title');
            $table->string('project_category'); // Hardware, Software, Network, System Development, Maintenance
            $table->text('objectives')->nullable();    // Objektif Projek
            $table->text('project_scope')->nullable(); // Skop Projek

            // Fasa 2: Perolehan & Kos
            $table->string('procurement_type')->nullable(); // Pembekalan / Perkhidmatan / Pembangunan Sistem
            $table->string('procurement_method')->nullable(); // Tender / Sebut Harga / Direct Purchase
            $table->decimal('ceiling_cost', 15, 2)->nullable(); // Kos Siling Projek (RM)
            $table->decimal('estimated_cost', 15, 2); // Anggaran Kos Projek (RM)
            $table->integer('expected_duration_months')->nullable();
            $table->string('outcome_code')->nullable(); // Kod Hasil / Vot Peruntukan

            // Fasa 3: Perincian & Justifikasi
            //$table->text('justification')->nullable();
            //$table->text('technical_impact')->nullable();

            // Status Permohonan & Semakan
            $table->enum('status', [
                'DRAFT', 
                'SUBMITTED', 
                'UNDER_REVIEW', 
                'NEEDS_AMENDMENT', 
                'APPROVED_URUS_SETIA', 
                'REJECTED_URUS_SETIA'
            ])->default('DRAFT');
            $table->text('review_remarks')->nullable();

            // Rujukan Mesyuarat
            $table->foreignId('pra_jtict_meeting_id')->nullable()->constrained('meetings');
            $table->foreignId('jtict_meeting_id')->nullable()->constrained('meetings');
            $table->foreignId('jpict_meeting_id')->nullable()->constrained('meetings');

            // Keputusan Mesyuarat Utama
            $table->enum('jtict_decision', ['PENDING', 'APPROVED', 'APPROVED_CONDITIONAL', 'REVIEW', 'REJECTED'])->default('PENDING');
            $table->text('jtict_remarks')->nullable();
            $table->enum('jpict_decision', ['NOT_REQUIRED', 'PENDING', 'APPROVED', 'APPROVED_CONDITIONAL', 'REVIEW', 'REJECTED'])->default('NOT_REQUIRED');
            $table->text('jpict_remarks')->nullable();

            // TAMBAH MEDAN PEGAWAI UTAMA & PENGGANTI DI SINI:
            $table->string('primary_officer_name')->nullable();
            $table->string('primary_officer_position')->nullable();
            $table->string('primary_officer_email')->nullable();
            $table->string('primary_officer_phone')->nullable();

            $table->string('secondary_officer_name')->nullable();     // Pegawai Pengganti (Pilihan)
            $table->string('secondary_officer_position')->nullable();
            $table->string('secondary_officer_email')->nullable();
            $table->string('secondary_officer_phone')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // 5. Jadual Item Perincian Projek (Letak di bawah applications)
        Schema::create('application_details', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('application_id')->constrained('applications')->onDelete('cascade');
            
            $table->enum('item_category', [
                'Projek Baharu', 
                'Peningkatan Sistem', 
                'Peluasan Sistem/Projek', 
                'Penambahbaikan Peralatan', 
                'Penyelenggaraan', 
                'Khidmat Perunding ICT'
            ]);
            
            $table->text('technical_specifications');
            $table->integer('unit_quantity');
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_cost', 15, 2);
            
            $table->timestamps();
            $table->softDeletes();
        });

        // 6. Jadual Dokumen Permohonan (Dokumen Sokongan PDF)
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('application_id')->constrained('applications')->onDelete('cascade');
            $table->enum('document_type', ['PROPOSAL_PAPER', 'PRESENTATION_SLIDE_JTICT', 'PRESENTATION_SLIDE_JPICT', 'SUPPORTING_DOC']);
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->default('application/pdf');
            $table->integer('file_size_kb');
            $table->timestamps();
            $table->softDeletes();
        });

        // 7. Jadual Pemantauan Projek (Projects & Progress)
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('application_id')->constrained('applications');
            $table->foreignId('agency_id')->constrained('agencies');
            $table->foreignId('project_manager_id')->constrained('users');

            $table->string('contractor_name')->nullable();
            $table->decimal('actual_cost', 15, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('actual_completion_date')->nullable();

            // Status Indikator Prestasi
            $table->enum('status_indicator', ['GREEN', 'AMBER', 'RED'])->default('GREEN'); // Hijau (On-track), Kuning (Lewat), Merah (Sakit)
            $table->decimal('physical_progress_percent', 5, 2)->default(0.00);
            $table->decimal('financial_progress_percent', 5, 2)->default(0.00);
            $table->boolean('is_completed')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });

        // 8. Jadual Laporan Kemajuan Bulanan / Penggal
        Schema::create('project_progress_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->integer('report_year');
            $table->integer('report_month');
            
            $table->decimal('physical_actual', 5, 2);
            $table->decimal('physical_planned', 5, 2);
            $table->decimal('financial_actual', 5, 2);
            $table->decimal('financial_planned', 5, 2);

            $table->text('work_summary')->nullable();
            $table->text('issues_and_challenges')->nullable();
            $table->text('corrective_action_plan')->nullable();

            $table->enum('verified_status', ['PENDING', 'VERIFIED', 'REJECTED'])->default('PENDING');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // 9. Jadual Kajian Separuh Penggal (Khusus Pembangunan Sistem)
        Schema::create('mid_term_reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->text('system_usage_status');
            $table->text('impact_assessment');
            $table->text('technical_issues');
            $table->enum('status', ['PENDING_REVIEW', 'APPROVED', 'REJECTED'])->default('PENDING_REVIEW');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mid_term_reviews');
        Schema::dropIfExists('project_progress_reports');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('application_details');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('agencies');
    }
};