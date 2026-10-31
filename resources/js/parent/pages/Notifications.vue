<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../api.js';
import { session } from '../session.js';
import { when } from '../format.js';
import ErrorNote from '../components/ErrorNote.vue';

const router = useRouter();
const notices = ref(null);
const page = ref(1);
const lastPage = ref(1);
const error = ref(null);

async function load(more = false) {
    error.value = null;
    try {
        const response = await api(`notifications?page=${more ? page.value + 1 : 1}`);
        notices.value = more ? [...notices.value, ...response.data] : response.data;
        page.value = response.meta.current_page;
        lastPage.value = response.meta.last_page;
        session.unread = response.unread;
    } catch (e) {
        error.value = e.message;
    }
}

async function markRead(notice) {
    // An announcement's notice opens it (which marks the notice read on the server).
    if (notice.kind === 'announcement' && notice.announcement_id) {
        router.push({ name: 'announcement', params: { id: notice.announcement_id } });

        return;
    }
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
</script>

<template>
    <section class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold">Notices</h2>
            <button v-if="session.unread > 0" class="text-sm underline" @click="markAll">Mark all read</button>
        </div>

        <ErrorNote :message="error" />
        <p v-if="notices === null && !error" class="text-zinc-500">Loading…</p>
        <p v-else-if="notices?.length === 0" class="rounded-lg bg-white p-4 text-zinc-600">No notices yet.</p>

        <ul class="space-y-2">
            <li v-for="notice in notices" :key="notice.id">
                <button class="block w-full rounded-xl border bg-white p-4 text-left shadow-sm" :class="notice.read ? 'border-zinc-200' : 'border-school'" @click="markRead(notice)">
                    <p class="flex items-start gap-2">
                        <span v-if="!notice.read" class="mt-1.5 size-2 shrink-0 rounded-full bg-school" aria-label="Unread"></span>
                        <span :class="notice.read ? 'text-zinc-700' : 'font-medium'">{{ notice.message }}</span>
                    </p>
                    <p class="mt-1 text-xs text-zinc-500">{{ when(notice.created_at) }}</p>
                </button>
            </li>
        </ul>

        <button v-if="page < lastPage" class="w-full rounded-lg border border-zinc-300 bg-white py-2 text-sm" @click="load(true)">Show older</button>
    </section>
</template>
