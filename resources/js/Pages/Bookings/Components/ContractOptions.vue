<template>
  <div>
    <label
      for="contractOptions"
      class="block text-sm font-medium text-gray-700"
    >Contract Options</label>
    <SelectButton
      id="contractOptions"
      v-model="model"
      :options="options"
      option-label="label"
      option-value="value"
      aria-labelledby="contractOptions"
    />
  </div>
</template>
  
  <script setup>
  import { defineModel } from 'vue';
  import SelectButton from 'primevue/selectbutton';
  
  const model = defineModel();
  
  const options = [
    { label: 'Automatic', value: 'default' },
    { label: 'None', value: 'none' },
    { label: 'External', value: 'external' }
  ];
  </script>