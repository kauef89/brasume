import "../css/app.css";
import "./bootstrap";

import { createInertiaApp, router } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createApp, h } from "vue";
import * as formatters from '@/Utils/formatters';
import { ZiggyVue } from 'ziggy-js';

import { definePreset } from "@primeuix/themes";
import Aura from "@primeuix/themes/aura";
import "primeicons/primeicons.css";
import PrimeVue from "primevue/config";
import ConfirmationService from "primevue/confirmationservice";
import ToastService from "primevue/toastservice";

// Componentes Globais PrimeVue
import AutoComplete from 'primevue/autocomplete';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Popover from 'primevue/popover';
import Select from 'primevue/select';

// Componentes Globais Design System
import AvatarBox from '@/Components/UI/AvatarBox.vue';
import BaseModal from '@/Components/UI/BaseModal.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import FormField from '@/Components/UI/FormField.vue';
import IconButton from '@/Components/UI/IconButton.vue';
import NumericKeypad from '@/Components/UI/NumericKeypad.vue';
import QtyStepper from '@/Components/UI/QtyStepper.vue';
import SegmentedControl from '@/Components/UI/SegmentedControl.vue';
import SelectableCard from '@/Components/UI/SelectableCard.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';

// 🛡️ Redirecionamento Inteligente de Sessão Expirada / Falha de Ligação
const handleSessionExpired = () => {
    const path = window.location.pathname;

    if (path.startsWith('/pos') || path.startsWith('/caixa') || path.startsWith('/pdv')) {
        window.location.href = '/pos/login';
    } else if (path.startsWith('/kds') || path.startsWith('/cozinha')) {
        window.location.href = '/kds/login';
    } else if (path.startsWith('/team') || path.startsWith('/waiter') || path.startsWith('/garcom')) {
        window.location.href = '/team/login';
    } else {
        window.location.href = '/cardapio';
    }
};

router.on('invalid', (event) => {
    event.preventDefault();
    handleSessionExpired();
});

router.on('exception', (event) => {
    const status = event.detail?.error?.response?.status;
    if (status === 419 || status === 401 || status === 409) {
        event.preventDefault();
        handleSessionExpired();
    }
});

// Motor de Tema e Cores do Tenant
const initGlobalTheme = (initialProps) => {
    const applyColorScheme = () => {
        const mode = localStorage.getItem('pos_color_scheme') || 'system';
        let isDark = false;

        if (mode === 'dark') {
            isDark = true;
        } else if (mode === 'system') {
            isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        }

        if (isDark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    };

    applyColorScheme();

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if ((localStorage.getItem('pos_color_scheme') || 'system') === 'system') {
            applyColorScheme();
        }
    });

    const applyPrimaryColor = (props) => {
        const color = props?.companySettings?.theme_color || props?.company?.theme_color;
        if (color) {
            document.documentElement.setAttribute("data-theme", color);
            localStorage.setItem('pos_primary_color', color);
        }
    };

    applyPrimaryColor(initialProps);

    router.on('navigate', (event) => {
        applyPrimaryColor(event.detail.page.props);
    });
};

const CustomAura = definePreset(Aura, {
    semantic: {
        primary: {
            50: 'var(--color-tenant-50)',
            100: 'var(--color-tenant-100)',
            200: 'var(--color-tenant-200)',
            300: 'var(--color-tenant-300)',
            400: 'var(--color-tenant-400)',
            500: 'var(--color-tenant-500)',
            600: 'var(--color-tenant-600)',
            700: 'var(--color-tenant-700)',
            800: 'var(--color-tenant-800)',
            900: 'var(--color-tenant-900)',
            950: 'var(--color-tenant-950)'
        }
    }
});

createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob("./**/*.vue")),
    setup({ el, App, props, plugin }) {
        initGlobalTheme(props.initialPage.props);

        const app = createApp({ render: () => h(App, props) });

        app.use(plugin)
            .use(ZiggyVue)
            .use(ToastService)
            .use(ConfirmationService)
            .use(PrimeVue, {
                theme: {
                    preset: CustomAura,
                    options: {
                        darkModeSelector: ".dark",
                    },
                },
            });

        // Registo PrimeVue
        app.component('AutoComplete', AutoComplete);
        app.component('InputText', InputText);
        app.component('InputNumber', InputNumber);
        app.component('Checkbox', Checkbox);
        app.component('Select', Select);
        app.component('Dialog', Dialog);
        app.component('Popover', Popover);

                // Registo Design System
        app.component('BaseModal', BaseModal);
        app.component('IconButton', IconButton);
        app.component('EmptyState', EmptyState);
        app.component('SegmentedControl', SegmentedControl);
        app.component('QtyStepper', QtyStepper);
        app.component('AvatarBox', AvatarBox);
        app.component('FormField', FormField);
        app.component('SelectableCard', SelectableCard);
        app.component('StatusBadge', StatusBadge);
        app.component('NumericKeypad', NumericKeypad);

        // 🚀 INJEÇÃO DOS FORMATADORES GLOBAIS
        Object.keys(formatters).forEach(key => {
            app.config.globalProperties['$' + key] = formatters[key];
        });

        
        app.mount(el);
    },
    progress: {
        color: "#4B5563",
    },
});

// Disparo de Métricas (Exclui estritamente todos os 3 Apps B2B de operação)
router.on('navigate', () => {
    const path = window.location.pathname;

    if (
        path.startsWith('/team') ||
        path.startsWith('/pos') ||
        path.startsWith('/kds') ||
        path.startsWith('/operations')
    ) {
        return;
    }

    if (typeof window.fbq === 'function') {
        window.fbq('track', 'PageView');
    }

    if (typeof window.gtag === 'function') {
        window.gtag('event', 'page_view', {
            page_path: path
        });
    }
});
