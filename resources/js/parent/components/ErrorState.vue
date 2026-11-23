<script setup>
import Icon from './Icon.vue';

// A load that failed: what happened and a way to try again.
defineProps({
    message: { type: String, required: true },
});
defineEmits(['retry']);
</script>

<template>
    <div class="flex items-start gap-3 rounded-2xl bg-absent-soft p-4 text-sm text-ink" role="alert">
        <Icon name="alert" class="mt-0.5 shrink-0 text-absent" />
        <div class="flex-1">
            <p>{{ message }}</p>
            <button class="press mt-2 inline-flex items-center gap-1.5 font-bold text-absent" @click="$emit('retry')">
                <Icon name="refresh" size="size-4" /> Try again
            </button>
        </div>
    </div>
</template>
