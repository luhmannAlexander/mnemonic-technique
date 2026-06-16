<?php

namespace App\Models;

use Database\Factories\AttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attempt extends Model
{
    /** @use HasFactory<AttemptFactory> */
    use HasFactory;

    protected $fillable = [
        'session_id',
        'session_question_id',
        'question_id',
        'knowledge_unit_id',
        'user_id',
        'project_id',
        'topic_tag',
        'kind',
        'given_answer',
        'result',
        'ai_feedback',
        'ai_graded_at',
        'attempted_at',
    ];

    protected $casts = [
        'ai_graded_at' => 'datetime',
        'attempted_at' => 'datetime',
    ];

    /** @return BelongsTo<SessionLog, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SessionLog::class, 'session_id');
    }

    /** @return BelongsTo<SessionQuestion, $this> */
    public function sessionQuestion(): BelongsTo
    {
        return $this->belongsTo(SessionQuestion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<KnowledgeUnit, $this> */
    public function knowledgeUnit(): BelongsTo
    {
        return $this->belongsTo(KnowledgeUnit::class);
    }
}
