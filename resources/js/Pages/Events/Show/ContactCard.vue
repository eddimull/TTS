<template>
  <div class="p-2 bg-gray-50 dark:bg-slate-700 rounded">
    <div class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-1">
      {{ contact.name }}
    </div>
    <div
      v-if="contact.email"
      class="text-xs text-gray-600 dark:text-gray-400 flex items-center gap-1.5"
    >
      <i class="pi pi-envelope" />
      <a
        :href="`mailto:${contact.email}`"
        class="hover:text-blue-600 dark:hover:text-blue-400"
      >
        {{ contact.email }}
      </a>
    </div>
    <div
      v-if="contact.phonenumber"
      class="text-xs text-gray-600 dark:text-gray-400 flex items-center gap-1.5"
    >
      <i class="pi pi-phone" />
      <a
        :href="`tel:${contact.phonenumber}`"
        class="hover:text-blue-600 dark:hover:text-blue-400"
      >
        {{ contact.phonenumber }}
      </a>
    </div>
  </div>
</template>

<script setup>
defineProps({
  contact: {
    type: Object,
    required: true
  }
});
</script>
