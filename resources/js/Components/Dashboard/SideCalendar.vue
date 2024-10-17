<template>
  <DatePicker
    :dates="dates"
    :show-time="false"
    :step-minute="15"
    hour-format="12"
    inline
  >
    <template #date="{ date }">
      <strong
        v-if="isReserved(date)"
        :title="getEventName(date)"
        class="rounded-full h-24 w-24 flex items-center justify-center bg-blue-300"
        @click="setEventId(date)"
      >
        {{ date.day }}
      </strong>
      <template v-else>
        {{ date.day }}
      </template>
    </template>
  </DatePicker>
</template>

<script setup>
import { DateTime } from 'luxon';
import DatePicker from 'primevue/datepicker';

const props = defineProps({
  events: {
    type: Object,
    default: () => ({})
  }
});
const emit = defineEmits(['date']);

const dates = { ...props.events };

const parseDate = (date) => {
  const dateString = `${date.year}-${String(date.month + 1).padStart(2, '0')}-${String(date.day).padStart(2, '0')}`;
  return DateTime.fromFormat(dateString, 'yyyy-MM-dd');
};

const isReserved = (date) => {
  const jsDate = parseDate(date);
  return Object.values(props.events).some(event => 
    DateTime.fromFormat(event.date, 'yyyy-MM-dd').equals(jsDate)
  );
};

const getEventName = (date) => {
  const jsDate = parseDate(date);
  const event = Object.values(props.events).find(event => 
    DateTime.fromFormat(event.date, 'yyyy-MM-dd').equals(jsDate)
  );
  return event ? event.event_name : '';
};

const setEventId = (date) => {
  const jsDate = parseDate(date);
  const event = Object.values(props.events).find(event => 
    DateTime.fromFormat(event.date, 'yyyy-MM-dd').equals(jsDate)
  );
  if (event) {
    emit('date', event.id);
  }
};
</script>