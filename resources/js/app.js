import "./bootstrap";
import "../css/app.css";

import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";

import PrimeVue from "primevue/config";
import Aura from "@primeuix/themes/aura";
import ToastService from "primevue/toastservice";
import "primeicons/primeicons.css";

createInertiaApp({
    // 🚀 A MÁGICA AQUI: Mudamos de `./Pages/${name}.vue` para `./${name}.vue`
    // E mandamos o Vite mapear todos os .vue dentro de js/
    resolve: (name) =>
        resolvePageComponent(`./${name}.vue`, import.meta.glob("./**/*.vue")),

    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        app.use(plugin)
            .use(ToastService) // 🚀 Injetando o serviço de Notificações Globais
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        darkModeSelector: ".dark",
                    },
                },
            });

        app.mount(el);
    },
    progress: {
        color: "#4B5563",
    },
});
