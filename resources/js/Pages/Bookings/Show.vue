<template>
  <BookingForm
    :booking="booking"
    :band="band"
  />
</template>

<script setup>
import BookingLayout from './Layout/BookingLayout.vue'
import BookingForm from './Components/BookingForm.vue'

defineOptions({
  layout: BookingLayout,
})

const props = defineProps({
  booking: Object,
  band: Object,
})
</script>