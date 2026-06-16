<?php

namespace App\Models;

use Database\Factories\SessionLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionLog extends Model
{
    /** @use HasFactory<SessionLogFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'session_type',
        'status',
        'questions_total',
        'questions_answered',
        'questions_correct',
        'questions_partial',
        'questions_wrong',
        'questions_pending',
        'current_question_index',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'questions_total' => 'integer',
        'questions_answered' => 'integer',
        'questions_correct' => 'integer',
        'questions_partial' => 'integer',
        'questions_wrong' => 'integer',
        'questions_pending' => 'integer',
        'current_question_index' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<SessionQuestion, $this> */
    public function sessionQuestions(): HasMany
    {
        return $this->hasMany(SessionQuestion::class, 'session_id');
    }

    /** @return HasMany<Attempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class, 'session_id');
    }
}
