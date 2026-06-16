<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BackendSchema §2.4 — uploaded markdown file + extraction status machine.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // denormalised for auth
            $table->string('filename');
            $table->longText('raw_markdown');
            $table->unsignedInteger('file_size_bytes'); // validated ≤ 2 MB at upload
            $table->enum('status', ['pending', 'processing', 'done', 'error'])->default('pending');
            $table->text('error_detail')->nullable();
            $table->unsignedTinyInteger('extraction_attempts')->default(0); // max 2 retries
            $table->unsignedSmallInteger('extracted_unit_count')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status', 'deleted_at'], 'idx_documents_project_status');
            $table->index(['user_id', 'status', 'deleted_at'], 'idx_documents_user_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
