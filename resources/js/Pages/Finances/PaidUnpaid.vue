<template>
    <FinanceLayout>
        <AllPaidUnpaid :paid-unpaid="paidUnpaid" />
    </FinanceLayout>
    <Button label="Toggle Dark Mode" @click="toggleDarkMode()" />
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

function toggleDarkMode() {
    document.documentElement.classList.toggle("my-app-dark");
}
</script>
