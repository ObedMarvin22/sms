<script setup>
import { onMounted, ref, watch } from 'vue';
import { api } from '../api.js';
import { session } from '../session.js';
import { longDate } from '../format.js';
import EmptyState from '../components/EmptyState.vue';
import ErrorState from '../components/ErrorState.vue';
import Icon from '../components/Icon.vue';

const props = defineProps({ id: { type: Number, required: true } });

const item = ref(null);
const error = ref(null);
const gone = ref(false);

async function load() {
    item.value = null;
    error.value = null;
    gone.value = false;
    try {
        item.value = (await api(`announcements/${props.id}`)).data;
        // Opening it marked its notice read on the server; refresh the badge.
        session.unread = (await api('notifications?per_page=1')).unread ?? session.unread;
    } catch (e) {
        e.status === 404 ? (gone.value = true) : (error.value = e.message);
    }
}

onMounted(load);
watch(() => props.id, load);
</script>

<template>
    <section class="mx-auto max-w-3xl space-y-5">
        <RouterLink :to="{ name: 'news' }" class="press inline-flex items-center gap-1 text-sm font-bold text-muted hover:text-ink">
            <Icon name="back" size="size-4" /> All news
        </RouterLink>

        <EmptyState v-if="gone" icon="megaphone" title="This announcement is no longer available" text="The school may have withdrawn it." />
        <ErrorState v-else-if="error" :message="error" @retry="load" />

        <div v-else-if="!item" class="space-y-3 rounded-2xl bg-surface p-5 ring-1 ring-line" aria-hidden="true">
            <div class="skeleton h-6 w-3/4"></div><div class="skeleton h-3.5 w-1/3"></div><div class="skeleton h-3.5 w-full"></div><div class="skeleton h-3.5 w-5/6"></div>
        </div>

        <article v-else class="rounded-2xl bg-surface p-5 ring-1 ring-line">
            <h1 class="text-xl leading-snug font-bold tracking-tight">{{ item.title }}</h1>
            <p class="mt-1.5 text-sm text-muted">
                {{ longDate(item.sent_at) }}, {{ item.whole_school ? 'to the whole school' : `for ${item.concerns.join(' and ')}` }}
            </p>
            <!-- Plain text with line breaks kept; never rendered as HTML. -->
            <p class="mt-4 max-w-prose leading-relaxed whitespace-pre-line">{{ item.body }}</p>
        </article>
    </section>
</template>
