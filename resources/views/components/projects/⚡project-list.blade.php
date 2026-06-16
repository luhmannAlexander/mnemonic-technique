<?php

use App\Models\Project;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Lernprojekte')] class extends Component
{
    public bool $showCreateModal = false;

    public string $name = '';

    public ?string $description = null;

    public bool $showRenameModal = false;

    public ?int $renameId = null;

    public string $renameName = '';

    public bool $showDeleteModal = false;

    public ?int $deleteId = null;

    /**
     * Owned projects with document and approved-card counts.
     *
     * @return \Illuminate\Support\Collection<int, Project>
     */
    #[Computed]
    public function projects()
    {
        return Project::query()
            ->where('user_id', Auth::id())
            ->withCount([
                'documents',
                'knowledgeUnits as cards_count' => fn (Builder $q) => $q->where('unit_status', 'approved'),
            ])
            ->latest()
            ->get();
    }

    public function createProject(): mixed
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $project = Project::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return $this->redirectRoute('projects.show', $project, navigate: true);
    }

    public function startRename(int $id): void
    {
        $project = $this->ownedOrFail($id);
        $this->renameId = $project->id;
        $this->renameName = $project->name;
        $this->showRenameModal = true;
    }

    public function renameProject(): void
    {
        $validated = $this->validate([
            'renameName' => ['required', 'string', 'max:120'],
        ]);

        $this->ownedOrFail($this->renameId)->update(['name' => $validated['renameName']]);

        $this->showRenameModal = false;
        $this->reset('renameId', 'renameName');
        unset($this->projects);
        Flux::toast(text: __('Lernprojekt umbenannt.'), variant: 'success');
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $this->ownedOrFail($id)->id;
        $this->showDeleteModal = true;
    }

    public function deleteProject(): void
    {
        $this->ownedOrFail($this->deleteId)->delete();

        $this->showDeleteModal = false;
        $this->reset('deleteId');
        unset($this->projects);
        Flux::toast(text: __('Lernprojekt in den Papierkorb verschoben.'), variant: 'success');
    }

    /** Ownership is enforced as 404 (never 403) so foreign IDs do not reveal existence (AppFlow §1.3). */
    private function ownedOrFail(?int $id): Project
    {
        $project = Project::query()->where('user_id', Auth::id())->find($id);

        abort_unless($project !== null, 404);

        return $project;
    }
}; ?>

<div class="mx-auto w-full max-w-[1200px] px-4 py-6 md:px-8">
    {{-- Page header (ContentGuidelines §6.2) --}}
    <div class="mb-6 flex items-center justify-between">
        <flux:heading size="xl" level="1">{{ __('Deine Lernprojekte') }}</flux:heading>
        <flux:button variant="primary" icon="plus" wire:click="$set('showCreateModal', true)">
            {{ __('Neues Projekt') }}
        </flux:button>
    </div>

    @if ($this->projects->isEmpty())
        <div class="flex flex-col items-center gap-4 rounded-2xl bg-surface p-8 text-center shadow-md shadow-black/40">
            <flux:icon.folder class="size-6 text-text-muted" />
            <flux:text class="text-text-secondary">
                {{ __('Noch keine Lernprojekte. Lege dein erstes an, um Inhalte hochzuladen.') }}
            </flux:text>
            <flux:button variant="primary" icon="plus" wire:click="$set('showCreateModal', true)">
                {{ __('Erstes Lernprojekt anlegen') }}
            </flux:button>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->projects as $project)
                <div class="group relative flex flex-col gap-3 rounded-2xl bg-surface p-4 shadow-md shadow-black/40 transition hover:bg-surface-raised">
                    <a href="{{ route('projects.show', $project) }}" wire:navigate class="flex flex-col gap-1">
                        <flux:heading size="lg">{{ $project->name }}</flux:heading>
                        @if ($project->description)
                            <flux:text class="line-clamp-2 text-sm text-text-secondary">{{ $project->description }}</flux:text>
                        @endif
                    </a>

                    <div class="mt-auto flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" color="zinc" icon="document-text">
                                {{ trans_choice('{0}Keine Dokumente|{1}1 Dokument|[2,*]:count Dokumente', $project->documents_count, ['count' => $project->documents_count]) }}
                            </flux:badge>
                            <flux:badge size="sm" color="zinc" icon="rectangle-stack">
                                {{ trans_choice('{0}Keine Karten|{1}1 Karte|[2,*]:count Karten', $project->cards_count, ['count' => $project->cards_count]) }}
                            </flux:badge>
                        </div>

                        <div class="flex items-center gap-1 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">
                            <flux:button size="sm" variant="ghost" icon="pencil" :aria-label="__('Lernprojekt umbenennen')" wire:click="startRename({{ $project->id }})" />
                            <flux:button size="sm" variant="ghost" icon="trash" :aria-label="__('Lernprojekt löschen')" wire:click="confirmDelete({{ $project->id }})" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Create modal (ContentGuidelines §7.6) --}}
    <flux:modal wire:model.self="showCreateModal" class="md:w-96">
        <form wire:submit="createProject" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Neues Lernprojekt') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('z. B. IHK Prüfung')" />
            <flux:textarea wire:model="description" :label="__('Beschreibung (optional)')" rows="3" />
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Projekt anlegen') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Rename modal --}}
    <flux:modal wire:model.self="showRenameModal" class="md:w-96">
        <form wire:submit="renameProject" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Lernprojekt umbenennen') }}</flux:heading>
            <flux:input wire:model="renameName" :label="__('Name')" />
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Speichern') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete confirmation (ContentGuidelines §5.4) --}}
    <flux:modal wire:model.self="showDeleteModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Lernprojekt löschen?') }}</flux:heading>
            <flux:text class="text-text-secondary">
                {{ __('Das Lernprojekt und der gesamte Lernfortschritt wandern in den Papierkorb.') }}
            </flux:text>
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteProject">{{ __('Lernprojekt löschen') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
