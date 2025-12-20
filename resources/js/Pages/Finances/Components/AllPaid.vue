<template>
  <div>
    <FilterableBookingsTable
      v-for="(band, index) in props.paid"
      :key="index"
      :band="band"
      :bookings="band.paidBookings"
      title="Paid Services"
      empty-message="No paid services found."
    />
  </div>
</template>

<script setup>
import FilterableBookingsTable from '@/Components/Finances/FilterableBookingsTable.vue';

const props = defineProps({
    paid: {
        type: Array,
        required: true,
    },
});

</script>
