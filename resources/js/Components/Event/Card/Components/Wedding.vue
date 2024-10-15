<template>
    <div class="bg-gray-100 dark:bg-slate-700 list-outside ml-4 p-4 shadow-lg rounded-lg ">
        Dances:
      <ul>
        <li v-for="dance in wedding?.dances" class="mt-2 pl-2" :key="dance.name">
          <span class="text-gray-700 dark:text-gray-50">{{ dance.title }}:</span>
          <strong class="ml-1 text-gray-900 dark:text-gray-100">{{ dance.data }}</strong>
        </li>
      </ul>
        <div class="mt-2">
            <strong class="text-gray-700 dark:text-slate-50">On-Site:</strong>
            <strong class="ml-1 text-gray-900 dark:text-white">{{ onSite ? 'Yes' : 'No' }}</strong>
        </div>
    </div>
  </template>
  
  <script setup>
  const props = defineProps({
    wedding: {
      type: Object,
      required: true
    },
    'onSite':{
        type: Boolean,
        required: true
    }
  });
  </script>