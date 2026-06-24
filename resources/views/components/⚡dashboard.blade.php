<?php

use App\Models\KnowledgeUnit;
use App\Models\Project;
use App\Models\ReviewState;
use App\Models\SessionLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    /**
     * Owned projects with their approved-card count.
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

    /** Cards due today across all projects (approved, non-deleted units only). */
    #[Computed]
    public function dueCount(): int
    {
        return ReviewState::query()
            ->where('review_states.user_id', Auth::id())
            ->where('review_states.due_at', '<=', now())
            ->join('knowledge_units', 'knowledge_units.id', '=', 'review_states.knowledge_unit_id')
            ->whereNull('knowledge_units.deleted_at')
            ->where('knowledge_units.unit_status', 'approved')
            ->count();
    }

    /** Total approved cards — distinguishes "nothing due" from "nothing to learn yet". */
    #[Computed]
    public function approvedCardCount(): int
    {
        return KnowledgeUnit::where('user_id', Auth::id())
            ->where('unit_status', 'approved')
            ->count();
    }

    /** The open interrupted session to resume, if any (AppFlow §2.2). */
    #[Computed]
    public function interruptedSession(): ?SessionLog
    {
        return SessionLog::where('user_id', Auth::id())
            ->where('status', 'interrupted')
            ->latest('updated_at')
            ->first();
    }

    /** "Neu starten": abandon the interrupted session, then start a fresh one for its slot. */
    public function restart(): void
    {
        $session = $this->interruptedSession;

        if ($session === null) {
            return;
        }

        $session->update(['status' => 'abandoned']);

        $this->redirect(
            $session->project_id === null
                ? route('practice.today')
                : route('practice.project', $session->project_id),
            navigate: true,
        );
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

        {{-- „Heute fällig"-Karte / Fortsetzen-Karte (AppFlow §2.2) --}}
        <div class="mb-6 flex flex-col gap-4 rounded-2xl bg-surface p-6 shadow-md shadow-black/40 sm:flex-row sm:items-center sm:justify-between">
            @if ($this->interruptedSession)
                {{-- Vorrang: unterbrochene Session fortsetzen --}}
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('Session fortsetzen') }}</flux:heading>
                    <flux:text class="text-text-secondary">
                        {{ __('Frage :current von :total', [
                            'current' => min($this->interruptedSession->current_question_index + 1, $this->interruptedSession->questions_total),
                            'total' => $this->interruptedSession->questions_total,
                        ]) }}
                    </flux:text>
                </div>
                <div class="flex items-center gap-3">
                    <flux:button variant="ghost" wire:click="restart">{{ __('Neu starten') }}</flux:button>
                    <flux:button variant="primary" icon="play" :href="route('practice.session', $this->interruptedSession)" wire:navigate>
                        {{ __('Fortsetzen') }}
                    </flux:button>
                </div>
            @elseif ($this->dueCount > 0)
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">
                        {{ trans_choice('{1}Heute fällig: 1 Karte|[2,*]Heute fällig: :count Karten', $this->dueCount, ['count' => $this->dueCount]) }}
                    </flux:heading>
                    <flux:text class="text-text-secondary">{{ __('Bleib dran und halte deine Karten frisch.') }}</flux:text>
                </div>
                <flux:button variant="primary" icon="academic-cap" :href="route('practice.today')" wire:navigate>
                    {{ __('Jetzt üben') }}
                </flux:button>
            @elseif ($this->approvedCardCount > 0)
                {{-- Heute nichts fällig --}}
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('Du hast für heute alles erledigt 🎉') }}</flux:heading>
                    <flux:text class="text-text-secondary">{{ __('Möchtest du trotzdem ein paar Karten wiederholen?') }}</flux:text>
                </div>
                <flux:button variant="ghost" icon="academic-cap" :href="route('practice.today', ['type' => 'voluntary'])" wire:navigate>
                    {{ __('Trotzdem üben') }}
                </flux:button>
            @else
                {{-- Projekte vorhanden, aber keine bestätigten Karten --}}
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('Noch keine Karten zum Üben') }}</flux:heading>
                    <flux:text class="text-text-secondary">
                        {{ __('Lade Inhalte hoch und bestätige die extrahierten Einheiten, um loszulegen.') }}
                    </flux:text>
                </div>
                <flux:button variant="ghost" icon="arrow-up-tray" :href="route('upload.index')" wire:navigate>
                    {{ __('Inhalte hochladen') }}
                </flux:button>
            @endif
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
