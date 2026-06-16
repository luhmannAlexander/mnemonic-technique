<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BackendSchema §2.6 — central content entity ("Karte" in the UI).
// document_id is nullable: manually-created units have document_id = NULL.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete(); // denormalised for card view
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();    // denormalised for auth
            $table->enum('type', ['fact', 'concept', 'relation', 'vocab']);
            $table->string('title', 500);
            $table->text('content');
            $table->string('source_ref', 500)->nullable();
            $table->string('topic_tag')->nullable();
            $table->enum('unit_status', ['draft', 'approved'])->default('draft');
            $table->enum('technique', ['spaced', 'acronym', 'story', 'loci', 'major'])->default('spaced');
            $table->text('technique_material')->nullable();
            $table->boolean('manually_edited')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'unit_status', 'deleted_at'], 'idx_ku_project_status');
            $table->index(['project_id', 'type', 'deleted_at'], 'idx_ku_project_type');
            $table->index(['project_id', 'topic_tag', 'deleted_at'], 'idx_ku_project_topic');
            $table->index(['document_id', 'unit_status', 'deleted_at'], 'idx_ku_document_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_units');
    }
};
