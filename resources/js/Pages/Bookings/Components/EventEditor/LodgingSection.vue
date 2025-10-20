<template>
  <div>
    <div class="grid grid-cols-1 gap-4">
      <div
        v-for="(value, key) in modelValue.additional_data.lodging"
        :key="key"
      >
        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">{{ value.title }}</label>
        <input
          v-model="modelValue.additional_data.lodging[key].data"
          :type="modelValue.additional_data.lodging[key].type"
          :class="{
            'form-checkbox h-5 w-5 text-blue-600':
              modelValue.additional_data.lodging[key].type ===
              'checkbox',
            'w-full p-2 border rounded dark:bg-slate-700 dark:text-gray-50':
              modelValue.additional_data.lodging[key].type ===
              'text',
          }"
        >
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
});
</script>
