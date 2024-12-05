<template>
    <div>
        <div
            class="flex flex-wrap content-around justify-evenly lg:-mb-4 gap-y-8"
        >
            <Button
                v-if="booking.amountLeft >= '0.00'"
                icon="pi pi-dollar"
                label="Make Payment"
                class="grow lg:grow-0"
                @click="$emit('openDialog')"
            />
            <Button
                v-if="booking.amountLeft >= '0.00'"
                icon="pi pi-receipt"
                label="Create Invoice"
                class="grow lg:grow-0"
                @click="$emit('openInvoiceDialog')"
            />
            <Button
                icon="pi pi-download"
                label="Download Receipt"
                class="grow lg:grow-0"
                @click="$emit('downloadReceipt')"
            />
        </div>
    </div>
</template>
<script setup>
defineEmits(["openDialog", "downloadReceipt", "openInvoiceDialog"]);
const props = defineProps({
    booking: {
        type: Object,
        required: true,
    },
});
</script>
