<template>
  <div>
    <div
      v-for="(band, index) in filteredServices"
      :key="index"
      class="card my-4"
    >
      <Toolbar class="p-mb-4 border-b-2 sticky top-[60px] z-10">
        <template #start>
          <div>
            <h3 class="font-bold">
              Unpaid Services for {{ band.name }}
            </h3>
          </div>
        </template>
        <template #end>
          <IconField>
            <InputIcon>
              <i class="pi pi-search" />
            </InputIcon>
            <InputText
              v-model="filters['global'].value"
              placeholder="Search"
            />
          </IconField>

          <Select
            v-model="selectedYear"
            placeholder="Year"
            class="mx-2 border rounded shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-black dark:text-gray-100"
            :options="availableYears"
            show-clear
          />
        </template>
      </Toolbar>
      
      <!-- Aggregate Information -->
      <div class="p-4 bg-gray-50 dark:bg-gray-800 border-b">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
          <div class="bg-white dark:bg-gray-700 p-3 rounded shadow">
            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-300">
              Number of Bookings
            </h4>
            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
              {{ getFilteredBookings(band.unpaidBookings).length }}
            </p>
          </div>
          <div class="bg-white dark:bg-gray-700 p-3 rounded shadow">
            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-300">
              Total Due
            </h4>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400">
              ${{ getTotalDue(getFilteredBookings(band.unpaidBookings)) }}
            </p>
          </div>
          <div class="bg-white dark:bg-gray-700 p-3 rounded shadow">
            <h4 class="text-sm font-medium text-gray-600 dark:text-gray-300">
              Amount Paid
            </h4>
            <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">
              ${{ getTotalPaid(getFilteredBookings(band.unpaidBookings)) }}
            </p>
          </div>
        </div>
      </div>

      <DataTable
        v-model:filters="filters"
        :value="band.unpaidBookings"
        striped-rows
        show-gridlines
        row-hover
        :paginator="true"
        :rows="20"
        class="cursor-pointer"
        sort-field="date"
        sort-order="1"
        :global-filter-fields="['name', 'status', 'price', 'amount_paid', 'date']"
        filter-display="menu"
        @row-click="
          (event) => {
            gotoPayment(event.data, band);
          }
        "
      >
        <Column
          field="name"
          header="Booking Name"
          :sortable="true"
          class="w-1/4"
        >
          <template #body="value">
            <span
              :class="{
                'line-through':
                  value?.data?.status === 'cancelled',
              }"
            >
              {{ value.data.name }}
            </span>
          </template>
        </Column>
        <Column
          field="status"
          header="Status"
          :sortable="true"
          filter-field="status"
          :show-filter-menu="true"
          :show-filter-match-modes="false"
          class="w-1/6"
        >
          <template #body="value">
            <span
              :class="{
                'text-red-600 font-bold':
                  value?.data?.status === 'cancelled',
                'text-green-600 font-bold':
                  value?.data?.status === 'confirmed',
                'text-blue-600 font-bold':
                  value?.data?.status === 'pending',
              }"
            >
              {{ value.data.status.charAt(0).toUpperCase() +
                value.data.status.slice(1) }}
            </span>
          </template>
          <template #filter="{ filterModel, filterCallback}">
            <MultiSelect
              v-model="filterModel.value"
              :options="statusOptions"
              option-label="label"
              option-value="value"
              placeholder="Status"
              class="w-full"
              @change="filterCallback()"
            >
              <template #option="slotProps">
                <div class="flex">
                  <span>{{ slotProps.option.label }}</span>
                </div>
              </template>
            </MultiSelect>
          </template>
        </Column>
        <Column
          field="price"
          header="Price"
          :sortable="true"
        >
          <template #body="value">
            ${{ value.data.price }}
          </template>
        </Column>
        <Column
          field="amount_paid"
          header="Amount Paid"
          :sortable="true"
        >
          <template #body="slotProps">
            <div class="relative w-full h-8 bg-gray-300 rounded">
              <div
                class="absolute top-0 left-0 h-full rounded"
                :style="{
                  width: `${getPaymentPercentage(
                    slotProps.data
                  )}%`,
                  backgroundColor: getPaymentColor(
                    slotProps.data
                  ),
                }"
              />
              <div
                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 flex items-center justify-center font-bold bg-slate-700 bg-opacity-30 px-2 py-1 rounded"
              >
                ${{ slotProps.data.amount_paid }}
              </div>
            </div>
          </template>
        </Column>
        <Column
          field="date"
          header="Booking Date"
          :sortable="true"
          data-type="date"
          filter-field="date"
          :show-filter-match-modes="true"
        >
          <template #body="{ data }">
            {{ formatDate(data.date) }}
          </template>
          <template #filter="{ filterModel }">
            <DatePicker
              v-model="filterModel.value"
              date-format="mm/dd/yy"
              placeholder="mm/dd/yyyy"
            />
          </template>
        </Column>
        <template #empty>
          <div class="p-4 text-center">
            No unpaid services found.
          </div>
        </template>
      </DataTable>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import InputText from "primevue/inputtext";
import Toolbar from "primevue/toolbar";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";
import MultiSelect from "primevue/multiselect";
import DatePicker from "primevue/datepicker";
import Select from "primevue/select";

