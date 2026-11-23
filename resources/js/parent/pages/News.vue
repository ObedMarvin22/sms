<script setup>
import { onMounted, ref, watch } from 'vue';
import { api } from '../api.js';
import { session } from '../session.js';
import EmptyState from '../components/EmptyState.vue';
import ErrorState from '../components/ErrorState.vue';
import Icon from '../components/Icon.vue';

const items = ref(null);
const page = ref(1);
const lastPage = ref(1);
const loadingMore = ref(false);
const error = ref(null);

async function load(more = false) {
    error.value = null;
    loadingMore.value = more;
    try {
        const response = await api(`announcements?page=${more ? page.value + 1 : 1}`);
        items.value = more ? [...items.value, ...response.data] : response.data;
        page.value = response.meta.current_page;
        lastPage.value = response.meta.last_page;
    } catch (e) {
        error.value = e.message;
    } finally {
        loadingMore.value = false;
    }
}

const day = (iso) => new Date(iso).toLocaleDateString('en-GB', { day: 'numeric' });
const month = (iso) => new Date(iso).toLocaleDateString('en-GB', { month: 'short' });

onMounted(() => load());
watch(() => session.refreshes, () => load());
</script>

<template>
    <section class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-bold tracking-tight">News from the school</h1>
        <p class="mt-1 text-muted">Announcements for the whole school and for your children's classes.</p>

        <ErrorState v-if="error" class="mt-6" :message="error" @retry="load()" />

        <div v-else-if="items === null" class="mt-6 divide-y divide-line overflow-hidden rounded-2xl bg-surface ring-1 ring-line" aria-hidden="true">
            <div v-for="n in 3" :key="n" class="flex gap-4 p-4"><div class="skeleton h-12 w-11"></div><div class="flex-1 space-y-2"><div class="skeleton h-4 w-3/5"></div><div class="skeleton h-3 w-full"></div></div></div>
        </div>

        <EmptyState v-else-if="items.length === 0" class="mt-6" icon="megaphone" title="No announcements yet" text="When the school sends news, it appears here and you get a notice." />

        <ul v-else class="mt-6 divide-y divide-line overflow-hidden rounded-2xl bg-surface ring-1 ring-line">
            <li v-for="item in items" :key="item.id">
                <RouterLink :to="{ name: 'announcement', params: { id: item.id } }" class="press flex gap-4 p-4 hover:bg-paper/60">
                    <!-- The date as a small calendar leaf: when it was sent is the first thing parents scan for. -->
                    <span class="flex w-11 shrink-0 flex-col items-center overflow-hidden rounded-lg ring-1 ring-line">
                        <span class="w-full bg-school py-0.5 text-center text-[11px] font-bold text-school-text">{{ month(item.sent_at) }}</span>
                        <span class="py-1 text-lg leading-tight font-bold">{{ day(item.sent_at) }}</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-bold">{{ item.title }}</span>
                        <span class="mt-0.5 line-clamp-2 block text-sm text-muted">{{ item.body }}</span>
                        <span class="mt-1.5 inline-block rounded-md bg-paper px-2 py-0.5 text-xs text-muted">
                            {{ item.whole_school ? 'Whole school' : `For ${item.concerns.join(' and ')}` }}
                        </span>
                    </span>
                    <Icon name="chevron" size="size-4" class="mt-1 shrink-0 text-faint" />
                </RouterLink>
            </li>
        </ul>

        <button v-if="items && page < lastPage" class="press mt-4 w-full rounded-xl bg-surface py-3 text-sm font-bold ring-1 ring-line disabled:opacity-60" :disabled="loadingMore" @click="load(true)">
            {{ loadingMore ? 'Loading…' : 'Show older news' }}
        </button>
    </section>
</template>
