<template>
  <div class="max-w-2xl mx-auto py-8 px-4 sm:px-6 lg:px-8 dark:bg-gray-900">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white mb-6">
      Create New Booking for {{ band.name }}
    </h1>
    <form @submit.prevent="submitForm">
      <div class="space-y-6">
        <div>
          <Input
            id="name"
            v-model="form.name"
            label="booking name"
            type="text"
            required
            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
          />
        </div>

        <div>
          <label
            for="event_type_id"
            class="block text-sm font-medium text-gray-700 dark:text-gray-200"
          >Event Type</label>
          <select
            id="event_type_id"
            v-model="form.event_type_id"
            required
            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
          >
            <option value="">
              Select an event type
            </option>
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
          >Date</label>
          
          <DatePicker
            id="date"
            v-model="bookingDate"
            class="w-full"
            :show-icon="true"
            @date-select="onDateSelect"
          >
            <template #date="slotProps">
              <span
                :class="{
                  'text-gray-400 line-through cursor-not-allowed': getBookingStatusForSlot(slotProps.date) == 'confirmed',
                  'text-blue-700': getBookingStatusForSlot(slotProps.date) == 'draft',
                  'text-yellow-500': getBookingStatusForSlot(slotProps.date) == 'pending'
                }"
              >
                {{ slotProps.date.day }}
              </span>
            </template>
          </DatePicker>
          
          <!-- Show tooltip for disabled dates with status-based colors -->
          <div
            v-if="selectedDateInfo"
            :class="getWarningClasses(selectedDateStatus)"
            class="mt-2 p-2 text-sm border rounded"
          >
            {{ selectedDateInfo }}
          </div>
        </div>

        <div>
          <LocationAutocomplete
            v-model="form.venue_name"
            name="venue_name"
            label="Venue"
            placeholder="Enter a venue name or address"
            @location-selected="handleLocationSelected"
          />
        </div>

        <div>
          <Input
            id="venue_address"
            v-model="form.venue_address"
            label="Venue Address"
            type="text"
            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
          />
        </div>

        <div>
          <Input
            id="start_time"
            v-model="form.start_time"
            label="Start Time"
            type="time"
            required
            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
          />
        </div>

        <div>
          <label
            for="duration"
            class="block text-sm font-medium text-gray-700 dark:text-gray-200"
          >Duration (hours)</label>
          <input
            id="duration"
            v-model="form.duration"
            type="number"
            required
            class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
          >
        </div>

        <div>
          <label
            for="price"
            class="block text-sm font-medium text-gray-700 dark:text-gray-200"
          >Price</label>
          <div class="mt-1 relative rounded-md shadow-sm">
            <div
              class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
            >
              <span
                class="text-gray-500 dark:text-gray-400 sm:text-sm"
              >$</span>
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
            :disabled="isSelectedDateBooked"
            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Create Booking
          </button>
        </div>
      </div>
    </form>
  </div>
</template>

<script>
import { ref, computed } from "vue";
import ContractOptions from "./ContractOptions.vue";
import { useForm } from "@inertiajs/vue3";
import DatePicker from "primevue/datepicker";
import InputNumber from "primevue/inputnumber";
import { DateTime } from "luxon";
import Input from "@/Components/Input.vue";
import LocationAutocomplete from "@/Components/LocationAutocomplete.vue";

export default {
    components: {
        ContractOptions,
        DatePicker,
        InputNumber,
        Input,
        LocationAutocomplete,
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
        bookingDetails: {
            type: Object,
            required: true,
        },
    },
    setup(props) {
        const form = useForm({
            name: "",
            event_type_id: "",
            date: "",
            venue_name: "",
            venue_address: "",
            start_time: "19:00",
            duration: 4,
            price: null,
            contract_option: "",
        });

        const bookingDate = ref(new Date());
        const selectedDateInfo = ref("");
        const selectedDateStatus = ref("");

        // Move _bookedDates into setup as a computed property
        const _bookedDates = computed(() => {
            return props.bookedDates.map(date => DateTime.fromISO(date).toFormat('yyyy-MM-dd'));
        });

        const isDateBooked = (date) => {
            const dateString = DateTime.fromJSDate(date).toISODate();
            return props.bookedDates.includes(dateString);
        };

        const getBookingStatusForSlot = (dateObj) => {
            const formatted = {day: dateObj.day, month: dateObj.month+1, year: dateObj.year}; // Adjust month for DateTime
            
            const dateString = DateTime.fromObject(formatted).toFormat('yyyy-MM-dd');
            let bookingStatus = null;
            if(_bookedDates.value.includes(dateString))
            {
                const booking = props.bookingDetails[`${dateString} 00:00:00`];
                if (booking) {
                    bookingStatus = booking.status || 'confirmed'; // Default to confirmed if no status
                }
            }
            return bookingStatus;
        };

        const submitForm = () => {
            if (isSelectedDateBooked.value) {
                return; // Don't submit if a booked date is selected
            }
            
            form.post(route("bands.booking.store", props.band.id), {
                preserveScroll: true,
                preserveState: true,
            });
        };

        const onDateSelect = (date) => {
            if (!date) return;
            updateSelectedDateInfo(date);
        };

        const updateSelectedDateInfo = (date) => {
            const dateString = DateTime.fromJSDate(date).toFormat('yyyy-MM-dd');

            if (_bookedDates.value.includes(dateString)) {
                const booking = props.bookingDetails[`${dateString} 00:00:00`];
                if (booking) {
                    selectedDateInfo.value = `This date is already has a ${booking.status} booking: ${booking.name} (${booking.event_type})`;
                    selectedDateStatus.value = booking.status || 'confirmed'; // Default to confirmed if no status
                } else {
                    selectedDateInfo.value = "This date is already booked";
                    selectedDateStatus.value = 'confirmed'; // Default status
                }
            } else {
                selectedDateInfo.value = "";
                selectedDateStatus.value = "";
            }
        };

        const getWarningClasses = (status) => {
            switch (status) {
                case 'pending':
                    return 'text-yellow-600 dark:text-yellow-400 bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800';
                case 'draft':
                    return 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800';
                case 'confirmed':
                default:
                    return 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800';
            }
        };

        const isSelectedDateBooked = computed(() => {
            if (!bookingDate.value) return false;
            // Only block submission if status is confirmed
            const dateString = DateTime.fromJSDate(bookingDate.value).toFormat('yyyy-MM-dd');
            if (_bookedDates.value.includes(dateString)) {
                const booking = props.bookingDetails[`${dateString} 00:00:00`];
                return booking && booking.status === 'confirmed';
            }
            return false;
        });

        const handleLocationSelected = (locationData) => {
            // Handle full location data from Google Places API
            form.venue_address = locationData.result.formatted_address;
        };

        return {
            form,
            bookingDate,
            selectedDateInfo,
            selectedDateStatus,
            submitForm,
            onDateSelect,
            updateSelectedDateInfo,
            getWarningClasses,
            isDateBooked,
            isSelectedDateBooked,
            getBookingStatusForSlot,
            handleLocationSelected,
        };
    },
    computed: {
        _bookedDates() {
            return this.bookedDates.map(date => DateTime.fromISO(date).toFormat('yyyy-MM-dd'));
        }
    },
    
    watch: {
        bookingDate: {
            immediate: false,
            handler(newDate) {
                if (newDate) {
                    this.form.date = DateTime.fromJSDate(newDate).toISODate();
                    this.updateSelectedDateInfo(newDate);
                }
            },
        },
    },
};
</script>
