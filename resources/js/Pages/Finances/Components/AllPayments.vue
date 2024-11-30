<template>
  <div>
    <div
      v-for="(band,index) in filteredPayments"
      :key="index"
      class="card my-4"
    >
      <Toolbar class="p-mb-4 border-b-2">
        <template #start>
          <div v-if="payments.length > 0">
            <h3 class="font-bold">
              Payments for {{ band.name }}
            </h3>
          </div>
        </template>
        <template #end>
          <IconField>
            <InputIcon>
              <i class="pi pi-search" />
            </InputIcon>
            <InputText
              v-model="paymentFilter"
              placeholder="Search"
              class="ml-2"
              @input="filterPayments"
            />
          </IconField>
        </template>
      </Toolbar>
      <DataTable
        :value="band.payments"
        striped-rows
        row-hover
        :paginator="true"
        :rows="20"
        class="cursor-pointer"
        @row-click="(event)=>{gotoPayment(event.data, band)}"
      >
        <Column
          field="payable_name"
          header="Booking Name"
          :sortable="true"
        />
        <Column
          field="payable_date"
          header="Booking Date"
          :sortable="true"
        />
        <Column
          field="name"
          header="Payment Name"
          :sortable="true"
        />
        <Column
          field="formattedPaymentAmount"
          header="Amount"
          :sortable="true"
        >
          <template #body="value">
            ${{ value.data.formattedPaymentAmount }}
          </template>
        </Column>
        <Column 
          field="formattedPaymentDate"
          header="Payment Date"
          :sortable="true"
        />
      </DataTable>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3'
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';

const props = defineProps({
    payments: {
      type: Object,
      required: true
    }
});

const paymentFilter = ref('');

const filterPayments = () => {
  if (!paymentFilter.value) {
    return props.payments;
  }
  return props.payments.map(band => ({
    ...band,
    payments: band.payments.filter(payment =>
      payment.payable_name.toLowerCase().includes(paymentFilter.value.toLowerCase()) ||
      payment.name.toLowerCase().includes(paymentFilter.value.toLowerCase()) ||
      payment.formattedPaymentAmount.toString().includes(paymentFilter.value) ||
      payment.formattedPaymentDate.toLowerCase().includes(paymentFilter.value.toLowerCase())
    )
  }));
}

const filteredPayments = computed(() => filterPayments());

const gotoPayment = (data,band) => {
    const url = route('Booking Finances', {band: band.id, booking:data.payable_id});
    router.get(url);
}
</script>

<style>

</style>