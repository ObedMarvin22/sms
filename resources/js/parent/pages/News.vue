<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api.js';
import { when } from '../format.js';
import ErrorNote from '../components/ErrorNote.vue';

const items = ref(null);
const page = ref(1);
const lastPage = ref(1);
const error = ref(null);

async function load(more = false) {
    error.value = null;
    try {
        const response = await api(`announcements?page=${more ? page.value + 1 : 1}`);
        items.value = more ? [...items.value, ...response.data] : response.data;
        page.value = response.meta.current_page;
        lastPage.value = response.meta.last_page;
    } catch (e) {
        error.value = e.message;
    }
}

onMounted(() => load());
</script>

<template>
    <section class="space-y-4">
        <h2 class="text-xl font-semibold">News from the school</h2>

        <ErrorNote :message="error" />
        <p v-if="items === null && !error" class="text-zinc-500">Loading…</p>
        <p v-else-if="items?.length === 0" class="rounded-lg bg-white p-4 text-zinc-600">No announcements yet.</p>

        <ul class="space-y-2">
            <li v-for="item in items" :key="item.id">
                <RouterLink :to="{ name: 'announcement', params: { id: item.id } }" class="block rounded-xl border border-zinc-200 bg-white p-4 shadow-sm active:bg-zinc-50">
                    <p class="font-semibold">{{ item.title }}</p>
                    <p class="mt-1 line-clamp-2 text-sm text-zinc-600">{{ item.body }}</p>
                    <p class="mt-2 text-xs text-zinc-500">
                        {{ when(item.sent_at) }} ·
                        {{ item.whole_school ? 'Whole school' : `For ${item.concerns.join(', ')}` }}
                    </p>
                </RouterLink>
            </li>
        </ul>

        <button v-if="page < lastPage" class="w-full rounded-lg border border-zinc-300 bg-white py-2 text-sm" @click="load(true)">Show older</button>
    </section>
</template>
