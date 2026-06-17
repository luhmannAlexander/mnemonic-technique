@props(['unit'])

{{-- Interactive learn card (ContentGuidelines §7.2). 3D Y-axis flip on click/tap/
     Enter/Space; persistent 3px left stripe in the type colour; edit pencil on
     hover/focus. The pencil's wire:click targets the surrounding CardList. --}}
@php
    $state = $unit->reviewStates->first();
    $attempts = $state?->attempt_count ?? 0;
    $due = $state?->due_at;

    if ($attempts === 0) {
        $learn = ['label' => 'Neu', 'color' => 'var(--color-info)'];
    } elseif ($due !== null && $due->lessThanOrEqualTo(now())) {
        $learn = ['label' => 'Fällig', 'color' => 'var(--color-warning)'];
    } else {
        $learn = ['label' => 'Sicher', 'color' => 'var(--color-success)'];
    }
@endphp

<div class="group relative h-[220px] [perspective:1000px]">
    {{-- Type-colour stripe: outside the rotating element so it stays on the left through the flip. --}}
    <div class="pointer-events-none absolute inset-y-0 left-0 z-10 w-[3px] rounded-l-2xl" style="background: var(--color-type-{{ $unit->type }})"></div>

    {{-- Edit pencil --}}
    <div class="absolute right-2 top-2 z-20 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">
        <flux:button size="sm" variant="ghost" icon="pencil" :aria-label="__('Karte bearbeiten')" wire:click.stop="startEdit({{ $unit->id }})" />
    </div>

    <div
        x-data="{ flipped: false }"
        @click="flipped = !flipped"
        @keydown.enter="flipped = !flipped"
        @keydown.space.prevent="flipped = !flipped"
        :aria-expanded="flipped"
        role="button"
        tabindex="0"
        :class="flipped && 'rotate-y-180'"
        class="relative h-full w-full cursor-pointer rounded-2xl outline-none transition-transform duration-[400ms] ease-in-out transform-3d focus-visible:ring-2 focus-visible:ring-primary"
    >
        {{-- Front --}}
        <div class="absolute inset-0 flex flex-col rounded-2xl bg-surface p-4 shadow-md shadow-black/40 backface-hidden transition group-hover:bg-surface-raised group-hover:shadow-lg">
            <x-knowledge-type-badge :type="$unit->type" />
            <flux:heading size="lg" class="my-auto text-center">{{ $unit->title }}</flux:heading>
            <div class="flex items-center justify-end gap-2 text-xs text-text-secondary">
                <span class="size-2 rounded-full" style="background: {{ $learn['color'] }}"></span>
                <span>{{ __($learn['label']) }}</span>
            </div>
        </div>

        {{-- Back --}}
        <div class="absolute inset-0 flex flex-col gap-2 overflow-y-auto rounded-2xl bg-surface p-4 shadow-md shadow-black/40 rotate-y-180 backface-hidden transition group-hover:bg-surface-raised group-hover:shadow-lg">
            <x-knowledge-type-badge :type="$unit->type" />
            <flux:text class="text-sm text-text">{{ $unit->content }}</flux:text>

            <div class="mt-auto flex flex-col gap-1 border-t border-border pt-2">
                <span class="text-xs font-medium text-accent"><x-technique-name :technique="$unit->technique" /></span>
                @if ($unit->technique_material)
                    <flux:text class="text-sm text-text-secondary">{{ $unit->technique_material }}</flux:text>
                @endif
                @if ($unit->source_ref)
                    <span class="text-[11px] text-text-muted">{{ $unit->source_ref }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
