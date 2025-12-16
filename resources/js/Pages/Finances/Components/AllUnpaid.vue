<template>
  <div>
    <FilterableBookingsTable
      v-for="(band, index) in props.unpaid"
      :key="index"
      :band="band"
      :bookings="band.unpaidBookings"
      title="Unpaid Services"
      empty-message="No unpaid services found."
      :stats="getStats(band.unpaidBookings)"
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

const getStats = (bookings) => {
    const totalDue = bookings.reduce((total, booking) => {
        const price = parseFloat(booking.price) || 0;
        const amountPaid = parseFloat(booking.amount_paid) || 0;
        return total + (price - amountPaid);
    }, 0);

    const totalPaid = bookings.reduce((total, booking) => {
        const amountPaid = parseFloat(booking.amount_paid) || 0;
        return total + amountPaid;
    }, 0);

    return [
        {
            label: 'Number of Bookings',
            value: bookings.length,
            colorClass: 'text-blue-600 dark:text-blue-400',
        },
        {
            label: 'Total Due',
            value: `$${totalDue.toFixed(2)}`,
            colorClass: 'text-green-600 dark:text-green-400',
        },
        {
            label: 'Amount Paid',
            value: `$${totalPaid.toFixed(2)}`,
            colorClass: 'text-orange-600 dark:text-orange-400',
        },
    ];
};
</script>
