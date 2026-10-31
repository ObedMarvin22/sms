<script setup>
import { onMounted, ref, watch } from 'vue';
import { api } from '../api.js';
import { statusLabels, statusStyles } from '../format.js';
import AttendanceMonth from '../components/AttendanceMonth.vue';
import ErrorNote from '../components/ErrorNote.vue';
import FeesPanel from '../components/FeesPanel.vue';

const props = defineProps({ id: { type: Number, required: true } });

const child = ref(null);
const error = ref(null);
const tab = ref('attendance');

async function load() {
    child.value = null;
    error.value = null;
    try {
        child.value = (await api(`children/${props.id}`)).data;
    } catch (e) {
        error.value = e.message;
    }
}

onMounted(load);
watch(() => props.id, load);
</script>

<template>
    <section class="space-y-4">
        <RouterLink :to="{ name: 'children' }" class="text-sm underline">← All children</RouterLink>

        <ErrorNote :message="error" />
        <p v-if="!child && !error" class="text-zinc-500">Loading…</p>

        <template v-if="child">
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
                <h2 class="text-xl font-semibold">{{ child.name }}</h2>
                <p class="text-sm text-zinc-600">{{ child.class ?? 'No class yet' }} · {{ child.admission_number }}</p>
                <p class="mt-3 text-sm">
                    Today:
                    <span v-if="child.today" class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusStyles[child.today]">{{ statusLabels[child.today] }}</span>
                    <span v-else class="text-zinc-500">register not taken yet</span>
                </p>
                <p v-if="child.balance" class="mt-1 text-sm">
                    Balance: <span class="font-semibold">{{ child.balance.display }}</span>
                </p>
            </div>

            <div v-if="child.sees_fees" class="flex rounded-lg bg-zinc-200 p-1 text-sm font-medium" role="tablist">
                <button v-for="name in ['attendance', 'fees']" :key="name" role="tab" :aria-selected="tab === name"
                        class="flex-1 rounded-md py-2 capitalize" :class="tab === name ? 'bg-white shadow-sm' : 'text-zinc-600'"
                        @click="tab = name">
                    {{ name }}
                </button>
            </div>

            <AttendanceMonth v-if="tab === 'attendance' || !child.sees_fees" :child-id="child.id" />
            <FeesPanel v-else :child-id="child.id" @paid="load" />
        </template>
    </section>
</template>
