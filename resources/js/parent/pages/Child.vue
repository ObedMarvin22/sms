<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api.js';
import { session } from '../session.js';
import { marks, thisMonth } from '../format.js';
import AttendanceMonth from '../components/AttendanceMonth.vue';
import Crest from '../components/Crest.vue';
import ErrorState from '../components/ErrorState.vue';
import FeesPanel from '../components/FeesPanel.vue';
import Icon from '../components/Icon.vue';
import RegisterMark from '../components/RegisterMark.vue';
import ResultsPanel from '../components/ResultsPanel.vue';

const props = defineProps({ id: { type: Number, required: true } });

const child = ref(null);
const month = ref(null); // this month's register summary, for the card
const error = ref(null);
const route = useRoute();
// A report-card notice opens the Results tab (?tab=results).
const tab = ref(route.query.tab === 'results' ? 'results' : 'attendance');

async function load() {
    error.value = null;
    try {
        const [found, register] = await Promise.all([api(`children/${props.id}`), api(`children/${props.id}/attendance?month=${thisMonth()}`)]);
        child.value = found.data;
        month.value = register.data;
    } catch (e) {
        error.value = e.status === 404 ? 'This child is not on your account.' : e.message;
    }
}

const daysTaken = computed(() => month.value?.days.length ?? 0);
const daysIn = computed(() => (month.value?.counts.present ?? 0) + (month.value?.counts.late ?? 0));

onMounted(load);
watch(() => props.id, () => {
    child.value = null;
    tab.value = 'attendance';
    load();
});
watch(() => session.refreshes, load);
</script>

<template>
    <section class="space-y-5">
        <RouterLink :to="{ name: 'children' }" class="press inline-flex items-center gap-1 text-sm font-bold text-muted hover:text-ink">
            <Icon name="back" size="size-4" /> All children
        </RouterLink>

        <ErrorState v-if="error" :message="error" @retry="load" />

        <div v-else-if="!child" class="overflow-hidden rounded-2xl bg-surface ring-1 ring-line" aria-hidden="true">
            <div class="h-14 bg-school/30"></div>
            <div class="space-y-2 p-4 pt-10"><div class="skeleton h-5 w-1/2"></div><div class="skeleton h-3.5 w-1/3"></div></div>
            <div class="grid grid-cols-3 border-t border-line"><div v-for="n in 3" :key="n" class="p-4"><div class="skeleton h-8"></div></div></div>
        </div>

        <!-- Phones: one column. Wide screens: the ID card stays in view beside the details. -->
        <div v-else class="space-y-5 lg:grid lg:grid-cols-[340px_minmax(0,1fr)] lg:items-start lg:gap-6 lg:space-y-0">
            <!-- The child's school ID card. -->
            <article class="overflow-hidden rounded-2xl bg-surface ring-1 ring-line lg:sticky lg:top-24">
                <div class="relative h-14 bg-school">
                    <Crest :name="child.name" size="size-16 text-xl" class="absolute -bottom-8 left-4 ring-4 ring-surface" />
                </div>
                <div class="px-4 pt-10 pb-4">
                    <h1 class="text-xl font-bold tracking-tight">{{ child.name }}</h1>
                    <p class="text-sm text-muted">{{ child.class ?? 'Not in a class yet' }}</p>
                    <p class="mt-0.5 text-sm text-faint">Admission number {{ child.admission_number }}</p>
                </div>

                <dl class="grid grid-cols-3 divide-x divide-line border-t border-line text-center">
                    <div class="px-2 py-3.5">
                        <dt class="text-xs text-muted">Today</dt>
                        <dd class="mt-1.5 flex justify-center"><RegisterMark :status="child.today" /></dd>
                        <dd class="mt-1 text-xs" :class="child.today ? marks[child.today].text : 'text-faint'">{{ child.today ? marks[child.today].label : 'Not taken yet' }}</dd>
                    </div>
                    <button class="press px-2 py-3.5" @click="tab = 'attendance'">
                        <dt class="text-xs text-muted">This month</dt>
                        <dd class="mt-1 text-xl font-bold">{{ daysIn }}<span class="text-sm font-normal text-muted">/{{ daysTaken }}</span></dd>
                        <dd class="text-xs text-muted">days in school</dd>
                    </button>
                    <button class="press px-2 py-3.5 disabled:cursor-default" :disabled="!child.sees_fees" @click="tab = 'fees'">
                        <dt class="text-xs text-muted">Fees</dt>
                        <template v-if="child.balance">
                            <dd class="mt-1 text-[15px] leading-tight font-bold whitespace-nowrap" :class="child.balance.pesewas > 0 ? 'text-ink' : 'text-present'">
                                {{ child.balance.pesewas > 0 ? child.balance.display : child.balance.pesewas < 0 ? 'In credit' : 'Paid' }}
                            </dd>
                            <dd class="text-xs text-muted">{{ child.balance.pesewas > 0 ? 'to pay' : 'nothing owed' }}</dd>
                        </template>
                        <dd v-else class="mt-1.5 text-xs text-faint">Ask the school</dd>
                    </button>
                </dl>
            </article>

            <div class="space-y-5">
            <div class="grid rounded-xl bg-line/60 p-1 text-sm font-bold" :class="child.sees_fees ? 'grid-cols-3' : 'grid-cols-2'" role="tablist">
                <button
                    v-for="name in child.sees_fees ? ['attendance', 'results', 'fees'] : ['attendance', 'results']"
                    :key="name"
                    role="tab"
                    :aria-selected="tab === name"
                    class="press rounded-lg py-2.5 capitalize transition"
                    :class="tab === name ? 'bg-surface text-ink shadow-[0_1px_2px_rgba(0,0,0,0.08)]' : 'text-muted'"
                    @click="tab = name"
                >
                    {{ name }}
                </button>
            </div>

            <AttendanceMonth v-if="tab === 'attendance'" :key="`a${child.id}`" :child-id="child.id" />
            <ResultsPanel v-else-if="tab === 'results'" :key="`r${child.id}`" :child-id="child.id" />
            <FeesPanel v-else-if="child.sees_fees" :key="`f${child.id}`" :child-id="child.id" />
            </div>
        </div>
    </section>
</template>
