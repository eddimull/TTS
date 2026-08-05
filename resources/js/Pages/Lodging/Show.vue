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
    default: () => ({}),
  },
  canWrite: {
    type: Boolean,
    default: false,
  },
});
</script>

<template>
  <div>
    <h1>{{ lodging?.name }}</h1>
  </div>
</template>
