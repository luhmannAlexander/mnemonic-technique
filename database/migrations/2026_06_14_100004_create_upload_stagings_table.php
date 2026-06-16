<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BackendSchema §2.5 — global upload staging before project assignment (D-3).
// No soft-delete: manual expiry via scheduled command (expires_at).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_stagings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->longText('raw_markdown');
            $table->unsignedInteger('file_size_bytes');

            $table->enum('classification_status', [
                'pending', 'classifying', 'awaiting_confirmation', 'confirmed', 'failed', 'expired',
            ])->default('pending');
            $table->text('classification_error')->nullable();

            // AI suggestion blob (D-4):
            // { "suggestions": [{"type":"existing|new","project_id":1,"name":"...","reason":"..."}], "accepted_index": 0 }
            $table->json('ai_suggestion_payload')->nullable();

            $table->foreignId('assigned_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('assigned_project_name')->nullable(); // for "new project" case before the row exists

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at'); // 7 days from upload (AppFlow §4.5 D-9)
            $table->timestamps();

            $table->index(['user_id', 'classification_status', 'expires_at'], 'idx_upload_stagings_user_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_stagings');
    }
};
