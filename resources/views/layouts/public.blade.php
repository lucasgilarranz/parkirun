<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <header class="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-5 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold" wire:navigate>
                <x-app-logo-icon class="size-8" />
                <span>ParkiRun</span>
            </a>
            @if (Route::has('login'))
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="subtle">
                        <flux:icon.bars-3 class="size-5" />
                    </flux:button>

                    <flux:menu>
                        <flux:menu.item :href="route('home')" wire:navigate>
                            Home
                        </flux:menu.item>
                        <flux:menu.item :href="route('about')" wire:navigate>
                            About
                        </flux:menu.item>
                        <flux:menu.separator />
                        @auth
                            <flux:menu.item :href="route('dashboard')" wire:navigate>
                                Dashboard
                            </flux:menu.item>
                        @else
                            <flux:menu.item :href="route('login')" wire:navigate>
                                Log in
                            </flux:menu.item>
                        @endauth
                    </flux:menu>
                </flux:dropdown>
            @endif
        </header>

        <main class="mx-auto w-full max-w-5xl px-4 pb-12 sm:px-6">
            {{ $slot }}
        </main>

        @fluxScripts
    </body>
</html>

