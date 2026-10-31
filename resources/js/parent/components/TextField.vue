<script setup>
defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
    hint: { type: String, default: null },
});

const model = defineModel({ type: String, default: '' });
</script>

<template>
    <label class="block">
        <span class="mb-1 block text-sm font-medium text-zinc-700">{{ label }}</span>
        <input
            v-model="model"
            v-bind="$attrs"
            class="block w-full rounded-lg border bg-white px-3 py-2.5 text-base outline-none focus:ring-2 focus:ring-school"
            :class="error ? 'border-red-500' : 'border-zinc-300'"
            :aria-invalid="error ? 'true' : undefined"
        />
        <span v-if="error" class="mt-1 block text-sm text-red-600">{{ error }}</span>
        <span v-else-if="hint" class="mt-1 block text-sm text-zinc-500">{{ hint }}</span>
    </label>
</template>

<script>
export default { inheritAttrs: false };
</script>
