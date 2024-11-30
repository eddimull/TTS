<template>
  <Container class="md:container md:mx-auto">
    <BookingCreateForm
      :band="band"
      :event-types="eventTypes"
    />
  </Container>
</template>
  
<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import BookingCreateForm from './Components/BookingCreation.vue'
export default {
components: {
    BookingCreateForm
},
layout: BreezeAuthenticatedLayout,
props: {
    band: Object,
    eventTypes: Array,
},
}
</script>