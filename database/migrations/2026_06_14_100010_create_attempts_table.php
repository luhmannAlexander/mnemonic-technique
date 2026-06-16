<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BackendSchema §2.11 — one row per answered question; source of truth for stats (D-8).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('session_logs')->cascadeOnDelete();
            $table->foreignId('session_question_id')->constrained('session_questions')->cascadeOnDelete();
            $table->unsignedBigInteger('question_id');       // denormalised (may be regenerated)
            $table->unsignedBigInteger('knowledge_unit_id'); // denormalised for retention queries
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('project_id')->nullable(); // denormalised (NULL = global)
            $table->string('topic_tag')->nullable(); // snapshot from ku at attempt time
            $table->enum('kind', ['mc', 'free']);
            $table->text('given_answer');
            $table->enum('result', ['correct', 'partial', 'wrong', 'pending'])->default('pending');
            $table->text('ai_feedback')->nullable();
            $table->timestamp('ai_graded_at')->nullable();
            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'knowledge_unit_id', 'attempted_at'], 'idx_attempts_user_ku');
            $table->index(['user_id', 'attempted_at'], 'idx_attempts_user_date');
            $table->index('session_id', 'idx_attempts_session');
            $table->index(['result', 'ai_graded_at'], 'idx_attempts_pending');
            $table->index(['project_id', 'attempted_at'], 'idx_attempts_project');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};
