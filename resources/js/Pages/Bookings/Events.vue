<template>
    <EventList :initial-events="events" :booking="booking"/>
</template>
<script setup>
import EventList from './Components/EventList.vue';
import BookingLayout from './Layout/BookingLayout.vue';


defineOptions({
    layout: BookingLayout
})

const props = defineProps({
    booking: Object,
    events: Object
})
</script>