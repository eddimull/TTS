<template>
    <div class="max-w-2xl mx-auto py-8 px-4 sm:px-6 lg:px-8 dark:bg-gray-900">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white mb-6">
            Create New Booking for {{ band.name }}
        </h1>
        <form @submit.prevent="submitForm">
            <div class="space-y-6">
                <div>
                    <label
                        for="name"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                        >Booking Name</label
                    >
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        required
                        class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    />
                </div>

                <div>
                    <label
                        for="event_type_id"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                        >Event Type</label
                    >
                    <select
                        id="event_type_id"
                        v-model="form.event_type_id"
                        required
                        class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    >
                        <option value="">Select an event type</option>
                        <option
                            v-for="event in eventTypes"
                            :key="event.id"
                            :value="event.id"
                        >
                            {{ event.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="date"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                        >Date</label
                    >
                    <DatePicker
                        id="date"
                        v-model="bookingDate"
                        class="w-full"
                        :show-icon="true"
                    />

                    <reserved-calendar :booked-dates="bookedDates" />
                </div>

                <div>
                    <label
                        for="start_time"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                        >Start Time</label
                    >
                    <input
                        id="start_time"
                        v-model="form.start_time"
                        type="time"
                        required
                        class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    />
                </div>

                <div>
                    <label
                        for="duration"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                        >Duration (hours)</label
                    >
                    <input
                        id="duration"
                        v-model="form.duration"
                        type="number"
                        required
                        class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    />
                </div>

                <div>
                    <label
                        for="price"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-200"
                        >Price</label
                    >
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div
                            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
                        >
                            <span
                                class="text-gray-500 dark:text-gray-400 sm:text-sm"
                                >$</span
                            >
                        </div>

                        <InputNumber
                            id="price"
                            v-model="form.price"
                            mode="currency"
                            currency="USD"
                            locale="en-US"
                            class="w-full rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                        />
                    </div>
                </div>
                <ContractOptions v-model="form.contract_option" />
                <div>
                    <button
                        type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-900"
                    >
                        Create Booking
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

<script>
import { ref } from "vue";
import ContractOptions from "./ContractOptions.vue";
import { useForm } from "@inertiajs/vue3";
import DatePicker from "primevue/datepicker";
import { DateTime } from "luxon";
import ReservedCalendar from "@/Components/ReservedCalendar.vue";

export default {
    components: {
        ContractOptions,
        DatePicker,
    },
    props: {
        band: {
            type: Object,
            required: true,
        },
        eventTypes: {
            type: Array,
            required: true,
        },
        bookedDates: {
            type: Array,
            required: true,
        },
    },
    setup(props) {
        const form = useForm({
            name: "",
            event_type_id: "",
            date: "",
            start_time: "19:00",
            duration: 4,
            price: null,
            contract_option: "",
        });

        //primevue datepicker uses a model of type Date
        //so when submitting, we need to convert the date to an ISODate
        const bookingDate = ref(new Date());

        const submitForm = () => {
            form.post(route("bands.booking.store", props.band.id), {
                preserveScroll: true,
                preserveState: true,
            });
        };

        return {
            form,
            bookingDate,
            submitForm,
        };
    },
    watch: {
        bookingDate: {
            immediate: true,
            handler(newDate) {
                this.form.date = DateTime.fromJSDate(newDate).toISODate();
            },
        },
    },
};
</script>
