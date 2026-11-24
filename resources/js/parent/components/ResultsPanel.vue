<script setup>
import { onMounted, ref, watch } from 'vue';
import { api, download } from '../api.js';
import { session } from '../session.js';
import EmptyState from './EmptyState.vue';
import ErrorNote from './ErrorNote.vue';
import ErrorState from './ErrorState.vue';
import Icon from './Icon.vue';

const props = defineProps({ childId: { type: Number, required: true } });

const cards = ref(null);
const error = ref(null);
const downloading = ref(null);
const downloadError = ref(null);

async function load() {
    error.value = null;
    try {
        cards.value = (await api(`children/${props.childId}/report-cards`)).data;
    } catch (e) {
        error.value = e.message;
    }
}

async function save(card) {
    downloading.value = card.id;
    downloadError.value = null;
    try {
        await download(`children/${props.childId}/report-cards/${card.id}.pdf`, `report-card-${card.term}.pdf`);
    } catch (e) {
        downloadError.value = e.message;
    } finally {
        downloading.value = null;
    }
}

const ordinal = (n) => {
    const tens = n % 100;
    const suffix = tens >= 11 && tens <= 13 ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[n % 10] ?? 'th');
    return `${n}${suffix}`;
};
const score = (v) => (v === null ? '–' : Number(v.toFixed(1)).toString());

onMounted(load);
watch(() => session.refreshes, load);
</script>

<template>
    <div class="space-y-5">
        <ErrorState v-if="error" :message="error" @retry="load" />

        <div v-else-if="!cards" class="space-y-3 rounded-2xl bg-surface p-5 ring-1 ring-line" aria-hidden="true">
            <div class="skeleton h-3.5 w-1/3"></div><div class="skeleton h-8 w-1/2"></div><div class="skeleton h-24 w-full"></div>
        </div>

        <EmptyState
            v-else-if="cards.length === 0"
            icon="news"
            title="No report cards yet"
            text="Report cards appear here when the school publishes them at the end of each term."
        />

        <template v-else>
            <ErrorNote :message="downloadError" />

            <article v-for="card in cards" :key="card.id" class="overflow-hidden rounded-2xl bg-surface ring-1 ring-line">
                <header class="flex items-start justify-between gap-3 border-b border-line p-4">
                    <div class="min-w-0">
                        <h2 class="font-bold">{{ card.term }}, {{ card.year }}</h2>
                        <p class="text-sm text-muted">{{ card.class }}</p>
                    </div>
                    <button
                        class="press inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-bold ring-1 ring-line hover:bg-paper disabled:opacity-60"
                        :disabled="downloading === card.id"
                        @click="save(card)"
                    >
                        <Icon name="download" size="size-4" />
                        {{ downloading === card.id ? 'Saving…' : 'Report card' }}
                    </button>
                </header>

                <dl v-if="card.average !== null || card.aggregate !== null || card.position" class="grid grid-cols-3 divide-x divide-line border-b border-line text-center">
                    <div class="px-2 py-3">
                        <dt class="text-xs text-muted">Average</dt>
                        <dd class="mt-1 text-xl font-bold">{{ score(card.average) }}</dd>
                    </div>
                    <div class="px-2 py-3">
                        <dt class="text-xs text-muted">{{ card.aggregate !== null ? 'Aggregate' : 'Subjects' }}</dt>
                        <dd class="mt-1 text-xl font-bold">{{ card.aggregate ?? card.subjects.length }}</dd>
                    </div>
                    <div class="px-2 py-3">
                        <dt class="text-xs text-muted">{{ card.position ? 'Position' : 'Class size' }}</dt>
                        <dd class="mt-1 text-xl font-bold">
                            <template v-if="card.position">{{ ordinal(card.position) }}<span class="text-sm font-normal text-muted"> of {{ card.class_size }}</span></template>
                            <template v-else>{{ card.class_size }}</template>
                        </dd>
                    </div>
                </dl>

                <ul v-if="card.subjects.length" class="divide-y divide-line">
                    <li v-for="row in card.subjects" :key="row.subject" class="flex items-center gap-3 px-4 py-2.5">
                        <span class="min-w-0 flex-1 truncate text-sm">{{ row.subject }}</span>
                        <span class="text-sm tabular-nums text-muted">{{ score(row.total) }}</span>
                        <span class="grid h-7 min-w-9 place-items-center rounded-md bg-paper px-1.5 text-sm font-bold ring-1 ring-line" :title="row.meaning">{{ row.grade ?? '–' }}</span>
                    </li>
                </ul>

                <div v-if="card.teacher_remark || card.head_remark" class="space-y-3 border-t border-line p-4 text-sm">
                    <div v-if="card.teacher_remark">
                        <p class="text-xs text-muted">Class teacher</p>
                        <p class="mt-0.5">{{ card.teacher_remark }}</p>
                    </div>
                    <div v-if="card.head_remark">
                        <p class="text-xs text-muted">Head</p>
                        <p class="mt-0.5">{{ card.head_remark }}</p>
                    </div>
                </div>
            </article>
        </template>
    </div>
</template>
