<script setup>
// Placeholder page component for Task 5 (web routes + controller).
// Full UI implementation lands in Task 6.
defineProps({
  band: {
    type: Object,
    default: () => ({}),
  },
  lodging: {
    type: Object,
    default: null,
  },
  bookings: {
    type: Array,
    default: () => [],
  },
  events: {
    type: Array,
    default: () => [],
  },
});
</script>

<template>
  <div>
    <h1>{{ lodging ? 'Edit' : 'New' }} Lodging</h1>
  </div>
</template>
