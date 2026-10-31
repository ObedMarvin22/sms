<script setup>
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { api } from './api.js';
import { session } from './session.js';

const route = useRoute();
const signedIn = computed(() => Boolean(session.token));

// The unread badge: checked on opening, every 60 s while the app is on screen, and on return.
let timer = null;

async function refreshUnread() {
    if (!session.token || document.visibilityState !== 'visible') {
        return;
    }
    try {
        session.unread = (await api('notifications?per_page=1')).unread ?? 0;
    } catch {
        // offline or signed out: keep the last count
    }
}

function onVisible() {
    if (document.visibilityState === 'visible') {
        refreshUnread();
    }
}

onMounted(() => {
    refreshUnread();
    timer = setInterval(refreshUnread, 60_000);
    document.addEventListener('visibilitychange', onVisible);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisible);
});
watch(() => session.token, refreshUnread);

const tabs = [
    { name: 'children', label: 'Children', icon: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z' },
    { name: 'notifications', label: 'Notices', icon: 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0' },
    { name: 'account', label: 'Account', icon: 'M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z' },
];

const activeTab = computed(() => (route.name === 'child' ? 'children' : route.name));
</script>

<template>
    <div class="mx-auto flex min-h-dvh max-w-lg flex-col">
        <header class="sticky top-0 z-10 flex items-center gap-3 bg-school px-4 py-3 text-school-text shadow">
            <img v-if="session.school.logo" :src="session.school.logo" alt="" class="size-8 rounded bg-white object-contain" />
            <h1 class="truncate text-base font-semibold">{{ session.school.name }}</h1>
        </header>

        <p v-if="!session.online" class="bg-zinc-800 px-4 py-2 text-center text-sm text-white" role="status">
            You're offline. The app will update when you're back online.
        </p>

        <main class="flex-1 px-4 py-4" :class="{ 'pb-24': signedIn }">
            <RouterView />
        </main>

        <nav v-if="signedIn" class="fixed inset-x-0 bottom-0 z-10 border-t border-zinc-200 bg-white pb-[env(safe-area-inset-bottom)]">
            <ul class="mx-auto flex max-w-lg">
                <li v-for="tab in tabs" :key="tab.name" class="flex-1">
                    <RouterLink
                        :to="{ name: tab.name }"
                        class="relative flex flex-col items-center gap-0.5 py-2 text-xs"
                        :class="activeTab === tab.name ? 'font-semibold text-zinc-900' : 'text-zinc-500'"
                    >
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" />
                        </svg>
                        {{ tab.label }}
                        <span
                            v-if="tab.name === 'notifications' && session.unread > 0"
                            class="absolute top-1 left-1/2 ml-2 min-w-5 rounded-full bg-red-600 px-1 text-center text-[11px] leading-5 font-semibold text-white"
                            :aria-label="`${session.unread} unread`"
                        >
                            {{ session.unread > 99 ? '99+' : session.unread }}
                        </span>
                    </RouterLink>
                </li>
            </ul>
        </nav>
    </div>
</template>
