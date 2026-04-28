<template>
  <div>
    <h1>{{ instance.name }}</h1>
  </div>
</template>

<script setup>
defineProps({
  booking: Object,
  instance: Object,
  fields: Array,
  responses: Object,
});
</script>
