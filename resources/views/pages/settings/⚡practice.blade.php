<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Übung')] class extends Component {
    public int $session_length = 10;

    public function mount(): void
    {
        $this->session_length = Auth::user()->settings?->session_length ?? 10;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'session_length' => ['required', 'integer', 'min:5', 'max:25'],
        ]);

        $user = Auth::user();
        $user->settings()->updateOrCreate(
            ['user_id' => $user->id],
            ['session_length' => $validated['session_length']],
        );

        Flux::toast(text: __('Einstellungen gespeichert.'), variant: 'success');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Übungseinstellungen') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Übung')" :subheading="__('Lege fest, wie viele Karten eine Übungssession standardmäßig enthält.')">
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:input
                type="number"
                wire:model="session_length"
                :label="__('Standard-Sessionlänge')"
                :description="__('Anzahl Karten pro Session (5–25).')"
                min="5"
                max="25"
                step="1"
                class="max-w-32"
            />

            <div class="flex items-center justify-start">
                <flux:button type="submit" variant="primary">{{ __('Speichern') }}</flux:button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
