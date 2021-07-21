require('./bootstrap');

// Import modules...
import { createApp, h, Vue } from 'vue';
import { App as InertiaApp, plugin as InertiaPlugin } from '@inertiajs/inertia-vue3';
import { InertiaProgress } from '@inertiajs/progress';
import VueSweetalert2 from 'vue-sweetalert2';
import moment from 'moment';
import CardModal from './Components/CardModal'
import Card from './Components/Card'
import PrimeVue from 'primevue/config';
import Calendar from 'primevue/calendar';
import qs from 'qs';

import 'sweetalert2/dist/sweetalert2.min.css';
import 'primevue/resources/themes/saga-blue/theme.css'
import 'primevue/resources/primevue.min.css'                 
import 'primeicons/primeicons.css'    


const el = document.getElementById('app');

const app = createApp({
    render: () =>
        h(InertiaApp, {
            initialPage: JSON.parse(el.dataset.page),
            resolveComponent: (name) => require(`./Pages/${name}`).default,
        }),
})
    .mixin({ methods: { route } })
    .use(InertiaPlugin)
    .use(VueSweetalert2)
    .use(moment)
    .use(PrimeVue)
    .component("card-modal",CardModal)
    .component('calendar', Calendar)
    .component("card",Card)

app.config.globalProperties.$qs = qs;
app.mount(el)


InertiaProgress.init({ color: '#4B5563' });
