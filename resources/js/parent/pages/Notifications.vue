<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api.js';
import { session } from '../session.js';
import { when } from '../format.js';
import EmptyState from '../components/EmptyState.vue';
import ErrorState from '../components/ErrorState.vue';
import Icon from '../components/Icon.vue';

const router = useRouter();
const notices = ref(null);
const page = ref(1);
const lastPage = ref(1);
const loadingMore = ref(false);
const error = ref(null);

const kinds = {
    absence: { icon: 'calendar', tone: 'bg-absent-soft text-absent' },
    payment: { icon: 'check', tone: 'bg-present-soft text-present' },
    announcement: { icon: 'megaphone', tone: 'bg-paper text-ink' },
    notice: { icon: 'bell', tone: 'bg-paper text-ink' },
};

async function load(more = false) {
    error.value = null;
    loadingMore.value = more;
    try {
        const response = await api(`notifications?page=${more ? page.value + 1 : 1}`);
        notices.value = more ? [...notices.value, ...response.data] : response.data;
        page.value = response.meta.current_page;
        lastPage.value = response.meta.last_page;
        session.unread = response.unread;
    } catch (e) {
        error.value = e.message;
    } finally {
        loadingMore.value = false;
    }
}

async function open(notice) {
    // An announcement's notice opens it (which marks the notice read on the server).
    if (notice.kind === 'announcement' && notice.announcement_id) {
        router.push({ name: 'announcement', params: { id: notice.announcement_id } });

        return;
    }
    if (notice.kind === 'report_card' && notice.student_id) {
        router.push({ name: 'child', params: { id: notice.student_id }, query: { tab: 'results' } });
        return;
    }
    if (notice.kind === 'absence' && notice.student_id) {
        markRead(notice);
        router.push({ name: 'child', params: { id: notice.student_id } });

        return;
    }
    markRead(notice);
}

async function markRead(notice) {
    if (notice.read) {
        return;
    }
    notice.read = true;
    session.unread = Math.max(0, session.unread - 1);
    try {
        await api(`notifications/${notice.id}/read`, { method: 'POST' });
    } catch {
        notice.read = false;
        session.unread += 1;
    }
}

async function markAll() {
    await api('notifications/read-all', { method: 'POST' });
    notices.value.forEach((notice) => (notice.read = true));
    session.unread = 0;
}

onMounted(() => load());
watch(() => session.refreshes, () => load());
</script>

<template>
    <section class="mx-auto max-w-3xl">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Notices</h1>
                <p class="mt-1 text-muted">{{ session.unread > 0 ? `${session.unread} unread` : 'You are up to date.' }}</p>
            </div>
            <button v-if="session.unread > 0" class="press rounded-lg px-3 py-2 text-sm font-bold ring-1 ring-line hover:bg-surface" @click="markAll">Mark all read</button>
        </div>

        <ErrorState v-if="error" class="mt-6" :message="error" @retry="load()" />

        <div v-else-if="notices === null" class="mt-6 divide-y divide-line overflow-hidden rounded-2xl bg-surface ring-1 ring-line" aria-hidden="true">
            <div v-for="n in 4" :key="n" class="flex gap-3 p-4"><div class="skeleton size-9 rounded-full"></div><div class="flex-1 space-y-2"><div class="skeleton h-3.5 w-4/5"></div><div class="skeleton h-3 w-1/5"></div></div></div>
        </div>

        <EmptyState v-else-if="notices.length === 0" class="mt-6" icon="bell" title="No notices yet" text="Absences, payments and school news will appear here." />

        <ul v-else class="mt-6 divide-y divide-line overflow-hidden rounded-2xl bg-surface ring-1 ring-line">
            <li v-for="notice in notices" :key="notice.id">
                <button class="press flex w-full gap-3 p-4 text-left hover:bg-paper/60" :class="notice.read ? '' : 'bg-school/[0.04]'" @click="open(notice)">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full" :class="kinds[notice.kind]?.tone ?? kinds.notice.tone">
                        <Icon :name="kinds[notice.kind]?.icon ?? 'bell'" size="size-4.5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block" :class="notice.read ? 'text-muted' : 'font-bold text-ink'">{{ notice.message }}</span>
                        <span class="mt-0.5 block text-xs text-faint">{{ when(notice.created_at) }}</span>
                    </span>
                    <span v-if="!notice.read" class="mt-2 size-2.5 shrink-0 rounded-full bg-school"><span class="sr-only">Unread</span></span>
                </button>
            </li>
        </ul>

        <button v-if="notices && page < lastPage" class="press mt-4 w-full rounded-xl bg-surface py-3 text-sm font-bold ring-1 ring-line disabled:opacity-60" :disabled="loadingMore" @click="load(true)">
            {{ loadingMore ? 'Loading…' : 'Show older notices' }}
        </button>
    </section>
</template>
