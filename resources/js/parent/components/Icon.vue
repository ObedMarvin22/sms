<script setup>
// Line icons drawn inline (no icon package: one request less on mobile data). 24 × 24, 1.75 stroke.
const props = defineProps({
    name: { type: String, required: true },
    size: { type: String, default: 'size-5' },
});

const paths = {
    children: ['M16 19c0-2.2-1.8-4-4-4s-4 1.8-4 4', 'M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z', 'M20 18c0-1.7-1-3-2.5-3.6', 'M17 6.2a2.5 2.5 0 0 1 0 4.6', 'M4 18c0-1.7 1-3 2.5-3.6', 'M7 6.2a2.5 2.5 0 0 0 0 4.6'],
    news: ['M4 6h11v12H6a2 2 0 0 1-2-2V6Z', 'M15 9h3a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-3', 'M7.5 9.5h4', 'M7.5 12.5h4', 'M7.5 15.5h2.5'],
    bell: ['M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15L6 16Z', 'M10 20a2 2 0 0 0 4 0'],
    user: ['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M5 20c0-3.3 3.1-6 7-6s7 2.7 7 6'],
    back: ['M15 6l-6 6 6 6'],
    chevron: ['M9 6l6 6-6 6'],
    left: ['M15 6l-6 6 6 6'],
    right: ['M9 6l6 6-6 6'],
    download: ['M12 4v11', 'M7.5 10.5 12 15l4.5-4.5', 'M5 19h14'],
    card: ['M3.5 7a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2V7Z', 'M3.5 10h17', 'M7 15h3'],
    calendar: ['M5 6h14v13H5V6Z', 'M5 10h14', 'M9 4v4', 'M15 4v4'],
    alert: ['M12 8v5', 'M12 16.5v.01', 'M10.3 4.6 3.2 17a2 2 0 0 0 1.7 3h14.2a2 2 0 0 0 1.7-3L13.7 4.6a2 2 0 0 0-3.4 0Z'],
    cash: ['M4 7h16v10H4V7Z', 'M12 14.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z', 'M7 10v4', 'M17 10v4'],
    megaphone: ['M4 10v4h3l7 4V6L7 10H4Z', 'M17.5 9.5a3.5 3.5 0 0 1 0 5'],
    refresh: ['M19 12a7 7 0 1 1-2.1-5', 'M19 5v4h-4'],
    offline: ['M3 3l18 18', 'M8.5 15.5a5 5 0 0 1 7 0', 'M5 12a10 10 0 0 1 4-2.4', 'M14.5 9.6A10 10 0 0 1 19 12', 'M12 19v.01'],
    lock: ['M7 11V8a5 5 0 0 1 10 0v3', 'M5.5 11h13v9h-13v-9Z'],
    phone: ['M8 3h8a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z', 'M11 18h2'],
    home: ['M4 11l8-7 8 7', 'M6 9.5V20h12V9.5'],
    check: ['M5 12.5l4.5 4.5L19 7.5'],
    logout: ['M15 4h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-3', 'M10 8l-4 4 4 4', 'M6 12h10'],
    install: ['M12 4v10', 'M8 10l4 4 4-4', 'M5 17v2h14v-2'],
};
</script>

<template>
    <svg :class="props.size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path v-for="(d, i) in paths[props.name] ?? []" :key="i" :d="d" />
    </svg>
</template>
