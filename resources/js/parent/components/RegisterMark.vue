<script setup>
import { marks } from '../format.js';

// One day in the class register: a letter, as teachers write it, in the mark's colour.
defineProps({
    status: { type: String, default: null },
    size: { type: String, default: 'size-7 text-xs' },
});
</script>

<template>
    <span
        v-if="status && marks[status]"
        :class="[size, marks[status].chip]"
        class="inline-grid shrink-0 place-items-center rounded-md font-bold"
        :title="marks[status].label"
    >
        {{ marks[status].letter }}<span class="sr-only"> {{ marks[status].label }}</span>
    </span>
    <span v-else :class="size" class="inline-grid shrink-0 place-items-center rounded-md border border-dashed border-line text-faint" title="Not taken">
        <span aria-hidden="true">·</span><span class="sr-only">Register not taken</span>
    </span>
</template>
