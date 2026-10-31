<script setup>
defineProps({
    busy: { type: Boolean, default: false },
});
</script>

<template>
    <button
        class="w-full rounded-lg bg-school px-4 py-3 text-base font-semibold text-school-text shadow-sm disabled:opacity-60"
        :disabled="busy"
        :aria-busy="busy ? 'true' : undefined"
    >
        <slot />
    </button>
</template>
