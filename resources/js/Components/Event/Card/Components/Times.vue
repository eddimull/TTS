<template>
  <div class="max-w-md mx-auto bg-white shadow-lg rounded-lg overflow-hidden">
    <ul class="divide-y divide-gray-200">
      <li v-for="(time, index) in sortedTimes" :key="time.title" class="relative">
        <div class="flex items-center p-4">
          <div class="flex-shrink-0 w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
            <span class="text-white font-semibold text-lg">{{ index + 1 }}</span>
          </div>
          <div class="ml-4 flex-grow">
            <p class="text-sm font-medium text-gray-900">{{ time.title }}</p>
            <p class="text-sm text-gray-500">{{ formatTime(time.time) }}</p>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  times: {
    type: Array,
    required: true
  }
});

const sortedTimes = computed(() => {
  return [...props.times].sort((a, b) => new Date(a.time) - new Date(b.time));
});

const formatTime = (timeString) => {
  const date = new Date(timeString);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};
</script>