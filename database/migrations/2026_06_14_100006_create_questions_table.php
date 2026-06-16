<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BackendSchema §2.7 — at most one MC + one free row per unit (D-7).
// Overwritten on AI regeneration via the uq_question_unit_kind unique key.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_unit_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['mc', 'free']);
            $table->text('prompt');
            $table->json('options_json')->nullable(); // MC only
            $table->text('correct_answer');
            $table->timestamps();

            $table->unique(['knowledge_unit_id', 'kind'], 'uq_question_unit_kind');
            $table->index('knowledge_unit_id', 'idx_questions_unit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
