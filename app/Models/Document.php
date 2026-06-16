<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'user_id',
        'filename',
        'raw_markdown',
        'file_size_bytes',
        'status',
        'error_detail',
        'extraction_attempts',
        'extracted_unit_count',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'extraction_attempts' => 'integer',
        'extracted_unit_count' => 'integer',
    ];

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

    /** @return HasMany<KnowledgeUnit, $this> */
    public function knowledgeUnits(): HasMany
    {
        return $this->hasMany(KnowledgeUnit::class);
    }
}
