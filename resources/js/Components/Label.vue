<template>
  <label class="block font-medium text-sm text-gray-700 dark:text-gray-50 first-letter:uppercase">
    <span v-if="value">{{ value }}</span>
    <span v-else><slot /></span>
  </label>
</template>

<script>
    export default {
        props: ['value']
    }
</script>
