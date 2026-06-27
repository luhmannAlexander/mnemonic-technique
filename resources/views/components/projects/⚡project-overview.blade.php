<?php

use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Project $project;

    public function mount(Project $project): void
    {
        // Ownership enforced as 404 (AppFlow §1.3).
        abort_unless($project->user_id === auth()->id(), 404);

        $this->project = $project;
    }

    /** @return array{documents:int, cards:int, drafts:int, approved:int, hasExtracted:bool} */
    #[Computed]
    public function stats(): array
    {
        $documents = $this->project->documents()->count();
        $cards = $this->project->knowledgeUnits()->where('unit_status', 'approved')->count();
        $drafts = $this->project->knowledgeUnits()->where('unit_status', 'draft')->count();
        $hasExtracted = $this->project->documents()->where('status', 'done')->exists();

        return [
            'documents' => $documents,
            'cards' => $cards,
            'drafts' => $drafts,
            'approved' => $cards,
            'hasExtracted' => $hasExtracted,
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-[1200px] px-4 py-6 md:px-8">
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item :href="route('projects.index')" wire:navigate>{{ __('Lernprojekte') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $project->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="mb-6 flex items-center justify-between">
        <div class="flex flex-col gap-1">
            <flux:heading size="xl" level="1">{{ $project->name }}</flux:heading>
            @if ($project->description)
                <flux:text class="text-sm text-text-secondary">{{ $project->description }}</flux:text>
            @endif
        </div>

        @if ($this->stats['approved'] > 0)
            <flux:button variant="primary" icon="play">{{ __('Jetzt üben') }}</flux:button>
        @else
            <flux:tooltip :content="__('Es gibt noch keine bestätigten Karten zum Üben.')">
                <flux:button variant="primary" icon="play" disabled>{{ __('Jetzt üben') }}</flux:button>
            </flux:tooltip>
        @endif
    </div>

    {{-- Review banner — extracted drafts await confirmation (AppFlow §2.6) --}}
    @if ($this->stats['hasExtracted'] && $this->stats['drafts'] > 0)
        <div class="mb-6 flex items-center justify-between rounded-2xl bg-primary-muted p-4">
            <flux:text class="text-text">
                {{ trans_choice('{1}1 neue Karte wurde extrahiert ✨|[2,*]:count neue Karten wurden extrahiert ✨', $this->stats['drafts'], ['count' => $this->stats['drafts']]) }}
            </flux:text>
            <flux:button variant="primary" icon="clipboard-document-check"
                :href="route('review.index', $project)" wire:navigate>{{ __('Jetzt prüfen') }}</flux:button>
        </div>
    @endif

    {{-- Kachel tiles (AppFlow §2.6) --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- Dokumente --}}
        <a href="{{ route('documents.index', $project) }}" wire:navigate
            class="flex flex-col gap-2 rounded-2xl bg-surface p-4 shadow-md shadow-black/40 transition hover:bg-surface-raised">
            <flux:icon.document-text class="size-6 text-text-secondary" />
            <flux:heading size="lg">{{ __('Dokumente') }}</flux:heading>
            <flux:text class="text-sm text-text-secondary">
                {{ trans_choice('{0}Keine Dokumente|{1}1 Dokument|[2,*]:count Dokumente', $this->stats['documents'], ['count' => $this->stats['documents']]) }}
            </flux:text>
        </a>

        {{-- Karten --}}
        <a href="{{ route('cards.index', $project) }}" wire:navigate
            class="flex flex-col gap-2 rounded-2xl bg-surface p-4 shadow-md shadow-black/40 transition hover:bg-surface-raised">
            <flux:icon.rectangle-stack class="size-6 text-text-secondary" />
            <flux:heading size="lg">{{ __('Karten') }}</flux:heading>
            <flux:text class="text-sm text-text-secondary">
                {{ trans_choice('{0}Keine Karten|{1}1 Karte|[2,*]:count Karten', $this->stats['approved'], ['count' => $this->stats['approved']]) }}
            </flux:text>
        </a>

        {{-- Review (M2) — highlighted when drafts exist --}}
        <a href="{{ route('review.index', $project) }}" wire:navigate
            class="flex flex-col gap-2 rounded-2xl p-4 shadow-md shadow-black/40 transition hover:bg-surface-raised {{ $this->stats['drafts'] > 0 ? 'bg-primary-muted' : 'bg-surface' }}">
            <flux:icon.clipboard-document-check class="size-6 text-text-secondary" />
            <flux:heading size="lg">{{ __('Review') }}</flux:heading>
            <flux:text class="text-sm text-text-secondary">
                {{ trans_choice('{0}Nichts zu prüfen|{1}1 Entwurf|[2,*]:count Entwürfe', $this->stats['drafts'], ['count' => $this->stats['drafts']]) }}
            </flux:text>
        </a>

        {{-- Statistik (M4) --}}
        <a href="{{ route('stats.project', $project) }}" wire:navigate
            class="flex flex-col gap-2 rounded-2xl bg-surface p-4 shadow-md shadow-black/40 transition hover:bg-surface-raised">
            <flux:icon.chart-bar class="size-6 text-text-secondary" />
            <flux:heading size="lg">{{ __('Statistik') }}</flux:heading>
            <flux:text class="text-sm text-text-secondary">{{ __('Behaltensquote & Themen') }}</flux:text>
        </a>
    </div>
</div>
