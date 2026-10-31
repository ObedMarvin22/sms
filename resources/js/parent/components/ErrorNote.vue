<script setup>
defineProps({
    message: { type: String, default: null },
});
</script>

<template>
    <p v-if="message" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ message }}</p>
</template>
