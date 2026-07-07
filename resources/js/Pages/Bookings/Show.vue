<template>
  <BookingDetails
    v-if="!isEditMode"
    :booking="booking"
    :band="band"
    :recent-activities="recentActivities"
    :payout-config="payoutConfig"
    :payout-result="payoutResult"
    :questionnaire-instances="questionnaireInstances"
    :available-questionnaires="availableQuestionnaires"
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
import { useBandRealtime } from '@/composables/useBandRealtime'

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
  payoutConfig: {
    type: Object,
    default: null,
  },
  payoutResult: {
    type: Object,
    default: null,
  },
  questionnaireInstances: {
    type: Array,
    default: () => [],
  },
  availableQuestionnaires: {
    type: Array,
    default: () => [],
  },
})

useBandRealtime(props.band.id, {
  bookings: { props: ['booking', 'recentActivities'], when: (p) => p.id === props.booking.id },
  // Event values feed the estimated payout total, so event-family changes
  // refresh the estimate props alongside the nested booking data.
  events: ['booking', 'payoutResult'],
  event_member: ['booking', 'payoutResult'],
  payments: { props: ['booking'], when: (p) => p.parent?.id === props.booking.id },
  payout: { props: ['payoutResult', 'payoutConfig'], when: (p) => p.parent?.id === props.booking.id },
  payout_adjustment: { props: ['payoutResult', 'payoutConfig'], when: (p) => p.parent?.id === props.booking.id },
  band_payout_config: ['payoutResult', 'payoutConfig'],
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