<?php

namespace App\Services;

use App\Models\Document;
use App\Models\KnowledgeUnit;
use App\Models\Project;

/**
 * Restore / permanent-delete of soft-deleted items, with ancestor restoration
 * so the tree never ends up orphaned (BackendSchema §8.2, AppFlow §2.13).
 *
 * The three managed models (Project, Document, KnowledgeUnit) all use SoftDeletes,
 * so restore()/forceDelete() are available on every branch of the union.
 */
class TrashService
{
    /** Restore an item and any soft-deleted ancestors it needs to be reachable. */
    public function restore(string $type, int $id, int $userId): void
    {
        $this->restoreWithAncestors($this->resolveOwned($type, $id, $userId));
    }

    /** Permanently delete an item (FK cascade removes its children). */
    public function forceDelete(string $type, int $id, int $userId): void
    {
        $this->resolveOwned($type, $id, $userId)->forceDelete();
    }

    private function restoreWithAncestors(Project|Document|KnowledgeUnit $model): void
    {
        if ($model instanceof KnowledgeUnit && $model->document_id !== null) {
            $document = Document::onlyTrashed()->find($model->document_id);

            if ($document !== null) {
                $this->restoreWithAncestors($document);
            }
        }

        if ($model instanceof Document) {
            $project = Project::onlyTrashed()->find($model->project_id);

            if ($project !== null) {
                $this->restoreWithAncestors($project);
            }
        }

        $model->restore();
    }

    /**
     * Resolve a trashed, user-owned model. Foreign / unknown IDs become 404
     * so existence is not revealed (AppFlow §1.3).
     */
    private function resolveOwned(string $type, int $id, int $userId): Project|Document|KnowledgeUnit
    {
        $model = match ($type) {
            'project' => Project::onlyTrashed()->whereKey($id)->first(),
            'document' => Document::onlyTrashed()->whereKey($id)->first(),
            'knowledge_unit' => KnowledgeUnit::onlyTrashed()->whereKey($id)->first(),
            default => abort(404),
        };

        abort_if($model === null || $model->user_id !== $userId, 404);

        return $model;
    }
}
