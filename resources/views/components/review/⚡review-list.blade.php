<?php

use App\Models\KnowledgeUnit;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Review')] class extends Component
{
    public Project $project;

    // Edit/create modal. editId === null means "create a new unit".
    public bool $showEditModal = false;

    public ?int $editId = null;

    public string $editType = 'fact';

    public string $editTitle = '';

    public string $editContent = '';

    public ?string $editTopicTag = null;

    public string $editTechnique = 'spaced';

    public ?string $editTechniqueMaterial = null;

    public ?string $editSourceRef = null;

    public bool $showApproveAllModal = false;

    public bool $showDiscardModal = false;

    public ?int $discardId = null;

    public function mount(Project $project): void
    {
        // Ownership enforced as 404 (AppFlow §1.3).
        abort_unless($project->user_id === auth()->id(), 404);

        $this->project = $project;
    }

    /**
     * Draft units for this project, grouped by their source document.
     *
     * @return \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, KnowledgeUnit>>
     */
    #[Computed]
    public function groups()
    {
        return KnowledgeUnit::query()
            ->where('project_id', $this->project->id)
            ->where('unit_status', 'draft')
            ->with('document')
            ->oldest()
            ->get()
            ->groupBy(fn (KnowledgeUnit $u) => $u->document_id ?? 0);
    }

    #[Computed]
    public function draftCount(): int
    {
        return $this->groups->reduce(fn (int $carry, $group): int => $carry + $group->count(), 0);
    }

    public function approve(int $id): void
    {
        $this->ownedDraftOrFail($id)->update(['unit_status' => 'approved']);

        unset($this->groups);
        Flux::toast(text: __('Einheit bestätigt.'), variant: 'success');
    }

    public function approveAll(): void
    {
        KnowledgeUnit::query()
            ->where('project_id', $this->project->id)
            ->where('user_id', auth()->id())
            ->where('unit_status', 'draft')
            ->get()
            ->each(fn (KnowledgeUnit $unit) => $unit->update(['unit_status' => 'approved']));

        $this->showApproveAllModal = false;
        unset($this->groups);
        Flux::toast(text: __('Alle Einheiten bestätigt.'), variant: 'success');
    }

    public function startCreate(): void
    {
        $this->reset(
            'editId', 'editType', 'editTitle', 'editContent',
            'editTopicTag', 'editTechnique', 'editTechniqueMaterial', 'editSourceRef',
        );
        $this->resetValidation();
        $this->showEditModal = true;
    }

    public function startEdit(int $id): void
    {
        $unit = $this->ownedDraftOrFail($id);

        $this->editId = $unit->id;
        $this->editType = $unit->type;
        $this->editTitle = $unit->title;
        $this->editContent = $unit->content;
        $this->editTopicTag = $unit->topic_tag;
        $this->editTechnique = $unit->technique;
        $this->editTechniqueMaterial = $unit->technique_material;
        $this->editSourceRef = $unit->source_ref;
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
            'editSourceRef' => ['nullable', 'string', 'max:500'],
        ]);

        $attributes = [
            'type' => $data['editType'],
            'title' => $data['editTitle'],
            'content' => $data['editContent'],
            'topic_tag' => $data['editTopicTag'] ?: null,
            'technique' => $data['editTechnique'],
            'technique_material' => $data['editTechniqueMaterial'] ?: null,
            'source_ref' => $data['editSourceRef'] ?: null,
        ];

        if ($this->editId === null) {
            // Manually-created units are born approved (AppFlow §2.9); the observer
            // seeds the review state and queues question generation.
            KnowledgeUnit::create([
                ...$attributes,
                'project_id' => $this->project->id,
                'user_id' => auth()->id(),
                'document_id' => null,
                'unit_status' => 'approved',
            ]);

            Flux::toast(text: __('Einheit angelegt und bestätigt.'), variant: 'success');
        } else {
            $unit = $this->ownedDraftOrFail($this->editId);

            // A changed body or technique invalidates AI-generated questions, so
            // mark the edit to stop later regeneration (ImplementationPlan §2.5).
            $manuallyEdited = $unit->manually_edited
                || $unit->content !== $attributes['content']
                || $unit->technique !== $attributes['technique'];

            $unit->update([...$attributes, 'manually_edited' => $manuallyEdited]);

            Flux::toast(text: __('Einheit gespeichert.'), variant: 'success');
        }

        $this->showEditModal = false;
        unset($this->groups);
    }

    public function confirmDiscard(int $id): void
    {
        $this->discardId = $this->ownedDraftOrFail($id)->id;
        $this->showDiscardModal = true;
    }

    public function discard(): void
    {
        // Drafts were never approved, so they skip the trash entirely (AppFlow §2.9).
        $this->ownedDraftOrFail($this->discardId)->forceDelete();

        $this->showDiscardModal = false;
        $this->reset('discardId');
        unset($this->groups);
        Flux::toast(text: __('Einheit verworfen.'), variant: 'success');
    }

    /** Resolve a draft unit owned by the current user in this project, or 404. */
    private function ownedDraftOrFail(?int $id): KnowledgeUnit
    {
        $unit = KnowledgeUnit::query()
            ->where('project_id', $this->project->id)
            ->where('user_id', auth()->id())
            ->where('unit_status', 'draft')
            ->find($id);

        abort_unless($unit !== null, 404);

        return $unit;
    }
}; ?>

