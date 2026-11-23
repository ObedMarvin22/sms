<script setup>
import { useId } from 'vue';

defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
    hint: { type: String, default: null },
});

const model = defineModel({ type: String, default: '' });
const id = useId();
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-sm font-bold text-ink">{{ label }}</label>
        <input
            :id="id"
            v-model="model"
            v-bind="$attrs"
            class="block w-full rounded-xl border bg-surface px-3.5 py-3 text-base text-ink placeholder:text-faint outline-none transition focus:border-school focus:ring-2 focus:ring-school/25"
            :class="error ? 'border-absent' : 'border-line'"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error || hint ? `${id}-help` : undefined"
        />
        <p v-if="error" :id="`${id}-help`" class="mt-1.5 text-sm text-absent">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-help`" class="mt-1.5 text-sm text-muted">{{ hint }}</p>
    </div>
</template>

<script>
export default { inheritAttrs: false };
</script>
