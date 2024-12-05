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
                            Paid Services for {{ band.name }}
                        </h3>
                    </div>
                </template>
                <template #end>
                    <IconField>
                        <InputIcon>
                            <i class="pi pi-search" />
                        </InputIcon>
                        <InputText
                            v-model="serviceFilter"
                            placeholder="Search"
                        />
                    </IconField>

                    <Select
                        v-model="selectedYear"
                        placeholder="Year"
                        class="mx-2 border rounded shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-black dark:text-gray-100"
                        :options="availableYears"
                        showClear
                    >
                    </Select>
                </template>
            </Toolbar>
            <DataTable
                :value="band.paidBookings"
                striped-rows
                row-hover
                show-gridlines
                :paginator="true"
                :rows="20"
                class="cursor-pointer"
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
                <Column field="price" header="Price" :sortable="true">
                    <template #body="value"> ${{ value.data.price }} </template>
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
                <Column field="date" header="Booking Date" :sortable="true" />
                <template #empty>
                    <div class="p-4 text-center">No paid services found.</div>
                </template>
            </DataTable>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import Toolbar from "primevue/toolbar";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import Select from "primevue/select";

const props = defineProps({
    paid: {
        type: Array,
        required: true,
    },
});

const selectedYear = ref("");

const serviceFilter = ref("");

const filteredServices = computed(() => {
    if (!serviceFilter.value && !selectedYear.value) {
        return props.paid;
    }
    return props.paid.map((band) => ({
        ...band,
        paidBookings: band.paidBookings.filter((paid) => {
            const matchesYear = selectedYear.value
                ? paid.date.includes(selectedYear.value)
                : true;

            if (!serviceFilter.value) {
                return matchesYear;
            }
            const searchTerm = serviceFilter.value.toLowerCase();
            const matchesService =
                paid.amount_paid
                    .toString()
                    .toLowerCase()
                    .includes(searchTerm) ||
                paid.name.toLowerCase().includes(searchTerm) ||
                paid.price.toString().includes(serviceFilter.value) ||
                paid.date.toLowerCase().includes(searchTerm);
            return matchesYear && matchesService;
        }),
    }));
});

const availableYears = computed(() => {
    const years = new Set(
        props.paid
            .reduce((acc, band) => {
                return acc.concat(band.paidBookings);
            }, [])
            .map((booking) => new Date(booking.date).getFullYear())
    );
    return Array.from(years).sort((a, b) => b - a);
});

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
