<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('has all required tables', function () {
    $tables = [
        'users', 'user_settings', 'projects', 'documents',
        'upload_stagings', 'knowledge_units', 'questions',
        'review_states', 'session_logs', 'session_questions', 'attempts',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Table {$table} missing");
    }
});

it('users has a deleted_at column', function () {
    expect(Schema::hasColumn('users', 'deleted_at'))->toBeTrue();
});

it('knowledge_units has all required columns', function () {
    $columns = [
        'id', 'document_id', 'project_id', 'user_id', 'type', 'title',
        'content', 'source_ref', 'topic_tag', 'unit_status', 'technique',
        'technique_material', 'manually_edited', 'created_at', 'updated_at', 'deleted_at',
    ];

    foreach ($columns as $column) {
        expect(Schema::hasColumn('knowledge_units', $column))->toBeTrue("knowledge_units.{$column} missing");
    }
});

it('review_states uses a composite key (no id column) and only updated_at', function () {
    expect(Schema::hasColumn('review_states', 'id'))->toBeFalse();
    expect(Schema::hasColumn('review_states', 'created_at'))->toBeFalse();
    expect(Schema::hasColumn('review_states', 'updated_at'))->toBeTrue();
    expect(Schema::hasColumn('review_states', 'knowledge_unit_id'))->toBeTrue();
    expect(Schema::hasColumn('review_states', 'user_id'))->toBeTrue();
});

it('attempts has the stats columns', function () {
    foreach (['result', 'topic_tag', 'project_id', 'ai_graded_at', 'attempted_at'] as $column) {
        expect(Schema::hasColumn('attempts', $column))->toBeTrue("attempts.{$column} missing");
    }
});
