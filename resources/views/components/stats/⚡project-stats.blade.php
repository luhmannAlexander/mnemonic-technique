<?php

use App\Models\Project;
use App\Services\StatsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Projekt-Statistiken')] class extends Component
{
    public Project $project;

    public function mount(Project $project): void
    {
        // Ownership is a 404, never a 403 (AppFlow §2.15).
        abort_unless($project->user_id === Auth::id(), 404);

        $this->project = $project;
    }

    /** @return list<array{date: string, total: int, correct: int, rate: float|null}> */
    #[Computed]
    public function trend(): array
    {
        return app(StatsService::class)->retentionTrend(Auth::id(), $this->project->id);
    }

    #[Computed]
    public function retention(): float
    {
        return app(StatsService::class)->currentRetention(Auth::id(), $this->project->id);
    }

    /** @return array<string, float|null> */
    #[Computed]
    public function byInterval(): array
    {
        return app(StatsService::class)->retentionByInterval(Auth::id(), $this->project->id);
    }

    /** @return list<array{topic_tag: string|null, total: int, correct: int, rate: float|null}> */
    #[Computed]
    public function byTopic(): array
    {
        return app(StatsService::class)->retentionByTopic(Auth::id(), $this->project->id);
    }

    #[Computed]
    public function hasData(): bool
    {
        return $this->trend !== [];
    }
}; ?>

<div class="mx-auto w-full max-w-[1000px] px-4 py-6 md:px-8">
    <div class="mb-6 flex flex-col gap-1">
        <flux:text class="text-sm text-text-secondary">{{ $project->name }}</flux:text>
        <flux:heading size="xl" level="1">{{ __('Statistiken') }}</flux:heading>
    </div>

    @if (! $this->hasData)
        <div class="mx-auto flex max-w-xl flex-col items-center gap-6 rounded-2xl bg-surface p-8 text-center shadow-md shadow-black/40">
            <flux:icon.chart-bar class="size-6 text-text-muted" />
            <flux:text class="text-text-secondary">{{ __('Noch keine Daten – schließe deine erste Übungssession ab.') }}</flux:text>
            <flux:button variant="primary" icon="academic-cap" :href="route('practice.project', $project)" wire:navigate>
                {{ __('Jetzt üben') }}
            </flux:button>
        </div>
    @else
        <div class="mb-6 grid gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-1 rounded-2xl bg-surface p-6 shadow-md shadow-black/40">
                <flux:text class="text-sm text-text-secondary">{{ __('Behaltensquote') }}</flux:text>
                <span class="text-3xl font-bold text-success">{{ $this->retention }} %</span>
            </div>
        </div>

        <div class="mb-6 rounded-2xl bg-surface p-6 shadow-md shadow-black/40">
            <flux:heading size="lg" class="mb-4">{{ __('Behaltensquote über Zeit') }}</flux:heading>
            <x-retention-chart
                :labels="array_column($this->trend, 'date')"
                :values="array_column($this->trend, 'rate')"
            />
        </div>

        <div class="mb-6 rounded-2xl bg-surface p-6 shadow-md shadow-black/40">
            <flux:heading size="lg" class="mb-4">{{ __('Nach Zeitabstand') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ($this->byInterval as $label => $rate)
                    <div class="flex flex-col gap-1">
                        <flux:text class="text-sm text-text-secondary">{{ $label }}</flux:text>
                        <span class="text-2xl font-semibold">{{ $rate === null ? '—' : $rate.' %' }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Quote je Thema/Tag (einfache Balkenliste, AppFlow §2.12) --}}
        <div class="rounded-2xl bg-surface p-6 shadow-md shadow-black/40">
            <flux:heading size="lg" class="mb-4">{{ __('Nach Thema') }}</flux:heading>
            <ul class="flex flex-col gap-3">
                @foreach ($this->byTopic as $topic)
                    <li class="flex flex-col gap-1">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-text">{{ $topic['topic_tag'] ?? __('Ohne Thema') }}</span>
                            <span class="text-text-secondary">{{ $topic['rate'] === null ? '—' : $topic['rate'].' %' }} ({{ $topic['total'] }})</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-surface-raised">
                            <div class="h-full rounded-full bg-success" style="width: {{ $topic['rate'] ?? 0 }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
