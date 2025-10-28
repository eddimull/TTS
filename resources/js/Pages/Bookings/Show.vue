<template>
  <BookingDetails
    v-if="!isEditMode"
    :booking="booking"
    :band="band"
    :recent-activities="recentActivities"
  />
  <BookingForm
    v-else
    :booking="booking"
    :band="band"
  />
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import BookingLayout from './Layout/BookingLayout.vue'
import BookingDetails from './Components/BookingDetails.vue'
import BookingForm from './Components/BookingForm.vue'

defineOptions({
  layout: BookingLayout,
})

const props = defineProps({
  booking: {
    type: Object,
    required: true,
  },
  band: {
    type: Object,
    required: true,
  },
  recentActivities: {
    type: Array,
    default: () => [],
  },
})

const page = usePage()

// Check if edit mode is active via URL query parameter
const isEditMode = computed(() => {
  if (typeof window !== 'undefined') {
    const urlParams = new URLSearchParams(window.location.search)
    return urlParams.get('edit') === 'true'
  }
  return false
})
</script>