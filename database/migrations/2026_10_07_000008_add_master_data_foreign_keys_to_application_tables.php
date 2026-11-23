<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hubungkan transaksi Permohonan Projek kepada master data. Lajur teks lama
 * (procurement_type, procurement_method, item_category) dikekalkan sebagai snapshot;
 * pengisian *_id bagi rekod sedia ada dibuat dalam migration berasingan (Fasa U4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->foreignId('procurement_type_id')
                ->nullable()
                ->after('procurement_type')
                ->constrained('procurement_types')
                ->restrictOnDelete();
            $table->foreignId('procurement_method_id')
                ->nullable()
                ->after('procurement_method')
                ->constrained('procurement_methods')
                ->restrictOnDelete();
        });

        Schema::table('application_details', function (Blueprint $table): void {
            $table->foreignId('project_category_id')
                ->nullable()
                ->after('item_category')
                ->constrained('project_categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('application_details', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('project_category_id');
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('procurement_type_id');
            $table->dropConstrainedForeignId('procurement_method_id');
        });
    }
};
