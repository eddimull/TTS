<template>
  <div class="w-full xl:w-4/12 px-4">
    <div class="p-6 md:p-12 bg-blue-300">
      <h2 class="mb-6 text-4xl font-bold font-heading text-white">
        Summary
      </h2>
      <div class="flex mb-8 items-center justify-between pb-5 border-b border-blue-100">
        <span class="text-blue-50">Agreed Price</span>
        <span class="text-xl font-bold font-heading text-white">${{ booking.price }}</span>
      </div>
      <h4 class="mb-2 text-xl font-bold font-heading text-white">
        Payment Total
      </h4>
      <div class="flex mb-2 justify-between items-center">
        <span class="text-blue-50">Amount Paid</span>
        <span class="text-xl font-bold font-heading text-white">${{ booking.amountPaid }}</span>
      </div>
      <div class="flex mb-10 justify-between items-center">
        <span class="text-xl font-bold font-heading text-white">Payment Left</span>
        <span class="text-xl font-bold font-heading text-white">${{ booking.amountLeft }}</span>
      </div>
      <div v-if="booking.amountLeft === '0.00'" class="text-2xl font-bold font-heading text-white text-center">
        <span class="underline">PAID</span> <i class="pi pi-check" />
      </div>
    </div>
  </div>
</template>
<script setup>
const props = defineProps({
  booking: {
    type: Object,
    required: true
  }
});
</script>