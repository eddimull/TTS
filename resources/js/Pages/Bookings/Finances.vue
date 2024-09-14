<template>
  <Container>
    <PaymentDialog />
    <section class="py-20 bg-gray-100">
      <div class="container mx-auto px-4">
        <div class="p-8 lg:p-20 bg-white">
          <h2 class="mb-20 text-5xl font-bold font-heading">
            Payments - {{ booking.name }}
          </h2>
          <div class="flex flex-wrap items-center -mx-4">
            <div class="w-full xl:w-8/12 mb-8 xl:mb-0 px-4">
              <div class="hidden lg:flex w-full">
                <div class="w-full lg:w-3/6">
                  <h4 class="mb-6 font-bold font-heading text-gray-500">
                    Notes
                  </h4>
                </div>
                <div class="w-full lg:w-1/6">
                  <h4 class="mb-6 font-bold font-heading text-gray-500">
                    Amount
                  </h4>
                </div>
                <div
                  class="w-full lg:w-1/6 text-center"
                  data-removed="true"
                >
                  <h4 class="mb-6 font-bold font-heading text-gray-500">
                    Date
                  </h4>
                </div>
              </div>
              <PaymentList :payments="payments" />
              <PaymentActions :booking="booking" />
            </div>
            <PaymentSummary :booking="booking" />
          </div>
        </div>
      </div>
    </section>
  </Container>    
</template>
<script setup>
import { ref, reactive } from 'vue'
import BookingLayout from './Layout/BookingLayout.vue'
import PaymentDialog from './Components/PaymentDialog.vue';
import PaymentList from './Components/PaymentList.vue';
import PaymentActions from './Components/PaymentActions.vue';
import PaymentSummary from './Components/PaymentSummary.vue';

const props = defineProps({
  booking: {
    type: Object,
    default: () => ({})
  },
  band: {
    type: Object,
    default: () => ({})
  },
  payments: {
    type: Array,
    default: () => []
  },
})







const downloadReceipt = () => {
//   window.open(`/proposals/${props.proposal.key}/downloadReceipt`, '_blank')
}

const deletePayment = (payment) => {
//   inertia.delete(`/proposals/${props.proposal.key}/deletePayment/${payment.id}`, {
//     preserveScroll: true
//   })
}

defineOptions({
  layout: BookingLayout,
})
</script>
  