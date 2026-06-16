<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\KnowledgeUnit;
use App\Models\Project;
use App\Models\UploadStaging;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trash:purge')]
#[Description('Permanently delete trashed items older than 30 days and expired upload stagings (BackendSchema §8.3).')]
class PurgeTrash extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays(30);

        // Children before parents so cascades do not race the explicit deletes.
        KnowledgeUnit::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
        Document::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
        Project::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();

        // Unconfirmed stagings expire after 7 days (expires_at set at upload time).
        UploadStaging::where('expires_at', '<', now())->whereNull('confirmed_at')->delete();

        $this->info('Trash purged.');

        return self::SUCCESS;
    }
}
