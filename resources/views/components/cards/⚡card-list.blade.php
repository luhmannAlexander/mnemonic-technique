<?php

use App\Models\KnowledgeUnit;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Karten')] class extends Component
{
    use WithPagination;

    public Project $project;

    public string $filterType = '';

    public string $filterTopic = '';

    public string $filterLearnStatus = '';

    // Edit modal (cards always edit an existing approved unit).
    public bool $showEditModal = false;

    public ?int $editId = null;

    public string $editType = 'fact';

    public string $editTitle = '';

    public string $editContent = '';

    public ?string $editTopicTag = null;

    public string $editTechnique = 'spaced';

    public ?string $editTechniqueMaterial = null;

    public bool $showDeleteModal = false;

    public ?int $deleteId = null;

    public function mount(Project $project): void
    {
        // Ownership enforced as 404 (AppFlow §1.3).
        abort_unless($project->user_id === auth()->id(), 404);

        $this->project = $project;
    }

    /** Reset pagination whenever a filter changes so the user lands on page 1. */
    public function updated(string $property): void
    {
        if (str_starts_with($property, 'filter')) {
            $this->resetPage();
        }
    }

    /** @return \Illuminate\Pagination\LengthAwarePaginator<int, KnowledgeUnit> */
    #[Computed]
    public function cards()
    {
        return KnowledgeUnit::query()
            ->where('project_id', $this->project->id)
            ->where('unit_status', 'approved')
            ->when($this->filterType !== '', fn (Builder $q) => $q->where('type', $this->filterType))
            ->when($this->filterTopic !== '', fn (Builder $q) => $q->where('topic_tag', $this->filterTopic))
            ->when($this->filterLearnStatus !== '', fn (Builder $q) => $this->applyLearnStatus($q))
            ->with('reviewStates')
            ->latest()
            ->paginate(50);
    }

    /** Distinct topic tags across this project's approved cards, for the filter dropdown. */
    #[Computed]
    public function topics(): array
    {
        return KnowledgeUnit::query()
            ->where('project_id', $this->project->id)
            ->where('unit_status', 'approved')
            ->whereNotNull('topic_tag')
            ->distinct()
            ->orderBy('topic_tag')
            ->pluck('topic_tag')
            ->all();
    }

    public function startEdit(int $id): void
    {
        $unit = $this->ownedCardOrFail($id);

        $this->editId = $unit->id;
        $this->editType = $unit->type;
        $this->editTitle = $unit->title;
        $this->editContent = $unit->content;
        $this->editTopicTag = $unit->topic_tag;
        $this->editTechnique = $unit->technique;
        $this->editTechniqueMaterial = $unit->technique_material;
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function saveUnit(): void
    {
        $data = $this->validate([
            'editType' => ['required', Rule::in(['fact', 'concept', 'relation', 'vocab'])],
            'editTitle' => ['required', 'string', 'max:500'],
            'editContent' => ['required', 'string'],
            'editTopicTag' => ['nullable', 'string', 'max:255'],
            'editTechnique' => ['required', Rule::in(['spaced', 'acronym', 'story', 'loci', 'major'])],
            'editTechniqueMaterial' => ['nullable', 'string'],
        ]);

        // Editing a card always marks it manually_edited so question regeneration
        // never overwrites the user's wording (ImplementationPlan §2.8).
        $this->ownedCardOrFail($this->editId)->update([
            'type' => $data['editType'],
            'title' => $data['editTitle'],
            'content' => $data['editContent'],
            'topic_tag' => $data['editTopicTag'] ?: null,
            'technique' => $data['editTechnique'],
            'technique_material' => $data['editTechniqueMaterial'] ?: null,
            'manually_edited' => true,
        ]);

        $this->showEditModal = false;
        unset($this->cards, $this->topics);
        Flux::toast(text: __('Karte gespeichert.'), variant: 'success');
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $this->ownedCardOrFail($id)->id;
        $this->showEditModal = false;
        $this->showDeleteModal = true;
    }

    public function deleteCard(): void
    {
        // Approved cards carry learning progress, so they go to the trash (soft
        // delete), unlike discarded drafts (AppFlow §2.10).
        $this->ownedCardOrFail($this->deleteId)->delete();

        $this->showDeleteModal = false;
        $this->reset('deleteId');
        unset($this->cards, $this->topics);
        Flux::toast(text: __('Karte in den Papierkorb verschoben.'), variant: 'success');
    }

    private function applyLearnStatus(Builder $query): Builder
    {
        $userId = auth()->id();

        return match ($this->filterLearnStatus) {
            'neu' => $query->whereHas('reviewStates', fn (Builder $q) => $q
                ->where('user_id', $userId)->where('attempt_count', 0)),
            'faellig' => $query->whereHas('reviewStates', fn (Builder $q) => $q
                ->where('user_id', $userId)->where('attempt_count', '>', 0)->where('due_at', '<=', now())),
            'sicher' => $query->whereHas('reviewStates', fn (Builder $q) => $q
                ->where('user_id', $userId)->where('attempt_count', '>', 0)->where('due_at', '>', now())),
            default => $query,
        };
    }

    /** Resolve an approved card owned by the current user in this project, or 404. */
    private function ownedCardOrFail(?int $id): KnowledgeUnit
    {
        $unit = KnowledgeUnit::query()
            ->where('project_id', $this->project->id)
            ->where('user_id', auth()->id())
            ->where('unit_status', 'approved')
            ->find($id);

        abort_unless($unit !== null, 404);

        return $unit;
    }
}; ?>

<div class="mx-auto w-full max-w-[1200px] px-4 py-6 md:px-8">
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item :href="route('projects.index')" wire:navigate>{{ __('Lernprojekte') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('projects.show', $project)" wire:navigate>{{ $project->name }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Karten') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('Karten') }}</flux:heading>

        {{-- Filter bar (AppFlow §2.10) --}}
        <div class="flex flex-wrap items-center gap-2">
            <flux:select wire:model.live="filterType" size="sm" class="w-40">
                <flux:select.option value="">{{ __('Alle Typen') }}</flux:select.option>
                <flux:select.option value="fact">{{ __('Fakt') }}</flux:select.option>
                <flux:select.option value="concept">{{ __('Konzept') }}</flux:select.option>
                <flux:select.option value="relation">{{ __('Zusammenhang') }}</flux:select.option>
                <flux:select.option value="vocab">{{ __('Vokabel') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="filterTopic" size="sm" class="w-40">
                <flux:select.option value="">{{ __('Alle Themen') }}</flux:select.option>
                @foreach ($this->topics as $topic)
                    <flux:select.option :value="$topic">{{ $topic }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filterLearnStatus" size="sm" class="w-40">
                <flux:select.option value="">{{ __('Alle Lernstatus') }}</flux:select.option>
                <flux:select.option value="neu">{{ __('Neu') }}</flux:select.option>
                <flux:select.option value="faellig">{{ __('Fällig') }}</flux:select.option>
                <flux:select.option value="sicher">{{ __('Sicher') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    @if ($this->cards->isEmpty())
        <div class="flex flex-col items-center gap-4 rounded-2xl bg-surface p-8 text-center shadow-md shadow-black/40">
            <flux:icon.rectangle-stack class="size-8 text-text-muted" />
            <flux:text class="text-text-secondary">
                {{ __('Noch keine Karten. Lade ein Dokument hoch oder bestätige extrahierte Einheiten im Review.') }}
            </flux:text>
            <div class="flex gap-3">
                <flux:button variant="ghost" :href="route('documents.index', $project)" wire:navigate>{{ __('Dokumente') }}</flux:button>
                <flux:button variant="primary" :href="route('review.index', $project)" wire:navigate>{{ __('Zum Review') }}</flux:button>
            </div>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->cards as $unit)
                <x-flip-card :unit="$unit" :wire:key="$unit->id" />
            @endforeach
        </div>

        <div class="mt-6">
            {{ $this->cards->links() }}
        </div>
    @endif

    {{-- Edit modal (ContentGuidelines §7.6, max 720px) --}}
    <flux:modal wire:model.self="showEditModal" class="md:w-[45rem]">
        <form wire:submit="saveUnit" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ __('Karte bearbeiten') }}</flux:heading>

            <flux:select wire:model="editType" :label="__('Typ')">
                <flux:select.option value="fact">{{ __('Fakt') }}</flux:select.option>
                <flux:select.option value="concept">{{ __('Konzept') }}</flux:select.option>
                <flux:select.option value="relation">{{ __('Zusammenhang') }}</flux:select.option>
                <flux:select.option value="vocab">{{ __('Vokabel') }}</flux:select.option>
            </flux:select>

            <flux:input wire:model="editTitle" :label="__('Titel')" />
            <flux:textarea wire:model="editContent" :label="__('Inhalt')" rows="4" />
            <flux:input wire:model="editTopicTag" :label="__('Thema / Tag')" :placeholder="__('optional')" />

            <flux:select wire:model="editTechnique" :label="__('Technik')">
                <flux:select.option value="spaced">{{ __('Spaced Repetition') }}</flux:select.option>
                <flux:select.option value="acronym">{{ __('Eselsbrücke') }}</flux:select.option>
                <flux:select.option value="story">{{ __('Geschichten-Methode') }}</flux:select.option>
                <flux:select.option value="loci">{{ __('Loci-Methode') }}</flux:select.option>
                <flux:select.option value="major">{{ __('Major-System') }}</flux:select.option>
            </flux:select>

            <flux:textarea wire:model="editTechniqueMaterial" :label="__('Technik-Material (optional)')" rows="3" />

            <div class="flex items-center justify-between gap-3">
                <flux:button variant="ghost" icon="trash" wire:click="confirmDelete({{ $editId }})">{{ __('Löschen') }}</flux:button>
                <div class="flex gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Speichern') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    {{-- Delete confirmation --}}
    <flux:modal wire:model.self="showDeleteModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Karte löschen?') }}</flux:heading>
            <flux:text class="text-text-secondary">
                {{ __('Die Karte und ihr Lernfortschritt wandern in den Papierkorb.') }}
            </flux:text>
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteCard">{{ __('Karte löschen') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
