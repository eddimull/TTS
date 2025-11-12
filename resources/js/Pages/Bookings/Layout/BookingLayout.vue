<template>
  <BreezeAuthenticatedLayout>
    <container>
      <NavSubmenu
        :routes="filteredRoutes"
        :booking="booking"
      /> 
      <slot />
    </container>
  </BreezeAuthenticatedLayout>
</template>
    
<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
import { computed } from "vue"
import { Ziggy } from '@/ziggy'
import NavSubmenu from '@/Components/NavSubmenu.vue';

const props = defineProps({
  booking: Object
})

const excludeRoutes = ['Create Booking', 'Booking Receipt', 'Download Booking Contract', 'bookings.history', 'bookings.historyJson', 'View Booking Contract', 'portal.booking.payment']
const filteredRoutes = computed(() => {
  return Object.entries(Ziggy.routes).reduce((acc, [name, route]) => {
    if (route.uri.includes('booking/') && 
    !excludeRoutes.includes(name) &&
    route.methods.includes('GET')) {
      acc[name] = route
    }
    return acc
  }, {})
})
</script>