<template>
  <div class="mt-4">
    <textarea
      v-model="attireContent"
      class="w-full p-4 text-base border-2 border-gray-300 dark:border-gray-600 rounded-lg
             focus:ring-2 focus:ring-blue-500 focus:border-blue-500
             dark:bg-slate-800 dark:text-gray-50 resize-none"
      placeholder="Add attire details here..."
      rows="10"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const attireContent = computed({
    get() {
        return props.modelValue.additional_data?.attire || '';
    },
    set(value) {
        const updatedEvent = { ...props.modelValue };
        if (!updatedEvent.additional_data) {
            updatedEvent.additional_data = {};
        }
        updatedEvent.additional_data = { ...updatedEvent.additional_data, attire: value };
        emit('update:modelValue', updatedEvent);
    }
});
</script>
