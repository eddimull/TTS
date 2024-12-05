<template>
    <FinanceLayout>
        <AllPaidUnpaid :paid-unpaid="paidUnpaid" />
    </FinanceLayout>
</template>

<script setup>
import FinanceLayout from "./Layout/FinanceLayout.vue";
import AllPaidUnpaid from "./Components/AllPaidUnpaid.vue";

const props = defineProps({
    paidUnpaid: {
        type: Array,
        required: true,
    },
});
</script>
