<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '../api.js';
import { addMonths, longDate, monthName, statusLabels, statusStyles } from '../format.js';
import ErrorNote from './ErrorNote.vue';

const props = defineProps({ childId: { type: Number, required: true } });

const thisMonth = new Date().toISOString().slice(0, 7);
const month = ref(thisMonth);
const data = ref(null);
const error = ref(null);

async function load() {
    error.value = null;
    try {
        data.value = (await api(`children/${props.childId}/attendance?month=${month.value}`)).data;
    } catch (e) {
        error.value = e.message;
    }
}

onMounted(load);
watch(month, load);

const counted = computed(() => Object.entries(data.value?.counts ?? {}).filter(([, n]) => n > 0));
</script>

<template>
    <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <button class="rounded-lg px-3 py-1 text-lg" aria-label="Previous month" @click="month = addMonths(month, -1)">‹</button>
            <h3 class="font-semibold">{{ monthName(month) }}</h3>
            <button class="rounded-lg px-3 py-1 text-lg disabled:opacity-30" aria-label="Next month" :disabled="month >= thisMonth" @click="month = addMonths(month, 1)">›</button>
        </div>

        <ErrorNote :message="error" />

        <template v-if="data">
            <p v-if="data.days.length === 0" class="text-sm text-zinc-500">No register marks this month.</p>
            <div v-else class="flex flex-wrap gap-2">
                <span v-for="[status, n] in counted" :key="status" class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusStyles[status]">
                    {{ statusLabels[status] }}: {{ n }}
                </span>
            </div>

            <ul class="divide-y divide-zinc-100">
                <li v-for="day in [...data.days].reverse()" :key="day.date" class="flex items-center justify-between py-2 text-sm">
                    <span>{{ longDate(day.date) }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusStyles[day.status]">{{ statusLabels[day.status] }}</span>
                </li>
            </ul>
        </template>
    </div>
</template>
