<script setup>
import Icon from './Icon.vue';

// A form's message that is not about one field.
defineProps({
    message: { type: String, default: null },
});
</script>

<template>
    <p v-if="message" class="flex items-start gap-2 rounded-xl bg-absent-soft px-3.5 py-3 text-sm text-ink" role="alert">
        <Icon name="alert" size="size-4" class="mt-0.5 shrink-0 text-absent" />
        <span>{{ message }}</span>
    </p>
</template>
