<?php

use App\Jobs\ExtractKnowledgeJob;
use App\Models\Document;
use App\Models\Project;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Project $project;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $files = [];

    public bool $showDeleteModal = false;

    public ?int $deleteId = null;

    public function mount(Project $project): void
    {
        abort_unless($project->user_id === auth()->id(), 404);

        $this->project = $project;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Document>
     */
    #[Computed]
    public function documents()
    {
        return $this->project->documents()->latest()->get();
    }

    /** Drives the 5s status polling — only while extraction is in flight (AppFlow §2.7). */
    #[Computed]
    public function hasActiveExtraction(): bool
    {
        return $this->project->documents()
            ->whereIn('status', ['pending', 'processing'])
            ->exists();
    }

    public function upload(): void
    {
        $this->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'extensions:md,txt', 'max:2048'],
        ], [
            'files.*.extensions' => __('Nur Markdown-Dateien (.md) sind erlaubt. Wandle die Datei um oder wähle eine andere.'),
            'files.*.max' => __('Die Datei ist größer als 2 MB. Verkleinere sie oder teile den Inhalt auf.'),
        ]);

        foreach ($this->files as $file) {
            $document = Document::create([
                'project_id' => $this->project->id,
                'user_id' => $this->project->user_id,
                'filename' => $file->getClientOriginalName(),
                'raw_markdown' => $file->get(),
                'file_size_bytes' => $file->getSize(),
                'status' => 'pending',
            ]);

            ExtractKnowledgeJob::dispatch($document->id);
        }

        $count = count($this->files);
        $this->reset('files');
        unset($this->documents, $this->hasActiveExtraction);

        Flux::toast(
            text: trans_choice('{1}1 Dokument hochgeladen.|[2,*]:count Dokumente hochgeladen.', $count, ['count' => $count]),
            variant: 'success',
        );
    }

    public function retry(int $id): void
    {
        $document = $this->ownedDocumentOrFail($id);

        $document->update([
            'status' => 'pending',
            'extraction_attempts' => 0,
            'error_detail' => null,
        ]);

        ExtractKnowledgeJob::dispatch($document->id);
        unset($this->documents, $this->hasActiveExtraction);
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $this->ownedDocumentOrFail($id)->id;
        $this->showDeleteModal = true;
    }

    public function deleteDocument(): void
    {
        $this->ownedDocumentOrFail($this->deleteId)->delete();

        $this->showDeleteModal = false;
        $this->reset('deleteId');
        unset($this->documents, $this->hasActiveExtraction);

        Flux::toast(text: __('Dokument in den Papierkorb verschoben.'), variant: 'success');
    }

    private function ownedDocumentOrFail(?int $id): Document
    {
        $document = $this->project->documents()->whereKey($id)->first();

        abort_unless($document !== null, 404);

        return $document;
    }
}; ?>

<div class="mx-auto w-full max-w-[1200px] px-4 py-6 md:px-8">
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item :href="route('projects.index')" wire:navigate>{{ __('Lernprojekte') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('projects.show', $project)" wire:navigate>{{ $project->name }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Dokumente') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <flux:heading size="xl" level="1" class="mb-6">{{ __('Dokumente') }}</flux:heading>

    {{-- Upload area (AppFlow §2.7) --}}
    <form wire:submit="upload" class="mb-8 flex flex-col gap-3 rounded-2xl bg-surface p-4 shadow-md shadow-black/40">
        <flux:input
            type="file"
            wire:model="files"
            multiple
            accept=".md,.txt"
            :label="__('Markdown-Dateien hochladen')"
            :description="__('Nur .md, max. 2 MB pro Datei. Mehrfachauswahl möglich.')"
        />
        <flux:error name="files.*" />
        <div class="flex items-center justify-between">
            <flux:text class="text-sm text-text-secondary" wire:loading wire:target="files">
                {{ __('Dateien werden geladen …') }}
            </flux:text>
            <flux:spacer />
            <flux:button type="submit" variant="primary" icon="arrow-up-tray">{{ __('Hochladen') }}</flux:button>
        </div>
    </form>

    {{-- Document list (ContentGuidelines §7.9) --}}
    @if ($this->documents->isEmpty())
        <div class="flex flex-col items-center gap-4 rounded-2xl bg-surface p-8 text-center shadow-md shadow-black/40">
            <flux:icon.document-text class="size-6 text-text-muted" />
            <flux:text class="text-text-secondary">
                {{ __('Noch keine Dokumente. Lade deine erste Markdown-Datei hoch, um zu starten.') }}
            </flux:text>
        </div>
    @else
        <div @if ($this->hasActiveExtraction) wire:poll.5s @endif class="overflow-hidden rounded-2xl bg-surface shadow-md shadow-black/40">
            @foreach ($this->documents as $document)
                <div class="flex flex-col border-b border-border last:border-b-0">
                    <div class="flex h-12 items-center gap-4 px-4">
                        <a href="{{ route('documents.show', [$project, $document]) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-2">
                            <flux:icon.document-text class="size-4 shrink-0 text-text-secondary" />
                            <span class="truncate text-text">{{ $document->filename }}</span>
                        </a>
                        <span class="hidden w-24 shrink-0 text-sm text-text-secondary sm:block">{{ $document->created_at->format('d.m.Y') }}</span>
                        <span class="hidden w-28 shrink-0 text-sm text-text-secondary md:block">
                            {{ $document->extracted_unit_count !== null
                                ? trans_choice('{0}Keine Karten|{1}1 Karte|[2,*]:count Karten', $document->extracted_unit_count, ['count' => $document->extracted_unit_count])
                                : '—' }}
                        </span>
                        <x-document-status-badge :status="$document->status" />
                        <div class="flex items-center gap-1">
                            @if ($document->status === 'error')
                                <flux:button size="sm" variant="ghost" icon="arrow-path" :aria-label="__('Erneut versuchen')" wire:click="retry({{ $document->id }})" />
                            @endif
                            <flux:button size="sm" variant="ghost" icon="trash" :aria-label="__('Dokument löschen')" wire:click="confirmDelete({{ $document->id }})" />
                        </div>
                    </div>

                    @if ($document->status === 'error' && $document->error_detail)
                        <div class="flex items-start gap-2 border-t border-border bg-bg/40 px-4 py-2 text-sm text-text-secondary">
                            <flux:icon.exclamation-circle class="mt-0.5 size-4 shrink-0 text-danger" />
                            <span>{{ $document->error_detail }}</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Delete confirmation (AppFlow §2.7) --}}
    <flux:modal wire:model.self="showDeleteModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Dokument löschen?') }}</flux:heading>
            <flux:text class="text-text-secondary">
                {{ __('Auch die aus dieser Datei extrahierten Karten und ihr Lernfortschritt wandern in den Papierkorb.') }}
            </flux:text>
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteDocument">{{ __('Dokument löschen') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
