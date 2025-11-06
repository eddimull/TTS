<template>
    <FinanceLayout>
        <AllPaidUnpaid
            :all-bookings="allBookings"
            :snapshot-date="snapshotDate"
            :compare-with-current="compareWithCurrent"
            :selected-year="selectedYear"
        />
    </FinanceLayout>
</template>

<script setup>
import FinanceLayout from "./Layout/FinanceLayout.vue";
import AllPaidUnpaid from "./Components/AllPaidUnpaid.vue";

const props = defineProps({
    allBookings: {
        type: Array,
        required: true,
    },
    snapshotDate: {
        type: String,
        default: null,
    },
    compareWithCurrent: {
        type: Boolean,
        default: false,
    },
    selectedYear: {
        type: [Number, String],
        default: null,
    },
});
</script>
