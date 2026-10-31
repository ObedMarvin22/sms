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
    { name: 'news', label: 'News', icon: 'M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46' },
    { name: 'notifications', label: 'Notices', icon: 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0' },
    { name: 'account', label: 'Account', icon: 'M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z' },
];

const activeTab = computed(() => ({ child: 'children', announcement: 'news' })[route.name] ?? route.name);
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
