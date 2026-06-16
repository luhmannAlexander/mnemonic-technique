<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BackendSchema §2.10 — frozen, ordered question queue for a session (enables resume).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('session_logs')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('knowledge_unit_id'); // denormalised for stats (no FK)
            $table->unsignedTinyInteger('position'); // 1-based order
            $table->timestamp('presented_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'position'], 'uq_sq_session_position');
            $table->index('session_id', 'idx_sq_session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_questions');
    }
};
