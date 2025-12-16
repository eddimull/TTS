<template>
  <span
    :class="badgeClasses"
  >
    {{ formattedStatus }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
    variant: {
        type: String,
        default: 'badge', // 'badge' or 'text'
        validator: (value) => ['badge', 'text'].includes(value),
    },
});

const statusConfig = {
    confirmed: {
        badge: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        text: 'text-green-600 dark:text-green-400 font-bold',
    },
    pending: {
        badge: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        text: 'text-blue-600 dark:text-blue-400 font-bold',
    },
    cancelled: {
        badge: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        text: 'text-red-600 dark:text-red-400 font-bold',
    },
    draft: {
        badge: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        text: 'text-gray-600 dark:text-gray-400 font-bold',
    },
    completed: {
        badge: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        text: 'text-green-600 dark:text-green-400 font-bold',
    },
};

const badgeClasses = computed(() => {
    const baseClass = props.variant === 'badge'
        ? 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full'
        : '';

    const statusClass = statusConfig[props.status]?.[props.variant] ||
        (props.variant === 'badge'
            ? 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
            : 'text-gray-600 dark:text-gray-400 font-bold');

    return [baseClass, statusClass].filter(Boolean).join(' ');
});

const formattedStatus = computed(() => {
    return props.status.charAt(0).toUpperCase() + props.status.slice(1);
});
</script>
