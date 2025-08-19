<template>
  <div>
    <Label
      v-if="label"
      :for="inputId"
    >{{ label }}</Label>
    <input
      :id="inputId"
      ref="input"
      :class="['border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-slate-700 dark:text-gray-50', $attrs.class]"
      :value="modelValue"
      v-bind="$attrs"
      @input="$emit('update:modelValue', $event.target.value)"
    >
  </div>
</template>

<script>
import Label from './Label.vue';
    export default {
        components: { Label },
        inheritAttrs: false,
        props: ['modelValue', 'label'],
        emits: ['update:modelValue'],
        computed: {
            inputId() {
                return this.label ? `input-${Math.random().toString(36).substr(2, 9)}` : undefined
            }
        },
        methods: {
            focus() {
                this.$refs.input.focus()
            }
        }
    }
</script>