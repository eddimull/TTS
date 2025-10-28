<template>
  <div class="mt-4">
    <Editor
      v-model="modelValue.additional_data.attire"
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
