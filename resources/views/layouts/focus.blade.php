<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    {{-- Fokusmodus: keine Sidebar, kein Header — ruhigerer, dunklerer Hintergrund (ContentGuidelines §8). --}}
    <body class="min-h-screen bg-bg-focus text-text antialiased">
        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
