<?php

namespace App\Observers;

use App\Models\Document;
use App\Models\KnowledgeUnit;

class DocumentObserver
{
    /** Cascade soft-delete to knowledge_units. BackendSchema §8.1. */
    public function deleting(Document $document): void
    {
        $document->knowledgeUnits()->whereNull('deleted_at')->get()
            ->each(fn (KnowledgeUnit $unit) => $unit->delete());
    }

    /** Restore knowledge_units cascaded with this document (1s grace). BackendSchema §8.2. */
    public function restoring(Document $document): void
    {
        if ($document->deleted_at === null) {
            return;
        }

        $document->knowledgeUnits()
            ->onlyTrashed()
            ->where('deleted_at', '>=', $document->deleted_at->copy()->subSecond())
            ->get()
            ->each(fn (KnowledgeUnit $unit) => $unit->restore());
    }
}
