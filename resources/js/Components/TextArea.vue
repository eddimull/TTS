<template>
  <div>
    <Label
      v-if="label"
      :for="props.name"
    >{{ label }}</Label>
    <div class="mb-4">
      <textarea
        :id="props.name"
        v-model="model"
        rows="3"
        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:bg-slate-700 dark:text-gray-50 leading-tight focus:outline-none focus:shadow-outline resize-vertical"
        :placeholder="props.placeholder"
      />
    </div>
    <slot name="append" />
  </div>
</template>
<script setup>
import Label from './Label.vue';
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