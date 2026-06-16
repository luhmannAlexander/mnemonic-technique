<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BackendSchema §2.9 — one row per practice session. project_id NULL = global.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->enum('session_type', ['due', 'voluntary'])->default('due'); // D-2
            $table->enum('status', ['active', 'interrupted', 'finished', 'abandoned'])->default('active');
            $table->unsignedTinyInteger('questions_total')->default(0);
            $table->unsignedTinyInteger('questions_answered')->default(0);
            $table->unsignedTinyInteger('questions_correct')->default(0);
            $table->unsignedTinyInteger('questions_partial')->default(0);
            $table->unsignedTinyInteger('questions_wrong')->default(0);
            $table->unsignedTinyInteger('questions_pending')->default(0); // async free-text awaiting grade
            $table->unsignedTinyInteger('current_question_index')->default(0); // for "Fortsetzen"
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_sl_user_status');
            $table->index(['user_id', 'project_id', 'status'], 'idx_sl_user_project');
            $table->index(['user_id', 'finished_at'], 'idx_sl_user_finished'); // streak + stats
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_logs');
    }
};
