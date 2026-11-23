<script setup>
import Icon from './Icon.vue';

defineProps({
    icon: { type: String, default: 'news' },
    title: { type: String, required: true },
    text: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col items-center px-6 py-12 text-center">
        <span class="mb-4 grid size-12 place-items-center rounded-full bg-surface text-faint ring-1 ring-line">
            <Icon :name="icon" size="size-6" />
        </span>
        <p class="font-bold text-ink">{{ title }}</p>
        <p v-if="text" class="mt-1 max-w-xs text-sm text-muted">{{ text }}</p>
        <div class="mt-4"><slot /></div>
    </div>
</template>
