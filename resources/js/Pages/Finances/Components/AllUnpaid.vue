<template>
  <div>
    <FilterableBookingsTable
      v-for="(band, index) in props.unpaid"
      :key="index"
      :band="band"
      :bookings="band.unpaidBookings"
      title="Unpaid Services"
      empty-message="No unpaid services found."
    />
  </div>
</template>

<script setup>
import FilterableBookingsTable from '@/Components/Finances/FilterableBookingsTable.vue';

const props = defineProps({
    unpaid: {
        type: Array,
        required: true,
    },
});
</script>
