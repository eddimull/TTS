<template>
  <div class="flex flex-wrap items-center -mx-4 mb-0 md:mb-3">
    <div class="w-full md:w-4/6 lg:w-6/12 px-4 mb-0 md:mb-0">
      <div class="flex -mx-4 flex-wrap items-center">
        <div class="px-4">
          <h3 class="mb-2 text-xl font-bold font-heading">
            {{ payment.name || 'Unnamed payment' }}
            <span v-if="payment.invoices_id">
              <a
                :href="'https://dashboard.stripe.com/test/invoices/' + payment.invoice.stripe_id"
                target="_blank"
              >
                <Button
                  icon="pi pi-external-link"
                  class="p-button-info"
                />
              </a>
            </span>
          </h3>
          <p class="md:hidden text-lg text-blue-500 font-bold font-heading">
            ${{ payment.amount }}
          </p>
        </div>
      </div>
    </div>
    <div class="hidden md:block lg:w-2/12 px-4">
      <p class="text-lg text-blue-500 font-bold font-heading">
        ${{ payment.amount }}
      </p>
    </div>

    <div class="hidden lg:block lg:w-3/12 px-4">
      <p
        :title="formattedPaymentDateTime"
        class="text-lg text-blue-500 font-bold font-heading"
      >
        {{ formattedPaymentDate }}
      </p>
    </div>

    <div class="lg:w-1/12 px-4">
      <p class="text-lg text-blue-500 font-bold font-heading">
        <Button
          v-if="payment.enableDelete"
          icon="pi pi-trash"
          class="p-button-danger"
          @click="deletePayment(payment)"
        />
      </p>
    </div>
  </div>
</template>
<script setup>
import {DateTime} from 'luxon';

const props = defineProps({
    payment: {
        type: Object,
        required: true
    }
});

const formattedPaymentDate = props.payment.date ? DateTime.fromISO(props.payment.date).toFormat('yyyy-MM-dd') : props.payment.status;
const formattedPaymentDateTime = props.payment.date ? DateTime.fromISO(props.payment.date).toFormat('yyyy-MM-dd HH:mm:ss') : props.payment.status;

const deletePayment = (payment) => {
    console.log('Deleting payment', payment);
}
</script>
