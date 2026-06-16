@props([
    'sidebar' => false,
])

{{-- Text wordmark „Mnemonic" with a violet square (ContentGuidelines §11) --}}
@if($sidebar)
    <flux:sidebar.brand name="Mnemonic" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-primary">
            <span class="size-2 rounded-sm bg-bg"></span>
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Mnemonic" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-primary">
            <span class="size-2 rounded-sm bg-bg"></span>
        </x-slot>
    </flux:brand>
@endif
