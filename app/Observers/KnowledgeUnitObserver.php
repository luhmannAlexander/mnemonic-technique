<?php

namespace App\Observers;

use App\Jobs\GenerateQuestionsJob;
use App\Models\KnowledgeUnit;
use App\Models\ReviewState;

class KnowledgeUnitObserver
{
    /**
     * A manually-created unit (ReviewList::createUnit) is born `approved`, so the
     * approve side effects must fire on `created` too — not just on transition.
     */
    public function created(KnowledgeUnit $unit): void
    {
        if ($unit->unit_status === 'approved') {
            $this->onApproved($unit);
        }
    }

    /**
     * React to the draft → approved transition. `wasChanged()` (not `isDirty()`)
     * is correct inside the `updated` event, and guarding on it means editing an
     * already-approved unit does not re-seed its ReviewState.
     */
    public function updated(KnowledgeUnit $unit): void
    {
        if ($unit->wasChanged('unit_status') && $unit->unit_status === 'approved') {
            $this->onApproved($unit);
        }
    }

    /**
     * KnowledgeUnit is the leaf of the soft-delete cascade: its questions and
     * review_states are hard tables that drop via FK ON DELETE CASCADE, so no
     * cascade work is required here.
     */
    public function deleting(KnowledgeUnit $unit): void
    {
        //
    }

    /**
     * Seed the spaced-repetition state (due now) and queue question generation
     * (ImplementationPlan §2.5, §2.7). firstOrCreate keeps approve idempotent.
     */
    private function onApproved(KnowledgeUnit $unit): void
    {
        ReviewState::firstOrCreate(
            ['knowledge_unit_id' => $unit->id, 'user_id' => $unit->user_id],
            ['due_at' => now(), 'priority' => 0, 'interval_days' => 1],
        );

        GenerateQuestionsJob::dispatch($unit->id);
    }
}
