<template>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <Input
        id="title"
        v-model="modelValue.title"
        label="Title"
        type="text"
        class="w-full"
      />
    </div>
    <div>
      <Input
        id="date"
        v-model="modelValue.date"
        label="Date"
        type="date"
        class="w-full"
      />
    </div>
    <div>
      <Input
        id="time"
        v-model="modelValue.time"
        label="Show Time"
        type="time"
        class="w-full p-2 border dark:bg-slate-700 dark:text-gray-50 rounded"
      />
    </div>
  </div>
</template>

<script setup>
import Input from "@/Components/Input.vue";

defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
});
</script>
