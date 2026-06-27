<?php

use App\Services\StatsService;
use App\Services\StreakService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Statistiken')] class extends Component
{
    /** @return list<array{date: string, total: int, correct: int, rate: float|null}> */
    #[Computed]
    public function trend(): array
    {
        return app(StatsService::class)->retentionTrend(Auth::id());
    }

    #[Computed]
    public function retention(): float
    {
        return app(StatsService::class)->currentRetention(Auth::id());
    }

    /** @return array<string, float|null> */
    #[Computed]
    public function byInterval(): array
    {
        return app(StatsService::class)->retentionByInterval(Auth::id());
    }

    #[Computed]
    public function currentStreak(): int
    {
        return app(StreakService::class)->current(Auth::id());
    }

    #[Computed]
    public function longestStreak(): int
    {
        return app(StreakService::class)->longest(Auth::id());
    }

    #[Computed]
    public function hasData(): bool
    {
        return $this->trend !== [];
    }
}; ?>

<div class="mx-auto w-full max-w-[1000px] px-4 py-6 md:px-8">
    <flux:heading size="xl" level="1" class="mb-6">{{ __('Statistiken') }}</flux:heading>

    @if (! $this->hasData)
        <div class="mx-auto flex max-w-xl flex-col items-center gap-6 rounded-2xl bg-surface p-8 text-center shadow-md shadow-black/40">
            <flux:icon.chart-bar class="size-6 text-text-muted" />
            <flux:text class="text-text-secondary">{{ __('Noch keine Daten – schließe deine erste Übungssession ab.') }}</flux:text>
            <flux:button variant="primary" icon="academic-cap" :href="route('dashboard')" wire:navigate>
                {{ __('Jetzt üben') }}
            </flux:button>
        </div>
    @else
        {{-- Kennzahlen: Quote + Streak --}}
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="flex flex-col gap-1 rounded-2xl bg-surface p-6 shadow-md shadow-black/40">
                <flux:text class="text-sm text-text-secondary">{{ __('Behaltensquote') }}</flux:text>
                <span class="text-3xl font-bold text-success">{{ $this->retention }} %</span>
            </div>
            <div class="flex flex-col gap-1 rounded-2xl bg-surface p-6 shadow-md shadow-black/40">
                <flux:text class="text-sm text-text-secondary">{{ __('Aktueller Streak') }}</flux:text>
                <span class="text-3xl font-bold">{{ trans_choice('{0}0 Tage|{1}1 Tag|[2,*]:count Tage', $this->currentStreak, ['count' => $this->currentStreak]) }}</span>
            </div>
            <div class="flex flex-col gap-1 rounded-2xl bg-surface p-6 shadow-md shadow-black/40">
                <flux:text class="text-sm text-text-secondary">{{ __('Längster Streak') }}</flux:text>
                <span class="text-3xl font-bold">{{ trans_choice('{0}0 Tage|{1}1 Tag|[2,*]:count Tage', $this->longestStreak, ['count' => $this->longestStreak]) }}</span>
            </div>
        </div>

        {{-- Behaltensquoten-Trend --}}
        <div class="mb-6 rounded-2xl bg-surface p-6 shadow-md shadow-black/40">
            <flux:heading size="lg" class="mb-4">{{ __('Behaltensquote über Zeit') }}</flux:heading>
            <x-retention-chart
                :labels="array_column($this->trend, 'date')"
                :values="array_column($this->trend, 'rate')"
            />
        </div>

        {{-- Aufschlüsselung nach Zeitabstand --}}
        <div class="rounded-2xl bg-surface p-6 shadow-md shadow-black/40">
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
    @endif
</div>
