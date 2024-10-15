<template>
  <div class="bg-white dark:bg-slate-800 border block p-2 my-2 shadow-lg">
    <event-header
      :eventkey="event.key"
      :name="event.title"
      :type="eventType"
      :date="event.date"
    />
    <event-body :event="event" />
    <event-footer :event="event" />
  </div>
</template> 
<script setup>
import { computed } from 'vue'
import eventHeader from './Event/Card/Header.vue'
import eventBody from './Event/Card/Body.vue'
import eventFooter from './Event/Card/Footer.vue'
import { useStore } from 'vuex';

const props = defineProps({
  event: {
    type: Object,
    required: true
  }
});

const store = useStore();
const eventType = computed(() => {
    const eventTypes = store.getters['eventTypes/getAllEventTypes']
    const foundEvent = eventTypes.find(eventType => eventType.id === props.event.event_type_id)
    return foundEvent ? foundEvent.name : 'Unknown'
})

</script>