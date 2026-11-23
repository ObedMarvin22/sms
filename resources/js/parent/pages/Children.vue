<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { api } from '../api.js';
import { session } from '../session.js';
import { greeting, marks } from '../format.js';
import Crest from '../components/Crest.vue';
import EmptyState from '../components/EmptyState.vue';
import ErrorState from '../components/ErrorState.vue';
import Icon from '../components/Icon.vue';
import RegisterMark from '../components/RegisterMark.vue';

const children = ref(null);
const error = ref(null);

async function load() {
    error.value = null;
    try {
        const [list, me] = await Promise.all([api('children'), session.me ? null : api('me')]);
        children.value = list.data;
        if (me) {
            session.me = me.data;
        }
    } catch (e) {
        error.value = e.message;
    }
}

const firstName = computed(() => session.me?.name?.split(/\s+/)[0] ?? '');
const owing = computed(() => (children.value ?? []).filter((c) => c.balance?.pesewas > 0).length);

async function install() {
    const prompt = session.installPrompt;
    session.installPrompt = null;
    await prompt?.prompt();
}

onMounted(load);
watch(() => session.refreshes, load);
</script>

<template>
    <section class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-bold tracking-tight">{{ greeting() }}<template v-if="firstName">, {{ firstName }}</template></h1>
        <p class="mt-1 text-muted">
            <template v-if="children === null">Getting your children's day…</template>
            <template v-else-if="children.length === 1">Here is how {{ children[0].first_name }} is doing.</template>
            <template v-else-if="children.length > 1">Here is how your {{ children.length }} children are doing.</template>
        </p>

        <ErrorState v-if="error" class="mt-6" :message="error" @retry="load" />

        <!-- Loading: the list's shape, so nothing jumps when it arrives. -->
        <div v-else-if="children === null" class="mt-6 divide-y divide-line overflow-hidden rounded-2xl bg-surface ring-1 ring-line" aria-hidden="true">
            <div v-for="n in 2" :key="n" class="flex items-center gap-3 p-4">
                <div class="skeleton size-11 rounded-full"></div>
                <div class="flex-1 space-y-2">
                    <div class="skeleton h-4 w-2/5"></div>
                    <div class="skeleton h-3 w-1/4"></div>
                </div>
                <div class="skeleton size-7"></div>
            </div>
        </div>

        <EmptyState
            v-else-if="children.length === 0"
            class="mt-6"
            icon="children"
            title="No children on your account yet"
            text="The school links children to your phone number. Ask the school office to add them."
        />

        <ul v-else class="mt-6 divide-y divide-line overflow-hidden rounded-2xl bg-surface ring-1 ring-line">
            <li v-for="child in children" :key="child.id">
                <RouterLink :to="{ name: 'child', params: { id: child.id } }" class="press flex items-center gap-3.5 p-4 hover:bg-paper/60">
                    <Crest :name="child.name" />
                    <div class="min-w-0 flex-1">
                        <p class="text-[17px] leading-snug font-bold break-words">{{ child.name }}</p>
                        <p class="text-sm text-muted">{{ child.class ?? 'Not in a class yet' }}</p>
                        <p v-if="child.balance" class="mt-1 text-sm">
                            <span v-if="child.balance.pesewas > 0">Owes <strong>{{ child.balance.display }}</strong></span>
                            <span v-else-if="child.balance.pesewas < 0" class="text-present">In credit</span>
                            <span v-else class="text-present">Fees paid</span>
                        </p>
                    </div>
                    <div class="flex flex-col items-end gap-1 text-right">
                        <RegisterMark :status="child.today" />
                        <span class="text-xs" :class="child.today ? marks[child.today].text : 'text-faint'">{{ child.today ? marks[child.today].label : 'Not taken' }}</span>
                    </div>
                    <Icon name="chevron" size="size-4" class="text-faint" />
                </RouterLink>
            </li>
        </ul>

        <p v-if="children?.length && owing > 0" class="mt-3 px-1 text-sm text-muted">Open a child to see their fees or pay online.</p>

        <div v-if="session.installPrompt" class="mt-6 flex items-center gap-3 rounded-2xl bg-surface p-4 ring-1 ring-line">
            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-paper text-ink"><Icon name="install" /></span>
            <p class="flex-1 text-sm">Add the app to your home screen to open it in one tap.</p>
            <button class="press rounded-lg bg-school px-3.5 py-2 text-sm font-bold text-school-text" @click="install">Add</button>
        </div>
    </section>
</template>
