import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    /*
     * CKEditor 5 is a large dependency, but it is only ever loaded on the few
     * administrator screens that carry a long form editor, through the dynamic
     * import in resources/js/app.js. The chunk is intentionally big, so the
     * default warning is silenced rather than left to hide real regressions in
     * the small entry chunk.
     */
    build: {
        chunkSizeWarningLimit: 1024,
    },
});
