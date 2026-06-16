@props([
    'type' => 'fact',
])

{{-- Knowledge-type badge: colour + German label (ContentGuidelines §2.4, glossary §5.3) --}}
@php
    $map = [
        'fact'     => ['label' => 'Fakt',         'color' => 'blue'],
        'concept'  => ['label' => 'Konzept',      'color' => 'violet'],
        'relation' => ['label' => 'Zusammenhang', 'color' => 'cyan'],
        'vocab'    => ['label' => 'Vokabel',      'color' => 'pink'],
    ];
    $badge = $map[$type] ?? $map['fact'];
@endphp

<flux:badge size="sm" :color="$badge['color']">{{ __($badge['label']) }}</flux:badge>
