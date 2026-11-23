<script setup>
import { computed } from 'vue';

// A child's initials in the school's colour, like a badge on a school ID card.
const props = defineProps({
    name: { type: String, required: true },
    size: { type: String, default: 'size-11 text-base' },
});

const initials = computed(() => {
    const parts = props.name.trim().split(/\s+/);

    return ((parts[0]?.[0] ?? '') + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
});
</script>

<template>
    <span :class="props.size" class="grid shrink-0 place-items-center rounded-full bg-school font-bold text-school-text" aria-hidden="true">
        {{ initials }}
    </span>
</template>
