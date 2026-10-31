import './parent.css';
import { createApp } from 'vue';
import App from './App.vue';
import { router } from './router.js';

createApp(App).use(router).mount('#app');

// Makes the app installable and lets it open offline. Scope: /parent/ only.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/parent/sw.js', { scope: '/parent' }));
}
