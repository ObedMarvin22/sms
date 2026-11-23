<script setup>
import { computed } from 'vue';
import { session } from '../session.js';

// The school's mark and a short heading, for the sign-in screens.
defineProps({
    title: { type: String, required: true },
    text: { type: String, default: '' },
});

const initials = computed(() =>
    session.school.name
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word[0] ?? '')
        .join('')
        .toUpperCase(),
);
</script>

<template>
    <div class="flex flex-col items-center pt-4 pb-6 text-center">
        <img v-if="session.school.logo" :src="session.school.logo" alt="" class="size-16 rounded-2xl bg-surface object-contain ring-1 ring-line" />
        <span v-else class="grid size-16 place-items-center rounded-2xl bg-school text-2xl font-bold text-school-text" aria-hidden="true">{{ initials }}</span>
        <h1 class="mt-5 text-2xl font-bold tracking-tight">{{ title }}</h1>
        <p v-if="text" class="mt-1.5 max-w-xs text-muted">{{ text }}</p>
    </div>
</template>
