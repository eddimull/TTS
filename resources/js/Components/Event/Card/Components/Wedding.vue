<template>
    <div class="bg-zinc-100 list-outside ml-4 p-4 shadow-lg rounded-lg">
        Dances:
      <ul>
        <li v-for="dance in wedding?.dances" class="mt-2 pl-2" :key="dance.name">
          <span class="text-gray-700">{{ dance.title }}:</span>
          <strong class="ml-1 text-gray-900">{{ dance.data }}</strong>
        </li>
      </ul>
        <div class="mt-2">
            <strong class="text-gray-700">On-Site:</strong>
            <strong class="ml-1 text-gray-900">{{ onSite ? 'Yes' : 'No' }}</strong>
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