import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { useTheme } from './Composables/useTheme';
import { initGlobalLoading } from './Composables/useGlobalLoading';
import { initInertiaErrorHandler } from './Composables/useInertiaErrors';
import { initFlashToasts } from './Composables/useFlashToasts';

const appName = import.meta.env.VITE_APP_NAME || 'PowerByExcellence';

initGlobalLoading();
initInertiaErrorHandler();
initFlashToasts();

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({
            setup() {
                useTheme();
            },
            render: () => h(App, props),
        })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#6366f1',
    },
});
