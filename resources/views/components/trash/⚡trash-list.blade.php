<?php

use App\Models\Document;
use App\Models\KnowledgeUnit;
use App\Models\Project;
use App\Services\TrashService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Papierkorb')] class extends Component
{
    public bool $showForceModal = false;

    public ?string $forceType = null;

    public ?int $forceId = null;

    /**
     * Unified list of trashed projects, documents and cards owned by the user.
     *
     * @return \Illuminate\Support\Collection<int, array{type:string, type_label:string, id:int, name:string, origin:string, deleted_at:\Illuminate\Support\Carbon}>
     */
    #[Computed]
    public function items()
    {
        $projects = Project::onlyTrashed()->where('user_id', Auth::id())->get()
            ->map(fn (Project $p): array => [
                'type' => 'project',
                'type_label' => __('Lernprojekt'),
                'id' => $p->id,
                'name' => $p->name,
                'origin' => '—',
                'deleted_at' => $p->deleted_at,
            ]);

        $documents = Document::onlyTrashed()->where('user_id', Auth::id())->get()
            ->map(fn (Document $d): array => [
                'type' => 'document',
                'type_label' => __('Dokument'),
                'id' => $d->id,
                'name' => $d->filename,
                'origin' => Project::withTrashed()->find($d->project_id)?->name ?? '—',
                'deleted_at' => $d->deleted_at,
            ]);

        $units = KnowledgeUnit::onlyTrashed()->where('user_id', Auth::id())->get()
            ->map(fn (KnowledgeUnit $u): array => [
                'type' => 'knowledge_unit',
                'type_label' => __('Karte'),
                'id' => $u->id,
                'name' => $u->title,
                'origin' => Project::withTrashed()->find($u->project_id)?->name ?? '—',
                'deleted_at' => $u->deleted_at,
            ]);

        return $projects->concat($documents)->concat($units)
            ->sortByDesc('deleted_at')->values();
    }

    public function restore(string $type, int $id): void
    {
        app(TrashService::class)->restore($type, $id, (int) Auth::id());

        unset($this->items);
        Flux::toast(text: __('Wiederhergestellt.'), variant: 'success');
    }

    public function confirmForceDelete(string $type, int $id): void
    {
        $this->forceType = $type;
        $this->forceId = $id;
        $this->showForceModal = true;
    }

    public function forceDelete(): void
    {
        app(TrashService::class)->forceDelete($this->forceType, (int) $this->forceId, (int) Auth::id());

        $this->showForceModal = false;
        $this->reset('forceType', 'forceId');
        unset($this->items);
        Flux::toast(text: __('Endgültig gelöscht.'), variant: 'success');
    }
}; ?>

<div class="mx-auto w-full max-w-[1200px] px-4 py-6 md:px-8">
    <flux:heading size="xl" level="1" class="mb-2">{{ __('Papierkorb') }}</flux:heading>
    <flux:text class="mb-6 text-sm text-text-secondary">{{ __('Elemente werden nach 30 Tagen automatisch gelöscht.') }}</flux:text>

    @if ($this->items->isEmpty())
        <div class="flex flex-col items-center gap-4 rounded-2xl bg-surface p-8 text-center shadow-md shadow-black/40">
            <flux:icon.trash class="size-6 text-text-muted" />
            <flux:text class="text-text-secondary">{{ __('Der Papierkorb ist leer.') }}</flux:text>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl bg-surface shadow-md shadow-black/40">
            @foreach ($this->items as $item)
                <div class="flex h-12 items-center gap-4 border-b border-border px-4 last:border-b-0" wire:key="{{ $item['type'] }}-{{ $item['id'] }}">
                    <flux:badge size="sm" color="zinc">{{ $item['type_label'] }}</flux:badge>
                    <span class="min-w-0 flex-1 truncate text-text">{{ $item['name'] }}</span>
                    <span class="hidden w-40 shrink-0 truncate text-sm text-text-secondary md:block">{{ $item['origin'] }}</span>
                    <span class="hidden w-24 shrink-0 text-sm text-text-secondary sm:block">{{ $item['deleted_at']?->format('d.m.Y') }}</span>
                    <div class="flex items-center gap-1">
                        <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" :aria-label="__('Wiederherstellen')" wire:click="restore('{{ $item['type'] }}', {{ $item['id'] }})" />
                        <flux:button size="sm" variant="ghost" icon="trash" :aria-label="__('Endgültig löschen')" wire:click="confirmForceDelete('{{ $item['type'] }}', {{ $item['id'] }})" />
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Permanent-delete confirmation (ContentGuidelines §5.4) --}}
    <flux:modal wire:model.self="showForceModal" class="md:w-96">
        <div class="flex flex-col gap-6">
            <flux:heading size="lg">{{ __('Endgültig löschen?') }}</flux:heading>
            <flux:text class="text-text-secondary">{{ __('Das kann nicht rückgängig gemacht werden.') }}</flux:text>
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="forceDelete">{{ __('Endgültig löschen') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
