<template>
    <EventList :initial-events="events" :booking="booking"/>
</template>
<script setup>
import EventList from './Components/EventList.vue';
import BookingLayout from './Layout/BookingLayout.vue';
import { useBandRealtime } from '@/composables/useBandRealtime';


defineOptions({
    layout: BookingLayout
})

const props = defineProps({
    booking: Object,
    events: Object
})

useBandRealtime(props.booking.band_id, {
    bookings: { props: ['booking'], when: (p) => p.id === props.booking.id },
    events: ['events'],
    event_member: ['events'],
})
</script>