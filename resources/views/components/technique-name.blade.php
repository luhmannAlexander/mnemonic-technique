@props(['technique' => 'spaced'])
{{-- Exact German technique names (ContentGuidelines §5.3 glossary). Outputs plain text. --}}
@php
    $map = [
        'spaced' => 'Spaced Repetition',
        'acronym' => 'Eselsbrücke',
        'story' => 'Geschichten-Methode',
        'loci' => 'Loci-Methode',
        'major' => 'Major-System',
    ];
@endphp{{ $map[$technique] ?? $map['spaced'] }}
