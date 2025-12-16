<template>
  <div>
    <FilterableBookingsTable
      v-for="(band, index) in props.paid"
      :key="index"
      :band="band"
      :bookings="band.paidBookings"
      title="Paid Services"
      empty-message="No paid services found."
      :stats="getStats(band.paidBookings)"
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

const getStats = (bookings) => {
    const totalRevenue = bookings.reduce((total, booking) => {
        const price = parseFloat(booking.price) || 0;
        return total + price;
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
            label: 'Total Revenue',
            value: `$${totalRevenue.toFixed(2)}`,
            colorClass: 'text-green-600 dark:text-green-400',
        },
        {
            label: 'Total Paid',
            value: `$${totalPaid.toFixed(2)}`,
            colorClass: 'text-purple-600 dark:text-purple-400',
        },
    ];
};
</script>
