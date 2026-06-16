<?php

namespace App\Models;

use Database\Factories\KnowledgeUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeUnit extends Model
{
    /** @use HasFactory<KnowledgeUnitFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_id',
        'project_id',
        'user_id',
        'type',
        'title',
        'content',
        'source_ref',
        'topic_tag',
        'unit_status',
        'technique',
        'technique_material',
        'manually_edited',
    ];

    protected $casts = [
        'manually_edited' => 'boolean',
    ];

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /** @return HasMany<ReviewState, $this> */
    public function reviewStates(): HasMany
    {
        return $this->hasMany(ReviewState::class);
    }
}
