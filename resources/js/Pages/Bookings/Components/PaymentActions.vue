<template>
  <div>
    <div
      class="flex flex-wrap content-around justify-evenly lg:-mb-4"
    >
      <Button
        v-if="booking.amountLeft !== '0.00'"
        icon="pi pi-dollar"
        label="Make Payment"
        class="p-button-success"
        @click="$emit('openDialog')"
      />
      <Button
        icon="pi pi-download"
        label="Download Receipt"
        class="p-button-default"
        @click="$emit('downloadReceipt')"
      />
    </div>
  </div>
</template>
<script setup>
    defineEmits(['openDialog', 'downloadReceipt']);
    const props = defineProps({
        booking: {
            type: Object,
            required: true
        },
    });

</script>