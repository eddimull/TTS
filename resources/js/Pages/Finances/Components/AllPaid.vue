<template>
  <div>
    <div
      v-for="(band,index) in filteredServices"
      :key="index"
      class="card my-4"
    >
      <Toolbar class="p-mb-4 border-b-2">
        <template #start>
          <div>
            <h3 class="font-bold">
              Paid Services for {{ band.name }}
            </h3>
          </div>
        </template>
        <template #end>
          <IconField>
            <InputIcon>
              <i class="pi pi-search" />
            </InputIcon>
            <InputText
              v-model="serviceFilter"
              placeholder="Search"
              class="ml-2"
            />
          </IconField>
        </template>
      </Toolbar>
      <DataTable
        :value="band.paidBookings"
        striped-rows
        row-hover
        :paginator="true"
        :rows="20"
        class="cursor-pointer"
        @row-click="(event)=>{gotoPayment(event.data, band)}"
      >
        <Column
          field="name"
          header="Booking Name"
          :sortable="true"
        />
        <Column 
          field="price"
          header="Price"
          :sortable="true"
        >
          <template #body="value">
            ${{ value.data.price }}
          </template>
        </Column>
        <Column
          field="amount_paid"
          header="Amount Paid"
          :sortable="true"
        >
          <template #body="slotProps">
            <div class="relative w-full h-8 bg-gray-300 rounded">
              <div
                class="absolute top-0 left-0 h-full rounded"
                :style="{
                  width: `${getPaymentPercentage(slotProps.data)}%`,
                  backgroundColor: getPaymentColor(slotProps.data)
                }"
              />
              <div class="absolute top-0 left-0 w-full h-full flex items-center justify-center text-white text-stroke">
                ${{ slotProps.data.amount_paid }} / ${{ slotProps.data.price }}
              </div>
            </div>
          </template>
        </Column>
        <Column
          field="date"
          header="Booking Date"
          :sortable="true"
        />
        <template #empty>
          <div class="p-4 text-center">
            No paid services found.
          </div>
        </template>
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
      paid: {
      type: Array,
      required: true
      }
  });
  
  const serviceFilter = ref('');
  
  const filteredServices = computed(() => {
    if (!serviceFilter.value) {
      return props.paid;
    }
    return props.paid.map(band => ({
      ...band,
      paidBookings: band.paidBookings.filter(paid =>
        paid.amount_paid.toString().toLowerCase().includes(serviceFilter.value.toLowerCase()) ||
        paid.name.toLowerCase().includes(serviceFilter.value.toLowerCase()) ||
        paid.price.toString().includes(serviceFilter.value) ||
        paid.date.toLowerCase().includes(serviceFilter.value.toLowerCase())
      )
    }));
  });
  
  
  const getPaymentPercentage = (booking) => {
    return (booking.amount_paid / booking.price) * 100;
  }
  
  const getPaymentColor = (booking) => {
    const paymentRatio = booking.amount_paid / booking.price;
    if (paymentRatio === 0) return 'rgb(255, 0, 0)'; // Red
    if (paymentRatio === 1) return 'rgb(0, 255, 0)'; // Green
    
    // Calculate the gradient color
    const red = Math.round(255 * (1 - paymentRatio));
    const green = Math.round(255 * paymentRatio);
    return `rgb(${red}, ${green}, 0)`;
  }
  
  const gotoPayment = (data,band) => {
      const url = route('Booking Finances', {band: band.id, booking:data.id});
      router.get(url);
  }
  </script>
  <style scoped>
  .text-stroke {
    text-shadow: 
      -1px -1px 0 #333,
      1px -1px 0 #333,
      -1px 1px 0 #333,
      1px 1px 0 #333;
  }
  </style>