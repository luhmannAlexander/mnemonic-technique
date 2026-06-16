<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BackendSchema §2.8 — spaced-repetition queue. Composite PK, no id column,
// only updated_at (no created_at).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_states', function (Blueprint $table) {
            $table->foreignId('knowledge_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamp('due_at')->useCurrent();
            $table->tinyInteger('priority')->default(0);          // AI-set 0–100; higher = sooner
            $table->unsignedSmallInteger('interval_days')->default(1); // fallback interval (D-5)
            $table->enum('last_result', ['correct', 'partial', 'wrong'])->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

            $table->primary(['knowledge_unit_id', 'user_id']);
            $table->index(['user_id', 'due_at'], 'idx_rs_user_due'); // "Heute fällig"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_states');
    }
};