const props = defineProps({
    unpaid: {
        type: Array,
        required: true,
    },
});

const initFilters = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        status: { value: null, matchMode: FilterMatchMode.IN },
        date: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.DATE_IS }] },
    };
};

const filters = ref();
initFilters();

const clearFilter = () => {
    initFilters();
};

// default to the current year
const selectedYear = ref(new Date().getFullYear());

const filteredServices = computed(() => {
    // Only apply year filter here, let DataTable handle global search and other filters
    if (!selectedYear.value) {
        return props.unpaid.map((band) => ({
            ...band,
            unpaidBookings: band.unpaidBookings.map(booking => ({
                ...booking,
                date: new Date(booking.date) // Ensure date is a Date object for proper filtering
            }))
        }));
    }

    return props.unpaid.map((band) => ({
        ...band,
        unpaidBookings: band.unpaidBookings
            .filter((unpaid) => {
                // Handle year filter - check if the date string contains the selected year
                return unpaid.date.includes(selectedYear.value.toString());
            })
            .map(booking => ({
                ...booking,
                date: new Date(booking.date) // Ensure date is a Date object for proper filtering
            }))
    }));
});
const statusOptions = [
    { label: "Confirmed", value: "confirmed" },
    { label: "Pending", value: "pending" },
    { label: "Cancelled", value: "cancelled" },
    { label: "Draft", value: "draft" },
];
const availableYears = computed(() => {
    const years = new Set(
        props.unpaid
            .reduce((acc, band) => {
                return acc.concat(band.unpaidBookings);
            }, [])
            .map((booking) => new Date(booking.date).getFullYear())
    );
    return Array.from(years).sort((a, b) => b - a);
});

const getFilteredBookings = (bookings) => {
    let filtered = bookings;

    // Apply global filter
    if (filters.value?.global?.value) {
        const globalValue = filters.value.global.value.toLowerCase();
        filtered = filtered.filter(booking => {
            return (
                booking.name?.toLowerCase().includes(globalValue) ||
                booking.status?.toLowerCase().includes(globalValue) ||
                booking.price?.toString().toLowerCase().includes(globalValue) ||
                booking.amount_paid?.toString().toLowerCase().includes(globalValue) ||
                booking.date?.toString().toLowerCase().includes(globalValue)
            );
        });
    }

    // Apply status filter
    if (filters.value?.status?.value && filters.value.status.value.length > 0) {
        filtered = filtered.filter(booking => 
            filters.value.status.value.includes(booking.status)
        );
    }

    // Apply date filter
    if (filters.value?.date?.constraints?.[0]?.value) {
        const filterDate = filters.value.date.constraints[0].value;
        const matchMode = filters.value.date.constraints[0].matchMode;
        
        filtered = filtered.filter(booking => {
            const bookingDate = new Date(booking.date);
            const compareDate = new Date(filterDate);
            
            // Reset time components for date-only comparison
            bookingDate.setHours(0, 0, 0, 0);
            compareDate.setHours(0, 0, 0, 0);
            
            switch (matchMode) {
                case FilterMatchMode.DATE_IS:
                    return bookingDate.getTime() === compareDate.getTime();
                case FilterMatchMode.DATE_IS_NOT:
                    return bookingDate.getTime() !== compareDate.getTime();
                case FilterMatchMode.DATE_BEFORE:
                    return bookingDate.getTime() < compareDate.getTime();
                case FilterMatchMode.DATE_AFTER:
                    return bookingDate.getTime() > compareDate.getTime();
                default:
                    return bookingDate.getTime() === compareDate.getTime();
            }
        });
    }

    return filtered;
};

const getPaymentPercentage = (booking) => {
    return (booking.amount_paid / booking.price) * 100;
};

const getPaymentColor = (booking) => {
    const paymentRatio = booking.amount_paid / booking.price;
    if (paymentRatio === 0) return "rgb(255, 0, 0)"; // Red
    if (paymentRatio === 1) return "rgb(0, 255, 0)"; // Green

    // Calculate the gradient color
    const red = Math.round(255 * (1 - paymentRatio));
    const green = Math.round(255 * paymentRatio);
    return `rgb(${red}, ${green}, 0)`;
};

const getTotalDue = (bookings) => {
    return bookings.reduce((total, booking) => {
        const price = parseFloat(booking.price) || 0;
        const amountPaid = parseFloat(booking.amount_paid) || 0;
        return total + (price - amountPaid);
    }, 0).toFixed(2);
};

const getTotalPaid = (bookings) => {
    return bookings.reduce((total, booking) => {
        const amountPaid = parseFloat(booking.amount_paid) || 0;
        return total + amountPaid;
    }, 0).toFixed(2);
};

const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(value);
    return date.toLocaleDateString('en-US', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
};

const gotoPayment = (data, band) => {
    const url = route("Booking Finances", { band: band.id, booking: data.id });
    router.get(url);
};
</script>
<style scoped>
.text-stroke {
    text-shadow: -1px -1px 0 #333, 1px -1px 0 #333, -1px 1px 0 #333,
        1px 1px 0 #333;
}
</style>
