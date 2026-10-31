import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
                'resources/js/parent/main.js', // parent app (Vue), mounted on /parent
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        // Asset URLs in Vue templates stay as written (Laravel serves them), not imported.
        vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
        tailwindcss(),
    ]),
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
