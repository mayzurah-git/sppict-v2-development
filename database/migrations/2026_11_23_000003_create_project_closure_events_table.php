<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SP4: sejarah penutupan projek (keputusan I.6, audit SP0 L.8): permohonan penutupan, tarik balik,
 * pengesahan / penolakan Urus Setia dan penutupan tanpa siap. Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_closure_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->string('event_type', 20);   // REQUESTED | WITHDRAWN | APPROVED | REJECTED | TERMINATED
            $table->text('remarks')->nullable();
            $table->foreignId('actor_id')->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_closure_events');
    }
};
