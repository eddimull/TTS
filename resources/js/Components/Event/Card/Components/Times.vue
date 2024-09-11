<template>
  <div>
    <li class="p-2">
      Load In times:
      <ul
        style="background-color: rgb(244 244 245);"
        class="list-outside indent-1 ml-3 p-3 shadow-lg rounded"
      >
        <li
          v-for="(time, key) in loadInTimes"
          :key="key"
          class="mt-2 pl-3"
        >
          {{ formatLabel(key) }}: <strong>{{ formatTime(time) }}</strong>
        </li>
      </ul>
    </li>
    <li
      v-if="Object.keys(otherTimes).length > 0"
      class="p-2"
    >
      Other times:
      <ul
        style="background-color: rgb(244 244 245);"
        class="list-outside indent-1 ml-3 p-3 shadow-lg rounded"
      >
        <li
          v-for="(time, key) in otherTimes"
          :key="key"
          class="mt-2 pl-3"
        >
          {{ formatLabel(key) }}: <strong>{{ formatTime(time) }}</strong>
        </li>
      </ul>
    </li>
  </div>
</template>
  
  <script setup>
  import { computed } from 'vue';
  
  const props = defineProps({
    times: {
      type: Object,
      required: true
    }
  });
  
  const loadInTimes = computed(() => 
    Object.entries(props.times)
      .filter(([key]) => key.includes('loadin'))
      .reduce((acc, [key, value]) => ({ ...acc, [key]: value }), {})
  );
  
  const otherTimes = computed(() => 
    Object.entries(props.times)
      .filter(([key]) => !key.includes('loadin'))
      .reduce((acc, [key, value]) => ({ ...acc, [key]: value }), {})
  );
  
  const formatTime = (timeString) => {
    const date = new Date(timeString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  };
  
  const formatLabel = (key) => {
    return key
      .split('_')
      .map(word => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ');
  };
  </script>