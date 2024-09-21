import '@/bootstrap';

// Import modules...
import { createApp, h } from 'vue';
import { createInertiaApp, Link } from '@inertiajs/vue3';
import { InertiaProgress } from '@inertiajs/progress';
import { createStore } from 'vuex'
import VueSweetalert2 from 'vue-sweetalert2';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import moment from 'moment';
import CardModal from '@/Components/CardModal'
import Card from '@/Components/Card'
import Accordion from 'primevue/accordion';
import AccordionTab from 'primevue/accordiontab';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import Editor from 'primevue/editor';
import Panel from 'primevue/panel';
import PrimeVue from 'primevue/config';
import Calendar from 'primevue/calendar';
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
import Image from 'primevue/image';
import qs from 'qs';
import Chart from 'primevue/chart';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import ProgressSpinner from 'primevue/progressspinner';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
import Container from '@/Components/Container'
import ToastService from 'primevue/toastservice';
import 'sweetalert2/dist/sweetalert2.min.css';
import 'primevue/resources/themes/saga-blue/theme.css'
import 'primevue/resources/primevue.min.css'
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

        app.component('BreezeAuthenticatedLayout', BreezeAuthenticatedLayout)
        app.use(plugin)
            .use(ZiggyVue, Ziggy)
            .use(store)
            .use(VueSweetalert2)
            .use(PrimeVue)
            .use(ToastService)
            const components = {
                Link,
                Container,
                Chart,
                CardModal,
                Calendar,
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
                InputSwitch,
                Textarea,
                PVtextarea: Textarea,
                Dialog,
                Dropdown,
                DataTable,
                Panel,
                TabView,
                TabPanel,
                ProgressSpinner
            };

            Object.entries(components).forEach(([name, component]) => {
                app.component(name, component);
            });
        app.mixin({ methods: { route } });

        app.config.globalProperties.$moment = moment;
        app.config.globalProperties.$qs = qs;
        app.config.globalProperties.$route = route;

        return (() => {
            return store.dispatch('eventTypes/fetchEventTypes')
        })().then(()=>{
            return app.mount(el);
        })


    }
});

InertiaProgress.init({ color: '#4B5563' });
