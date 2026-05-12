<template>
  <div class="text-sm text-gray-600 dark:text-gray-300 flex flex-wrap items-center gap-x-2 gap-y-1">
    <span>{{ eventCountLabel }}</span>
    <span aria-hidden="true">·</span>
    <span>{{ dateRangeLabel }}</span>
    <template v-if="venueLabel">
      <span aria-hidden="true">·</span>
      <span>{{ venueLabel }}</span>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatDate, formatDateRange, formatWeekday } from '@/utils/formatters';

const props = defineProps({
    booking: {
        type: Object,
        required: true,
    },
});

const eventCountLabel = computed(() => {
    const count = props.booking.event_count ?? props.booking.events?.length ?? 0;
    return `${count} ${count === 1 ? 'event' : 'events'}`;
});

const dateRangeLabel = computed(() => {
    const start = props.booking.start_date;
    const end = props.booking.end_date;
    if (!start) return 'No date';
    if (!end || start === end) return `${formatWeekday(start)} ${formatDate(start)}`;
    return formatDateRange(start, end);
});

const venueLabel = computed(() => props.booking.venue_summary || null);
</script>
