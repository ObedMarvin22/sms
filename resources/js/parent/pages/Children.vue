<script setup>
import { onMounted, ref } from 'vue';
import { api } from '../api.js';
import { session } from '../session.js';
import { statusLabels, statusStyles } from '../format.js';
import ErrorNote from '../components/ErrorNote.vue';

const children = ref(null);
const error = ref(null);

async function load() {
    error.value = null;
    try {
        children.value = (await api('children')).data;
    } catch (e) {
        error.value = e.message;
    }
}

async function install() {
    const prompt = session.installPrompt;
    session.installPrompt = null;
    await prompt?.prompt();
}

onMounted(load);
</script>

<template>
    <section class="space-y-4">
        <h2 class="text-xl font-semibold">Your children</h2>

        <ErrorNote :message="error" />
        <button v-if="error" class="text-sm underline" @click="load">Try again</button>

        <p v-if="children === null && !error" class="text-zinc-500">Loading…</p>
        <p v-else-if="children?.length === 0" class="rounded-lg bg-white p-4 text-zinc-600">
            No children are linked to your number yet. Please contact the school.
        </p>

        <ul class="space-y-3">
            <li v-for="child in children" :key="child.id">
                <RouterLink :to="{ name: 'child', params: { id: child.id } }" class="block rounded-xl border border-zinc-200 bg-white p-4 shadow-sm active:bg-zinc-50">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-lg font-semibold">{{ child.name }}</p>
                            <p class="text-sm text-zinc-600">{{ child.class ?? 'No class yet' }} · {{ child.admission_number }}</p>
                        </div>
                        <span v-if="child.today" class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusStyles[child.today]">
                            {{ statusLabels[child.today] }} today
                        </span>
                    </div>
                    <p v-if="child.balance" class="mt-3 text-sm">
                        <template v-if="child.balance.pesewas > 0">Owes <span class="font-semibold">{{ child.balance.display }}</span></template>
                        <template v-else-if="child.balance.pesewas < 0">In credit</template>
                        <template v-else>Fees fully paid</template>
                    </p>
                </RouterLink>
            </li>
        </ul>

        <div v-if="session.installPrompt" class="flex items-center justify-between gap-3 rounded-xl bg-white p-4 shadow-sm">
            <p class="text-sm">Add the app to your home screen for quick access.</p>
            <button class="shrink-0 rounded-lg bg-school px-3 py-2 text-sm font-semibold text-school-text" @click="install">Add</button>
        </div>
    </section>
</template>
