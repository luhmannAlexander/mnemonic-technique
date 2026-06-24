<?php

use App\Models\SessionLog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ergebnis')] class extends Component
{
    public SessionLog $session;

    public function mount(SessionLog $session): void
    {
        // Ownership is a 404, never a 403 (AppFlow §2.15).
        abort_unless($session->user_id === Auth::id(), 404);

        $this->session = $session;
    }

    /**
     * The questions of this session in order, each with its attempt + unit, so
     * the list can show the result icon and topic (AppFlow §2.11). Recomputed on
     * every poll, so freshly graded free-text answers appear without a reload.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\SessionQuestion>
     */
    #[Computed]
    public function items()
    {
        return $this->session->sessionQuestions()
            ->with(['attempt', 'knowledgeUnit:id,title,topic_tag'])
            ->orderBy('position')
            ->get();
    }

    /** Live pending count drives the poll — stop polling once every grade is in. */
    #[Computed]
    public function pendingCount(): int
    {
        return (int) $this->session->fresh()->questions_pending;
    }
}; ?>

<div class="mx-auto w-full max-w-[720px] px-4 py-8 md:px-8">
    {{-- Poll only while free-text grades are still landing (AppFlow §2.11). --}}
    <div @if ($this->pendingCount > 0) wire:poll.3s @endif class="flex flex-col gap-6">
        {{-- Gesamtergebnis --}}
        <div class="flex flex-col items-center gap-3 rounded-2xl bg-surface p-8 text-center shadow-md shadow-black/40">
            <flux:heading size="xl" level="1">{{ __('Session abgeschlossen') }}</flux:heading>

            <p class="text-3xl font-bold text-success">
                {{ __(':correct von :total richtig', [
                    'correct' => $session->questions_correct,
                    'total' => $session->questions_total,
                ]) }}
            </p>

            <div class="flex flex-wrap items-center justify-center gap-2 text-sm">
                @if ($session->questions_partial > 0)
                    <flux:badge size="sm" color="amber">
                        {{ trans_choice('{1}1 teilweise|[2,*]:count teilweise', $session->questions_partial) }}
                    </flux:badge>
                @endif
                @if ($session->questions_wrong > 0)
                    <flux:badge size="sm" color="red">
                        {{ trans_choice('{1}1 falsch|[2,*]:count falsch', $session->questions_wrong) }}
                    </flux:badge>
                @endif
                @if ($this->pendingCount > 0)
                    <flux:badge size="sm" color="zinc">
                        {{ trans_choice('{1}1 Bewertung läuft …|[2,*]:count Bewertungen laufen …', $this->pendingCount) }}
                    </flux:badge>
                @endif
            </div>
        </div>

        {{-- Fragenliste mit Ergebnis-Icon + Thema --}}
        <div class="overflow-hidden rounded-2xl bg-surface shadow-md shadow-black/40">
            <ul class="divide-y divide-border">
                @foreach ($this->items as $item)
                    @php($attempt = $item->attempt)
                    @php($result = $attempt?->result)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <span class="shrink-0">
                            @switch($result)
                                @case('correct')
                                    <flux:icon.check-circle class="size-5 text-success" />
                                    @break
                                @case('partial')
                                    <flux:icon.minus-circle class="size-5 text-warning" />
                                    @break
                                @case('wrong')
                                    <flux:icon.x-circle class="size-5 text-danger" />
                                    @break
                                @default
                                    <flux:icon.clock class="size-5 text-text-muted" />
                            @endswitch
                        </span>

                        <div class="flex min-w-0 flex-1 flex-col">
                            <span class="truncate text-sm text-text">{{ $item->knowledgeUnit?->title ?? __('Wissenseinheit') }}</span>
                            @if (filled($item->knowledgeUnit?->topic_tag))
                                <span class="truncate text-xs text-text-secondary">{{ $item->knowledgeUnit->topic_tag }}</span>
                            @endif
                        </div>

                        @if ($result === 'pending' || $result === null)
                            <span class="shrink-0 text-xs text-text-muted">{{ __('wird bewertet …') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Einzige primäre Aktion: „Zum Dashboard" (AppFlow §2.11). --}}
        <div class="flex justify-center">
            <flux:button variant="primary" :href="route('dashboard')" wire:navigate>
                {{ __('Zum Dashboard') }}
            </flux:button>
        </div>
    </div>
</div>
