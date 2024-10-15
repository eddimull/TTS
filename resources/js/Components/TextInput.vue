<template>
  <div class="createEventInput">
    <p class="text-gray-600 dark:text-gray-50">
      <label :for="props.name">{{ label }}</label>
    </p>
    <div class="mb-4">
      <input
        :id="props.name"
        v-model="model"
        type="text"
        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
        :placeholder="props.placeholder"
      >
    </div>
    <slot name="append" />
  </div>
</template>
<script setup>
    const model = defineModel()
    const props = defineProps({
        name: {
            type: String,
            required: true
        },
        label: {
            type: String,
            required: false,
            default: ''
        },
        placeholder: {
            type: String,
            required: false,
            default: ''
        }
    })

</script>