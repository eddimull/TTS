import { defineConfig } from 'vitest/config'
import DefineOptions from 'unplugin-vue-define-options/vite'
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        DefineOptions(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
            'ziggy': '/vendor/tightenco/ziggy/src/js',
            'ziggy-vue': '/vendor/tightenco/ziggy/src/js/vue'
        },
        extensions: ['.js', '.vue', '.json'],
    },
    test:{
        environment: 'jsdom',
        setupFiles: ['./resources/js/tests/setup.js'],        
    },
    server: {
        hmr: !process.env.production
    }
});