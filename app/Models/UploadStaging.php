<?php

namespace App\Models;

use Database\Factories\UploadStagingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// No SoftDeletes: rows are hard-deleted on promotion or expired via scheduled command.
class UploadStaging extends Model
{
    /** @use HasFactory<UploadStagingFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'filename',
        'raw_markdown',
        'file_size_bytes',
        'classification_status',
        'classification_error',
        'ai_suggestion_payload',
        'assigned_project_id',
        'assigned_project_name',
        'confirmed_at',
        'expires_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'ai_suggestion_payload' => 'array',
        'confirmed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function assignedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'assigned_project_id');
    }
}
