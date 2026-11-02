<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        {{-- Unpaid Orange Tree invoice (App\Support\Saas\SaasLock, shared by EnforceSaasReadOnly). --}}
        @if (isset($saasLock) && $saasLock->status !== \App\Support\Saas\SaasLock::OPEN)
            @include('partials.saas-lock-banner', ['lock' => $saasLock])
        @endif
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
