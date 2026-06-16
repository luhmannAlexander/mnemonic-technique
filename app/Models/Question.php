<?php

namespace App\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'knowledge_unit_id',
        'kind',
        'prompt',
        'options_json',
        'correct_answer',
    ];

    protected $casts = [
        'options_json' => 'array',
    ];

    /** @return BelongsTo<KnowledgeUnit, $this> */
    public function knowledgeUnit(): BelongsTo
    {
        return $this->belongsTo(KnowledgeUnit::class);
    }
}
