<template>
  <div>
    <div
      v-if="payments.length === 0"
      class="mb-6 py-6 border-t border-b border-gray-200"
    >
      <span class="bold">NO PAYMENTS HAVE BEEN RECEIVED</span>
    </div>
    <PaymentItem
      v-for="payment in payments"
      :key="payment.id"
      class="mb-6 py-6 border-t border-b border-gray-200 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
      :payment="payment"
      :booking="booking"
      @dblclick="payment.enableDelete = !payment.enableDelete"
    />
  </div>
</template>
<script setup>
import PaymentItem from "./PaymentItem.vue";

const props = defineProps({
    payments: {
        type: Object,
        required: true,
    },
    booking: {
        type: Object,
        required: true,
    },
});
</script>
