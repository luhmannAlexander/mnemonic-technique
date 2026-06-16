<?php

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    /**
     * Owned projects with their approved-card count.
     * Milestone 1 has no AI data yet, so streak/trend/„Heute fällig" stay hidden.
     *
     * @return \Illuminate\Support\Collection<int, Project>
     */
    #[Computed]
    public function projects()
    {
        return Project::query()
            ->where('user_id', Auth::id())
            ->withCount([
                'knowledgeUnits as approved_cards_count' => fn (Builder $q) => $q->where('unit_status', 'approved'),
            ])
            ->latest()
            ->get();
    }
}; ?>

<div class="mx-auto w-full max-w-[1200px] px-4 py-6 md:px-8">
    @if ($this->projects->isEmpty())
        {{-- Empty state — first login (ContentGuidelines §5.4) --}}
        <div class="mx-auto flex max-w-xl flex-col items-center gap-6 rounded-2xl bg-surface p-8 text-center shadow-md shadow-black/40">
            <flux:icon.folder class="size-6 text-text-muted" />
            <div class="flex flex-col gap-2">
                <flux:heading size="xl">{{ __('Willkommen bei Mnemonic') }}</flux:heading>
                <flux:text class="text-text-secondary">
                    {{ __('Lade deine Lerninhalte als Markdown hoch – die KI macht daraus Karten, die du mit bewährten Gedächtnistechniken übst.') }}
                </flux:text>
            </div>
            <flux:button variant="primary" icon="folder" :href="route('projects.index')" wire:navigate>
                {{ __('Erstes Lernprojekt anlegen') }}
            </flux:button>
        </div>
    @else
        <div class="mb-6 flex items-center justify-between">
            <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
            <div class="flex items-center gap-3">
                <flux:button variant="ghost" icon="arrow-up-tray" :href="route('upload.index')" wire:navigate>
                    {{ __('Inhalte hochladen') }}
                </flux:button>
                <flux:button variant="primary" icon="folder" :href="route('projects.index')" wire:navigate>
                    {{ __('Neues Projekt') }}
                </flux:button>
            </div>
        </div>

        {{-- Lernprojekte (ContentGuidelines §5.3 glossary) --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->projects as $project)
                <a
                    href="{{ route('projects.show', $project) }}"
                    wire:navigate
                    class="flex flex-col gap-3 rounded-2xl bg-surface p-4 shadow-md shadow-black/40 transition hover:bg-surface-raised hover:shadow-lg hover:shadow-black/50"
                >
                    <flux:heading size="lg">{{ $project->name }}</flux:heading>
                    @if ($project->description)
                        <flux:text class="line-clamp-2 text-sm text-text-secondary">{{ $project->description }}</flux:text>
                    @endif
                    <div class="mt-auto">
                        <flux:badge size="sm" color="zinc" icon="rectangle-stack">
                            {{ trans_choice('{0}Keine Karten|{1}1 Karte|[2,*]:count Karten', $project->approved_cards_count, ['count' => $project->approved_cards_count]) }}
                        </flux:badge>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
