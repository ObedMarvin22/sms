<script setup>
defineProps({
    busy: { type: Boolean, default: false },
    quiet: { type: Boolean, default: false },
});
</script>

<template>
    <button
        class="press inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3.5 text-base font-bold disabled:opacity-60"
        :class="quiet ? 'bg-surface text-ink ring-1 ring-line' : 'bg-school text-school-text shadow-[0_1px_0_rgba(0,0,0,0.08)]'"
        :disabled="busy"
        :aria-busy="busy ? 'true' : undefined"
    >
        <svg v-if="busy" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.3" stroke-width="3" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
        <slot />
    </button>
</template>
