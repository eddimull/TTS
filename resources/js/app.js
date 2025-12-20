import '@/bootstrap';

// Import modules...
import { createApp, h } from 'vue';
import { createInertiaApp, Link } from '@inertiajs/vue3';
import { InertiaProgress } from '@inertiajs/progress';
import { createStore } from 'vuex'
import VueSweetalert2 from 'vue-sweetalert2';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import { DateTime } from 'luxon';
import * as Sentry from "@sentry/vue";
import CardModal from '@/Components/CardModal'
import Card from '@/Components/Card'
import Accordion from 'primevue/accordion';
import AccordionTab from 'primevue/accordiontab';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import Editor from 'primevue/editor';
import Panel from 'primevue/panel';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import DatePicker from 'primevue/datepicker';
import Divider from 'primevue/divider';
import Button from 'primevue/button';
import RadioButton from 'primevue/radiobutton';
import InputText from 'primevue/inputtext';
import DataTable from 'primevue/datatable';
import InputSwitch from 'primevue/inputswitch';
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import MultiSelect from 'primevue/multiselect';
import ContextMenu from 'primevue/contextmenu';
import Image from 'primevue/image';
import FileUpload from 'primevue/fileupload';
import Toolbar from 'primevue/toolbar';
import Tag from 'primevue/tag';
import ProgressBar from 'primevue/progressbar';
import Paginator from 'primevue/paginator';
import qs from 'qs';
import Chart from 'primevue/chart';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import ProgressSpinner from 'primevue/progressspinner';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
import Container from '@/Components/Container'
import ToastService from 'primevue/toastservice';
import Tooltip from 'primevue/tooltip';
import 'sweetalert2/dist/sweetalert2.min.css';
// import 'primevue/resources/themes/saga-blue/theme.css'
// import 'primevue/resources/primevue.min.css'
// import 'primeflex/primeflex.css';
import 'primeicons/primeicons.css'

import questionnaire from '@/Store/questionnaire';
import user from '@/Store/userStore';
import eventTypes from '@/Store/eventTypesStore';

const store = createStore({
    modules: {
        questionnaire,
        user,
        eventTypes
    }
})

createInertiaApp({
    title: (title) => `${title} - ${import.meta.env.VITE_APP_NAME}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        // Initialize Sentry for frontend error tracking
        if (import.meta.env.VITE_SENTRY_DSN) {
            Sentry.init({
                app,
                dsn: import.meta.env.VITE_SENTRY_DSN,
                environment: import.meta.env.VITE_APP_ENV || 'production',
                integrations: [
                    Sentry.browserTracingIntegration(),
                    Sentry.replayIntegration({
                        maskAllText: false,
                        blockAllMedia: false,
                    }),
                ],
                // Performance Monitoring
                tracesSampleRate: 1.0, // Capture 100% of transactions (adjust for production)
                // Session Replay
                replaysSessionSampleRate: 0.1, // 10% of sessions
                replaysOnErrorSampleRate: 1.0, // 100% of sessions with errors
                enableLogs: true
            });
        }

        app.component('BreezeAuthenticatedLayout', BreezeAuthenticatedLayout)
        app.use(plugin)
            .use(ZiggyVue, Ziggy)
            .use(store)
            .use(VueSweetalert2)
            .use(PrimeVue, { ripple: true, theme: { preset: Aura } })
            .use(ToastService)
        const components = {
            Link,
            Container,
            Chart,
            CardModal,
            DatePicker,
            Calendar: DatePicker, // Alias for backward compatibility
            Checkbox,
            Column,
            Accordion,
            AccordionTab,
            Editor,
            Button,
            Divider,
            Card,
            RadioButton,
            InputText,
            Image,
            InputNumber,
            Textarea,
            PVtextarea: Textarea,
            Dialog,
            DataTable,
            Panel,
            TabView,
            TabPanel,
            ProgressSpinner,
            FileUpload,
            Toolbar,
            Tag,
            ProgressBar,
            Paginator,
            Dropdown,
            MultiSelect,
            ContextMenu,
            InputSwitch
        };

        Object.entries(components).forEach(([name, component]) => {
            app.component(name, component);
        });
        app.directive('tooltip', Tooltip);
        app.mixin({ methods: { route } });

        app.config.globalProperties.$luxon = DateTime;
        app.config.globalProperties.$qs = qs;
        app.config.globalProperties.$route = route;

        // Initialize event types from Inertia shared data
        store.dispatch('eventTypes/initializeEventTypes', props.initialPage.props.eventTypes);

        return app.mount(el);


    }
});

InertiaProgress.init({ color: '#4B5563' });
