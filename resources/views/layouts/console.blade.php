{{-- Orange Tree console (admin subdomain). Separate from the school staff layout, whose links need a school. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <span class="font-semibold">{{ __('Orange Tree console') }}</span>

            <flux:navbar class="-mb-px ms-6">
                <flux:navbar.item icon="building-library" :href="route('console.schools.index')" :current="request()->routeIs('console.schools.*')" wire:navigate>
                    {{ __('Schools') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <span class="me-4 text-sm text-zinc-500">{{ auth('platform')->user()?->name }}</span>
            <form method="POST" action="{{ route('console.logout') }}">
                @csrf
                <flux:button type="submit" size="sm" variant="ghost" data-test="console-logout-button">{{ __('Log out') }}</flux:button>
            </form>
        </flux:header>

        <flux:main container>
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
