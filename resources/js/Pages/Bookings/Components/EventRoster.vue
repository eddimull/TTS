<template>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
          <div
            v-for="(member, index) in attendingMembers"
            :key="index"
            class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-700 rounded-lg"
          >
            <div
              class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center flex-shrink-0"
            >
              <i class="pi pi-user text-blue-600 dark:text-blue-300" />
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-medium text-gray-900 dark:text-gray-50 truncate">
                {{ member.name }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400 truncate">
                {{ member.role || 'No role specified' }}
              </div>
            </div>
          </div>
        </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({
    roster_members: {
        type: Array,
        required: true
    }
});
const attendingMembers = computed(() => {
    return props.roster_members.filter(member => member.attendance === 'confirmed');
});
</script>