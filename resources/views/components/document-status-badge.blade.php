@props([
    'status' => 'pending',
])

{{-- Document extraction status badge (ContentGuidelines §7.4) --}}
@php
    $map = [
        'pending'    => ['label' => 'Wartet',          'color' => 'zinc',  'icon' => 'clock'],
        'processing' => ['label' => 'Wird analysiert', 'color' => 'blue',  'icon' => null],
        'done'       => ['label' => 'Fertig',          'color' => 'green', 'icon' => 'check'],
        'error'      => ['label' => 'Fehler',          'color' => 'red',   'icon' => 'x-mark'],
    ];
    $badge = $map[$status] ?? $map['pending'];
@endphp

<flux:badge size="sm" :color="$badge['color']" :icon="$badge['icon']">
    @if ($status === 'processing')
        {{-- Pulsing dot for the active state (ContentGuidelines §7.4, §9.1) --}}
        <span class="me-1.5 inline-block size-2 animate-pulse rounded-full bg-info"></span>
    @endif
    {{ __($badge['label']) }}
</flux:badge>
