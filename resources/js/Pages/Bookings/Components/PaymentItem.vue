<template>
  <div class="flex flex-wrap items-center -mx-4 mb-0 md:mb-3">
    <div class="w-full md:w-4/6 lg:w-6/12 px-4 mb-0 md:mb-0 min-w-0">
      <div class="flex -mx-4 flex-wrap items-center min-w-0">
        <div class="px-4 min-w-0 flex-1">
          <div class="mb-2 flex items-center gap-2 flex-wrap">
            <h3 class="text-xl font-bold font-heading min-w-0 break-words">
              {{ payment.name || "Unnamed payment" }}
            </h3>
            <span
              v-if="payment.payment_type"
              :class="`inline-flex items-center px-2 py-1 rounded text-xs font-medium whitespace-nowrap bg-${getPaymentTypeColor(payment.payment_type)}-100 text-${getPaymentTypeColor(payment.payment_type)}-800 dark:bg-${getPaymentTypeColor(payment.payment_type)}-900 dark:text-${getPaymentTypeColor(payment.payment_type)}-200`"
            >
              <i
                :class="getPaymentTypeIcon(payment.payment_type)"
                class="pi mr-1"
              />
              {{ getPaymentTypeLabel(payment.payment_type) }}
            </span>
            <span
              v-if="payment.invoices_id && payment.invoice"
              class="flex-shrink-0"
            >
              <a
                :href="payment.invoice.stripe_url || (invoiceUrl + payment.invoice.stripe_id)"
                target="_blank"
              >
                <Button
                  icon="pi pi-external-link"
                  class="p-button-info"
                />
              </a>
            </span>
          </div>
          <p
            v-if="payment.payer"
            class="text-sm text-gray-600 dark:text-gray-400 truncate"
          >
            Made by: {{ payment.payer.name }}
          </p>
          <p
            v-if="payment.invoice && payment.user"
            class="text-xs text-gray-500 dark:text-gray-500 truncate"
          >
            Invoice sent {{ formatDateShort(payment.invoice.created_at) }} by {{ payment.user.name }}
          </p>
          <p
            class="md:hidden text-lg text-blue-500 font-bold font-heading mt-2"
          >
            ${{ typeof payment.amount === 'string' ? payment.amount : Number(payment.amount).toFixed(2) }}
          </p>
        </div>
      </div>
    </div>
    <div class="hidden md:block md:w-2/6 lg:w-2/12 px-4 flex-shrink-0">
      <p class="text-lg text-blue-500 font-bold font-heading whitespace-nowrap">
        ${{ typeof payment.amount === 'string' ? payment.amount : Number(payment.amount).toFixed(2) }}
      </p>
    </div>

    <div class="hidden lg:block lg:w-3/12 px-4 flex-shrink-0">
      <p
        :title="formattedPaymentDateTime"
        class="text-lg text-blue-500 font-bold font-heading whitespace-nowrap"
      >
        {{ formattedPaymentDate }}
      </p>
    </div>

    <div class="w-auto lg:w-1/12 px-4 flex-shrink-0">
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
import { DateTime } from "luxon";
import {router, usePage} from "@inertiajs/vue3";

const props = defineProps({
    payment: {
        type: Object,
        required: true,
    },
    booking: {
        type: Object,
        required: true,
    },
});

const formattedPaymentDate = props.payment.date
    ? DateTime.fromISO(props.payment.date).toFormat("yyyy-MM-dd")
    : props.payment.status;
const formattedPaymentDateTime = props.payment.date
    ? DateTime.fromISO(props.payment.date).toFormat("yyyy-MM-dd HH:mm:ss")
    : props.payment.status;

const config = usePage().props.config;
const invoiceUrl = config.StripeInvoiceURL;

const formatDateShort = (date) => {
    if (!date) return '';
    return DateTime.fromISO(date).toFormat('MMM d, yyyy');
};

const paymentTypeMap = {
    cash: { label: 'Cash', icon: 'pi pi-money-bill', color: 'green' },
    check: { label: 'Check', icon: 'pi pi-file', color: 'blue' },
    portal: { label: 'Client Portal', icon: 'pi pi-globe', color: 'purple' },
    venmo: { label: 'Venmo', icon: 'pi pi-mobile', color: 'cyan' },
    zelle: { label: 'Zelle', icon: 'pi pi-mobile', color: 'indigo' },
    invoice: { label: 'Invoice', icon: 'pi pi-file-edit', color: 'orange' },
    wire: { label: 'Wire Transfer', icon: 'pi pi-building', color: 'teal' },
    credit_card: { label: 'Credit Card', icon: 'pi pi-credit-card', color: 'pink' },
    other: { label: 'Other', icon: 'pi pi-question-circle', color: 'gray' },
};

const getPaymentTypeLabel = (type) => {
    return paymentTypeMap[type]?.label || type;
};

const getPaymentTypeIcon = (type) => {
    return paymentTypeMap[type]?.icon || 'pi pi-question-circle';
};

const getPaymentTypeColor = (type) => {
    return paymentTypeMap[type]?.color || 'gray';
};

const deletePayment = (payment) => {
    console.log("Deleting payment", payment);
    router.delete(
        route("Delete Booking Payment", {
            booking: props.booking.id,
            band: props.booking.band_id,
            payment: payment.id,
        })
    );
};
</script>
