<template>
  <BookingForm
    :booking="booking"
    :band="band"
  />
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import BookingForm from './Components/BookingForm.vue'

defineOptions({
  layout: BreezeAuthenticatedLayout,
})

const props = defineProps({
  booking: Object,
  band: Object,
})
</script>