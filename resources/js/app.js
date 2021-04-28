require('./bootstrap');

// Import modules...
import { createApp, h, Vue } from 'vue';
import { App as InertiaApp, plugin as InertiaPlugin } from '@inertiajs/inertia-vue3';
import { InertiaProgress } from '@inertiajs/progress';
import VueSweetalert2 from 'vue-sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import moment from 'moment';
import CardModal from './Components/CardModal'
import Card from './Components/Card'


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
    .component("card-modal",CardModal)
    .component("card",Card)
    .mount(el);

InertiaProgress.init({ color: '#4B5563' });
