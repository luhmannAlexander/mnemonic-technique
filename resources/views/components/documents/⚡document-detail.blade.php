<?php

use App\Jobs\ExtractKnowledgeJob;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public Document $document;

    public function mount(Project $project, Document $document): void
    {
        // Ownership: project must belong to the user, and the document must belong
        // to that project. The route uses scopeBindings(), but re-check here so the
        // component is safe when mounted directly (tests, future callers). (AppFlow §1.3)
        abort_unless($project->user_id === auth()->id(), 404);
        abort_unless($document->project_id === $project->id, 404);

        $this->project = $project;
        $this->document = $document;
    }

    /**
     * Knowledge units extracted from this document (none until Milestone 2).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\KnowledgeUnit>
     */
    #[Computed]
    public function units()
    {
        return $this->document->knowledgeUnits()->latest()->get();
    }

    /** Raw markdown rendered to safe HTML — raw HTML is stripped to prevent XSS. */
    #[Computed]
    public function renderedMarkdown(): string
    {
        return Str::markdown($this->document->raw_markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function retry(): void
    {
        $this->document->update([
            'status' => 'pending',
            'extraction_attempts' => 0,
            'error_detail' => null,
        ]);

        ExtractKnowledgeJob::dispatch($this->document->id);
    }
}; ?>

<div class="mx-auto w-full max-w-[1200px] px-4 py-6 md:px-8">
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item :href="route('projects.index')" wire:navigate>{{ __('Lernprojekte') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('projects.show', $project)" wire:navigate>{{ $project->name }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('documents.index', $project)" wire:navigate>{{ __('Dokumente') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $document->filename }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="mb-6 flex items-center justify-between gap-4">
        <flux:heading size="xl" level="1" class="min-w-0 truncate">{{ $document->filename }}</flux:heading>
        <x-document-status-badge :status="$document->status" />
    </div>

    {{-- Error block + retry (AppFlow §2.8) --}}
    @if ($document->status === 'error')
        <div class="mb-6 flex flex-col gap-3 rounded-2xl bg-surface p-4 shadow-md shadow-black/40">
            <div class="flex items-start gap-2">
                <flux:icon.exclamation-circle class="mt-0.5 size-5 shrink-0 text-danger" />
                <div class="flex flex-col gap-1">
                    <flux:text class="text-text">{{ __('Die Extraktion ist fehlgeschlagen. Details unten – du kannst es erneut versuchen.') }}</flux:text>
                    @if ($document->error_detail)
                        <flux:text class="text-sm text-text-secondary">{{ $document->error_detail }}</flux:text>
                    @endif
                </div>
            </div>
            <div>
                <flux:button size="sm" variant="primary" icon="arrow-path" wire:click="retry">{{ __('Erneut versuchen') }}</flux:button>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
        {{-- Rohtext (read-only rendered markdown) --}}
        <section class="rounded-2xl bg-surface p-4 shadow-md shadow-black/40">
            <flux:heading size="lg" class="mb-3">{{ __('Rohtext') }}</flux:heading>
            <div class="markdown-body max-w-none text-text">
                {!! $this->renderedMarkdown !!}
            </div>
        </section>

        {{-- Extrahierte Einheiten dieser Datei --}}
        <section class="rounded-2xl bg-surface p-4 shadow-md shadow-black/40">
            <flux:heading size="lg" class="mb-3">{{ __('Karten aus dieser Datei') }}</flux:heading>

            @if ($document->status === 'processing')
                <flux:text class="text-sm text-text-secondary">{{ __('Wissenseinheiten werden extrahiert …') }}</flux:text>
            @elseif ($this->units->isEmpty())
                <flux:text class="text-sm text-text-secondary">{{ __('Noch keine Karten aus dieser Datei.') }}</flux:text>
            @else
                <div class="flex flex-col gap-2">
                    @foreach ($this->units as $unit)
                        <div class="flex items-center gap-3 rounded-lg border border-border px-3 py-2">
                            <x-knowledge-type-badge :type="$unit->type" />
                            <span class="min-w-0 flex-1 truncate text-sm text-text">{{ $unit->title }}</span>
                            <flux:badge size="sm" :color="$unit->unit_status === 'approved' ? 'green' : 'zinc'">
                                {{ $unit->unit_status === 'approved' ? __('Bestätigt') : __('Entwurf') }}
                            </flux:badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
