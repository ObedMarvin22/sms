{{-- School name with a badge in the school's accent colour (initials until a logo is uploaded). --}}
@props(['school' => null])

@php
    $name = $school?->name ?? config('app.name');
    $initials = \Illuminate\Support\Str::of($name)->explode(' ')->filter()->take(2)
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->join('');
@endphp

<flux:sidebar.brand :name="$name" {{ $attributes }}>
    <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent text-xs font-semibold text-accent-foreground">
        <span data-test="school-initials">{{ $initials }}</span>
    </x-slot>
</flux:sidebar.brand>
