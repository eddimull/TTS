<template>
    <div class="bg-gray-100 dark:bg-slate-700 list-outside ml-4 p-4 shadow-lg rounded-lg ">
        Dances:
      <ul v-if="wedding?.dances && wedding.dances.length > 0" class="list-disc">
        <li v-for="dance in wedding?.dances" class="mt-2 ml-5 pl-2" :key="dance.name">
          <span class="text-gray-700 dark:text-gray-50">{{ dance.title }}:</span>
          <strong class="ml-1 text-gray-900 dark:text-gray-100">{{ dance.data }}</strong>
        </li>
       
      </ul>
      <div v-else>
        
          <strong class="text-gray-700 dark:text-gray-50">None</strong>
        
      </div>
        <div class="mt-2">
            <strong class="text-gray-700 dark:text-slate-50">On-Site:</strong>
            <strong class="ml-1 text-gray-900 dark:text-white">{{ wedding?.onsite ? 'Yes' : 'No' }}</strong>
        </div>
    </div>
  </template>
  
  <script setup>
  const props = defineProps({
    wedding: {
      type: Object,
      required: true
    }
  });
  </script>