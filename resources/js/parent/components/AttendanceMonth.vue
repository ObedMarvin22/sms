<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '../api.js';
import { session } from '../session.js';
import { addMonths, marks, monthName, thisMonth } from '../format.js';
import ErrorState from './ErrorState.vue';
import Icon from './Icon.vue';
import RegisterMark from './RegisterMark.vue';

// The month as a register: school days (Monday to Friday) in weeks, each with its mark.
const props = defineProps({ childId: { type: Number, required: true } });

const current = thisMonth();
const month = ref(current);
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

/** Weeks of Monday–Friday cells; days outside the month are blank. */
const weeks = computed(() => {
    const [year, m] = month.value.split('-').map(Number);
    const last = new Date(year, m, 0).getDate();
    const byDate = Object.fromEntries((data.value?.days ?? []).map((d) => [d.date, d.status]));
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

    const rows = [];
    let week = Array(5).fill(null);
    for (let day = 1; day <= last; day++) {
        const date = new Date(year, m - 1, day);
        const weekday = (date.getDay() + 6) % 7; // Monday = 0
        if (weekday > 4) {
            continue;
        }
        if (weekday === 0 && week.some(Boolean)) {
            rows.push(week);
            week = Array(5).fill(null);
        }
        const iso = `${year}-${String(m).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        week[weekday] = { day, iso, status: byDate[iso] ?? null, future: iso > today, today: iso === today };
    }
    if (week.some(Boolean)) {
        rows.push(week);
    }

    return rows;
});

const shown = computed(() => Object.entries(data.value?.counts ?? {}).filter(([, n]) => n > 0));

onMounted(load);
watch(month, load);
watch(() => session.refreshes, load);
</script>

<template>
    <section class="rounded-2xl bg-surface p-4 ring-1 ring-line">
        <div class="flex items-center justify-between">
            <button class="press grid size-9 place-items-center rounded-lg text-muted hover:bg-paper hover:text-ink" aria-label="Previous month" @click="month = addMonths(month, -1)">
                <Icon name="left" />
            </button>
            <h2 class="font-bold">{{ monthName(month) }}</h2>
            <button
                class="press grid size-9 place-items-center rounded-lg text-muted hover:bg-paper hover:text-ink disabled:opacity-30"
                aria-label="Next month"
                :disabled="month >= current"
                @click="month = addMonths(month, 1)"
            >
                <Icon name="right" />
            </button>
        </div>

        <ErrorState v-if="error" class="mt-4" :message="error" @retry="load" />

        <template v-else>
            <table class="mt-3 w-full table-fixed border-separate border-spacing-y-1.5 text-center">
                <caption class="sr-only">Register for {{ monthName(month) }}</caption>
                <thead>
                    <tr class="text-xs text-muted">
                        <th v-for="d in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']" :key="d" scope="col" class="font-normal">{{ d }}</th>
                    </tr>
                </thead>
                <tbody v-if="data">
                    <tr v-for="(week, w) in weeks" :key="w">
                        <td v-for="(cell, c) in week" :key="c">
                            <div v-if="cell" class="flex flex-col items-center gap-0.5" :aria-current="cell.today ? 'date' : undefined">
                                <span class="text-[11px]" :class="cell.today ? 'font-bold text-ink' : 'text-faint'">{{ cell.day }}</span>
                                <span class="rounded-lg p-0.5" :class="cell.today ? 'ring-2 ring-school' : ''">
                                    <RegisterMark v-if="cell.status" :status="cell.status" size="size-8 text-sm" />
                                    <span v-else class="block size-8 rounded-md" :class="cell.future ? '' : 'bg-paper'" aria-hidden="true"></span>
                                </span>
                            </div>
                        </td>
                    </tr>
                </tbody>
                <tbody v-else aria-hidden="true">
                    <tr v-for="n in 4" :key="n"><td v-for="c in 5" :key="c"><div class="skeleton mx-auto size-8"></div></td></tr>
                </tbody>
            </table>

            <div v-if="data" class="mt-3 border-t border-line pt-3">
                <p v-if="data.days.length === 0" class="text-sm text-muted">No register was taken this month.</p>
                <ul v-else class="flex flex-wrap gap-x-4 gap-y-1.5 text-sm">
                    <li v-for="[status, n] in shown" :key="status" class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-full" :class="marks[status].dot"></span>
                        {{ marks[status].label }} <strong>{{ n }}</strong>
                    </li>
                </ul>
            </div>
        </template>
    </section>
</template>
