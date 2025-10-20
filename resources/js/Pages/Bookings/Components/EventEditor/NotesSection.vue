<template>
  <div>
    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
    <Editor
      v-model="modelValue.notes"
      class="w-full p-2 border rounded"
      editor-style="height: 320px"
    />
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
