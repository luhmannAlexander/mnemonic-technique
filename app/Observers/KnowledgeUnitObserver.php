<?php

namespace App\Observers;

use App\Models\KnowledgeUnit;

class KnowledgeUnitObserver
{
    /**
     * KnowledgeUnit is the leaf of the soft-delete cascade: its questions and
     * review_states are hard tables that drop via FK ON DELETE CASCADE, so no
     * cascade work is required here in Milestone 1.
     *
     * Milestone 2 adds an updated() hook here that, on transition to
     * unit_status = 'approved', dispatches GenerateQuestionsJob and creates the
     * ReviewState row (ImplementationPlan §2.5, §2.7).
     */
    public function deleting(KnowledgeUnit $unit): void
    {
        //
    }
}
