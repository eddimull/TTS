// Import core modules
import { createApp, h } from 'vue';
import { createStore } from 'vuex';
import { App as InertiaApp, plugin as InertiaPlugin } from '@inertiajs/inertia-vue3';
import { InertiaProgress } from '@inertiajs/progress';

// Import third-party libraries
import VueSweetalert2 from 'vue-sweetalert2';
import moment from 'moment';
import qs from 'qs';
import AudioVisual from 'vue-audio-visual';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';

// Import store modules
import questionnaire from './Store/questionnaire';
import user from './Store/userStore';

// Import custom components
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated';
import BreezeNavLink from '@/Components/InlineLink';
import Container from '@/Components/Container';
import CardModal from './Components/CardModal';
import Card from './Components/Card';

// Import PrimeVue components individually
import Accordion from 'primevue/accordion';
import AccordionTab from 'primevue/accordiontab';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Divider from 'primevue/divider';
import Dropdown from 'primevue/dropdown';
import Editor from 'primevue/editor';
import Image from 'primevue/image';
import InputNumber from 'primevue/inputnumber';
import InputSwitch from 'primevue/inputswitch';
import InputText from 'primevue/inputtext';
import Panel from 'primevue/panel';
import ProgressSpinner from 'primevue/progressspinner';
import RadioButton from 'primevue/radiobutton';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import Textarea from 'primevue/textarea';
import Toolbar from 'primevue/toolbar';

// Import styles
import 'sweetalert2/dist/sweetalert2.min.css';
import 'primevue/resources/themes/saga-blue/theme.css';
import 'primevue/resources/primevue.min.css';
import 'primeicons/primeicons.css';

// Create Vuex store
const store = createStore({
  modules: { questionnaire, user }
});

// Create app
const el = document.getElementById('app');
const app = createApp({
  render: () => h(InertiaApp, {
    initialPage: JSON.parse(el.dataset.page),
    resolveComponent: (name) => require(`./Pages/${name}`).default,
  }),
}).mixin({ methods: { route } });

// Use plugins
app.use(InertiaPlugin)
   .use(store)
   .use(VueSweetalert2)
   .use(PrimeVue)
   .use(AudioVisual)
   .use(ToastService);

// Register global components
const globalComponents = {
  Layout: BreezeAuthenticatedLayout,
  Link: BreezeNavLink,
  Container,
  CardModal,
  Card,
  // PrimeVue components
  Accordion, AccordionTab, Button, Calendar, Checkbox, Column, DataTable,
  Dialog, Divider, Dropdown, Editor, Image, InputNumber, InputSwitch,
  InputText, Panel, ProgressSpinner, RadioButton, TabView, TabPanel, Textarea, Toolbar
};

Object.entries(globalComponents).forEach(([name, component]) => {
  app.component(name, component);
});

// Set up global properties
app.config.globalProperties.$moment = moment;
app.config.globalProperties.$qs = qs;
app.config.globalProperties.$route = route;

// Mount app
app.mount(el);

// Initialize Inertia progress
InertiaProgress.init({ color: '#4B5563' });