<script setup>
import { onMounted, ref, watch } from 'vue';
import { api } from '../api.js';
import { session } from '../session.js';
import { when } from '../format.js';
import ErrorNote from '../components/ErrorNote.vue';

const props = defineProps({ id: { type: Number, required: true } });

const item = ref(null);
const error = ref(null);

async function load() {
    item.value = null;
    error.value = null;
    try {
        item.value = (await api(`announcements/${props.id}`)).data;
        // Opening it marked its notice read on the server; refresh the badge.
        session.unread = (await api('notifications?per_page=1')).unread ?? session.unread;
    } catch (e) {
        error.value = e.status === 404 ? 'This announcement is no longer available.' : e.message;
    }
}

onMounted(load);
watch(() => props.id, load);
</script>

<template>
    <section class="space-y-4">
        <RouterLink :to="{ name: 'news' }" class="text-sm underline">← All news</RouterLink>

        <ErrorNote :message="error" />
        <p v-if="!item && !error" class="text-zinc-500">Loading…</p>

        <article v-if="item" class="space-y-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
            <h2 class="text-xl font-semibold">{{ item.title }}</h2>
            <p class="text-xs text-zinc-500">
                {{ when(item.sent_at) }} · {{ item.whole_school ? 'Whole school' : `For ${item.concerns.join(', ')}` }}
            </p>
            <!-- Plain text with line breaks kept; never rendered as HTML. -->
            <p class="whitespace-pre-line">{{ item.body }}</p>
        </article>
    </section>
</template>