<div class="mx-auto w-full max-w-[1000px] px-4 py-6 md:px-8">
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item :href="route('projects.index')" wire:navigate>{{ __('Lernprojekte') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('projects.show', $project)" wire:navigate>{{ $project->name }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Review') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="mb-6 flex items-center justify-between gap-4">
        <div class="flex flex-col gap-1">
            <flux:heading size="xl" level="1">{{ __('Review') }}</flux:heading>
            <flux:text class="text-sm text-text-secondary">
                {{ trans_choice('{0}Nichts zu prüfen|{1}1 Einheit wartet auf Bestätigung|[2,*]:count Einheiten warten auf Bestätigung', $this->draftCount, ['count' => $this->draftCount]) }}
            </flux:text>
        </div>

        <div class="flex items-center gap-2">
            <flux:button variant="ghost" icon="plus" wire:click="startCreate">{{ __('Einheit manuell anlegen') }}</flux:button>
            @if ($this->draftCount > 0)
                <flux:button variant="primary" icon="check" wire:click="$set('showApproveAllModal', true)">{{ __('Alle bestätigen') }}</flux:button>
            @endif
        </div>
    </div>

    @if ($this->draftCount === 0)
        <div class="flex flex-col items-center gap-4 rounded-2xl bg-surface p-8 text-center shadow-md shadow-black/40">
            <flux:icon.check-circle class="size-8 text-success" />
            <flux:text class="text-text-secondary">{{ __('Nichts zu prüfen – alle Einheiten sind bestätigt.') }}</flux:text>
            <div class="flex gap-3">
                <flux:button variant="ghost" :href="route('projects.show', $project)" wire:navigate>{{ __('Zurück zum Projekt') }}</flux:button>
            </div>
        </div>
    @else
        <div class="flex flex-col gap-8">
            @foreach ($this->groups as $group)
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-2 text-text-secondary">
                        <flux:icon.document-text class="size-4" />
                        <flux:heading size="sm">{{ $group->first()->document?->filename ?? __('Ohne Dokument') }}</flux:heading>
                    </div>

                    @foreach ($group as $unit)
                        <div class="flex flex-col gap-3 rounded-2xl bg-surface p-4 shadow-md shadow-black/40 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex min-w-0 flex-col gap-2" x-data="{ open: false }">
                                <div class="flex items-center gap-2">
                                    <x-knowledge-type-badge :type="$unit->type" />
                                    @if ($unit->topic_tag)
                                        <flux:badge size="sm" color="zinc">{{ $unit->topic_tag }}</flux:badge>
                                    @endif
                                </div>
                                <flux:heading size="lg">{{ $unit->title }}</flux:heading>
                                <flux:text class="text-sm text-text-secondary">{{ \Illuminate\Support\Str::limit($unit->content, 240) }}</flux:text>

                                @if ($unit->technique_material)
                                    <button type="button" x-on:click="open = !open"
                                        class="flex w-fit items-center gap-1 text-xs font-medium text-accent hover:text-primary-hover">
                                        <flux:icon.light-bulb class="size-4" />
                                        <span><x-technique-name :technique="$unit->technique" /></span>
                                        <flux:icon.chevron-down class="size-3 transition" x-bind:class="open && 'rotate-180'" />
                                    </button>
                                    <flux:text x-show="open" x-collapse class="rounded-lg bg-surface-raised p-3 text-sm text-text-secondary">
                                        {{ $unit->technique_material }}
                                    </flux:text>
                                @else
                                    <flux:text class="text-xs text-text-muted"><x-technique-name :technique="$unit->technique" /></flux:text>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                <flux:button size="sm" variant="primary" icon="check" :aria-label="__('Bestätigen')" wire:click="approve({{ $unit->id }})">{{ __('Bestätigen') }}</flux:button>
                                <flux:button size="sm" variant="ghost" icon="pencil" :aria-label="__('Bearbeiten')" wire:click="startEdit({{ $unit->id }})" />
                                <flux:button size="sm" variant="ghost" icon="trash" :aria-label="__('Verwerfen')" wire:click="confirmDiscard({{ $unit->id }})" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    {{-- Edit / create modal --}}
    <flux:modal wire:model.self="showEditModal" class="md:w-[32rem]">
        <form wire:submit="saveUnit" class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editId === null ? __('Einheit manuell anlegen') : __('Einheit bearbeiten') }}</flux:heading>

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

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Speichern') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Approve-all confirmation --}}
    <flux:modal wire:model.self="showApproveAllModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Alle bestätigen?') }}</flux:heading>
            <flux:text class="text-text-secondary">
                {{ trans_choice('{1}1 Einheit wird bestätigt und in Karten umgewandelt.|[2,*]:count Einheiten werden bestätigt und in Karten umgewandelt.', $this->draftCount, ['count' => $this->draftCount]) }}
            </flux:text>
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="approveAll">{{ __('Alle bestätigen') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Discard confirmation --}}
    <flux:modal wire:model.self="showDiscardModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Verwerfen?') }}</flux:heading>
            <flux:text class="text-text-secondary">
                {{ __('Diese Einheit wird endgültig gelöscht. Da sie nie bestätigt wurde, landet sie nicht im Papierkorb.') }}
            </flux:text>
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="discard">{{ __('Verwerfen') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
