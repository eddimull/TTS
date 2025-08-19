<template>
  <div class="flex items-center cursor-pointer">
    <input
      :id="inputId"
      :checked="modelValue"
      type="checkbox"
      class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 cursor-pointer"
      @change="$emit('update:modelValue', $event.target.checked)"
    >
    <Label
      v-if="label"
      :for="inputId"
      class="ml-2 cursor-pointer"
    >{{ label }}</Label>
  </div>
</template>

<script>
import Label from './Label.vue';
export default {
    components: { Label },
    inheritAttrs: false,
    props: {
        modelValue: {
            type: Boolean,
            default: false,
        },
        label: {
            type: String,
            default: '',
        },
    },
    emits: ['update:modelValue'],

    computed: {
        inputId() {
            return this.label ? `input-${Math.random().toString(36).substr(2, 9)}` : undefined
        }
    },
}
</script>