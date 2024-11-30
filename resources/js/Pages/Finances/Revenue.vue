<template>
  <FinanceLayout>
    <div class="m-4">
      <div
        v-for="band in revenue"
        :key="band.name"
      >
        <h2 class="underline text-2xl">
          {{ band.name }}
        </h2>
        <ul
          v-for="(payment,index) in band.payments"
          :key="index"
        >
          <li>{{ payment.year }}: {{ moneyFormat(payment.total/100) }}</li>
        </ul>
      </div>
    </div>
  </FinanceLayout>
</template>
    
<script setup>
  import FinanceLayout from './Layout/FinanceLayout.vue'

  const props = defineProps({
    revenue: {
      type: Object,
      required: true
    }
  })

  const moneyFormat = (number) => 
  {
      var formatter = new Intl.NumberFormat('en-US', {
          style: 'currency',
          currency: 'USD'
      });
      return formatter.format(number);
  }

</script>