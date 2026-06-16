<?php

namespace App\Models;

use Database\Factories\SessionQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SessionQuestion extends Model
{
    /** @use HasFactory<SessionQuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'session_id',
        'question_id',
        'knowledge_unit_id',
        'position',
        'presented_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'presented_at' => 'datetime',
    ];

    /** @return BelongsTo<SessionLog, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SessionLog::class, 'session_id');
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** @return BelongsTo<KnowledgeUnit, $this> */
    public function knowledgeUnit(): BelongsTo
    {
        return $this->belongsTo(KnowledgeUnit::class);
    }

    /** @return HasOne<Attempt, $this> */
    public function attempt(): HasOne
    {
        return $this->hasOne(Attempt::class, 'session_question_id');
    }
}
