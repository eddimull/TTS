<template>
  <div class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-slate-700 rounded">
    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 rounded-full flex items-center justify-center text-xs font-semibold flex-shrink-0">
      {{ initials }}
    </div>
    <div class="flex-1 min-w-0">
      <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
        {{ member.name }}
      </div>
      <div
        v-if="member.role"
        class="text-xs text-gray-600 dark:text-gray-400 truncate"
      >
        {{ member.role }}
      </div>
    </div>
    <span
      v-if="member.attendance"
      class="text-xs px-2 py-0.5 rounded-full flex-shrink-0"
      :class="attendanceClass"
    >
      {{ attendanceLabel }}
    </span>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  member: {
    type: Object,
    required: true
  }
});

const initials = computed(() => {
  if (!props.member.name) return '?';
  const parts = props.member.name.split(' ');
  if (parts.length >= 2) {
    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
  }
  return props.member.name.substring(0, 2).toUpperCase();
});

const attendanceClass = computed(() => {
  const classes = {
    confirmed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    attended: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    absent: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    excused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
  };
  return classes[props.member.attendance] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
});

const attendanceLabel = computed(() => {
  if (!props.member.attendance) return '';
  return props.member.attendance.charAt(0).toUpperCase() + props.member.attendance.slice(1);
});
</script>
