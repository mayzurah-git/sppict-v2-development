<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UM13: token pautan "Tetapkan Kata Laluan" bagi akaun yang didaftar/diset semula oleh pentadbir
 * (broker `account_setup`). Jadual berasingan daripada `password_reset_tokens` supaya tempoh sah
 * (72 jam) tidak bercampur dengan pautan Lupa Kata Laluan (60 minit). Token disimpan dalam bentuk hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_setup_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_setup_tokens');
    }
};
