<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-bg">
        @php
            $uploadCount = \App\Models\UploadStaging::query()
                ->where('user_id', auth()->id())
                ->whereNull('confirmed_at')
                ->where('expires_at', '>', now())
                ->count();
        @endphp

        <flux:sidebar sticky collapsible="mobile" class="border-e border-border bg-surface">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="arrow-up-tray" :href="route('upload.index')" :current="request()->routeIs('upload.*')" :badge="$uploadCount ?: null" wire:navigate>
                    {{ __('Upload') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="folder" :href="route('projects.index')" :current="request()->routeIs('projects.*') || request()->routeIs('documents.*')" wire:navigate>
                    {{ __('Lernprojekte') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="chart-bar" :href="route('stats.index')" :current="request()->routeIs('stats.*')" wire:navigate>
                    {{ __('Statistiken') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="trash" :href="route('trash.index')" :current="request()->routeIs('trash.*')" wire:navigate>
                    {{ __('Papierkorb') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.*') || request()->routeIs('security.*') || request()->routeIs('appearance.*')" wire:navigate>
                    {{ __('Einstellungen') }}
                </flux:sidebar.item>

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:sidebar.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Logout') }}
                    </flux:sidebar.item>
                </form>
            </flux:sidebar.nav>

            {{-- User email display only — no menu needed in v1 (AppFlow §1.1) --}}
            <div class="truncate px-3 py-2 text-sm text-text-secondary" title="{{ auth()->user()->email }}">
                {{ auth()->user()->email }}
            </div>
        </flux:sidebar>

        <!-- Mobile header -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>
                            {{ __('Einstellungen') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Logout') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
