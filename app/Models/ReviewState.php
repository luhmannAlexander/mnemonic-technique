<?php

namespace App\Models;

use Database\Factories\ReviewStateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Composite-PK table (knowledge_unit_id, user_id) with only an updated_at column.
 *
 * Eloquent does not support array primary keys for write operations (save/update),
 * so instead of BackendSchema's literal `$primaryKey = [...]` we use
 * `knowledge_unit_id` as the model key. This is safe because each knowledge unit
 * has exactly one owner (no cross-user sharing, PRD §2.3), so knowledge_unit_id is
 * effectively unique within review_states. Queries that need both keys pass them
 * explicitly (firstOrCreate / updateOrCreate with the full attribute set).
 */
class ReviewState extends Model
{
    /** @use HasFactory<ReviewStateFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $primaryKey = 'knowledge_unit_id';

    protected $keyType = 'int';

    public const CREATED_AT = null; // table has no created_at column

    protected $fillable = [
        'knowledge_unit_id',
        'user_id',
        'due_at',
        'priority',
        'interval_days',
        'last_result',
        'last_attempted_at',
        'attempt_count',
        'correct_count',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'last_attempted_at' => 'datetime',
        'priority' => 'integer',
        'interval_days' => 'integer',
        'attempt_count' => 'integer',
        'correct_count' => 'integer',
    ];

    /**
     * Limit to cards whose knowledge unit has at least one generated question.
     * SessionService::buildQueue skips question-less units, so they are not
     * practisable — counting/selecting them would make "Jetzt üben" a dead button.
     *
     * @param  Builder<ReviewState>  $query
     */
    public function scopeWhereUnitHasQuestions(Builder $query): void
    {
        $query->whereExists(function (QueryBuilder $sub): void {
            $sub->select(DB::raw('1'))
                ->from('questions')
                ->whereColumn('questions.knowledge_unit_id', 'review_states.knowledge_unit_id');
        });
    }

    /** @return BelongsTo<KnowledgeUnit, $this> */
    public function knowledgeUnit(): BelongsTo
    {
        return $this->belongsTo(KnowledgeUnit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
