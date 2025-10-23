<template>
  <div 
    :class="[
      'border block p-2 my-2 shadow-lg relative',
      event.is_cancelled 
        ? 'bg-red-50 dark:bg-red-900 border-red-300 dark:border-red-700 border-2' 
        : event.is_virtual
          ? 'bg-blue-50 dark:bg-blue-900 border-blue-200 dark:border-blue-700'
          : 'bg-white dark:bg-slate-800',
      'dark:text-white'
    ]"
  >
    <!-- Edit/Add Notes to Rehearsal Button (Top Right) -->
    <button
      v-if="isRehearsal && canEditRehearsal"
      class="absolute top-2 right-2 p-2 text-gray-600 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 transition-colors"
      :title="event.eventable_id ? 'Edit Rehearsal' : 'Add Notes to Rehearsal'"
      @click.prevent="$emit('edit-rehearsal', event)"
    >
      <svg
        v-if="event.eventable_id"
        class="w-5 h-5"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
        />
      </svg>
      <!-- Plus/Add icon for virtual rehearsals -->
      <svg
        v-else
        class="w-5 h-5"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M12 4v16m8-8H4"
        />
      </svg>
    </button>

    <div
      v-if="event.is_cancelled"
      class="mb-2 px-3 py-1 bg-red-500 text-white text-sm font-bold rounded inline-block"
    >
      CANCELLED
    </div>
    <div
      v-else-if="isRehearsal && event.rehearsal_schedule_name"
      class="mb-2 px-3 py-1 bg-purple-500 text-white text-sm font-bold rounded inline-block"
    >
      {{ event.rehearsal_schedule_name }}
    </div>
    <event-header
      :eventkey="event.key || event['key'] || 'no-key'"
      :name="isRehearsal && event.rehearsal_schedule_name ? 'Rehearsal' : (event.title || event['title'])"
      :type="eventType"
      :date="event.date || event['date']"
      :eventable-type="event.eventable_type"
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

defineEmits(['edit-rehearsal']);

const store = useStore();

const eventType = computed(() => {
    const eventTypes = store.getters['eventTypes/getAllEventTypes']
    const foundEvent = eventTypes.find(eventType => eventType.id === props.event.event_type_id)
    return foundEvent ? foundEvent.name : 'Unknown'
})

// Check if this is a rehearsal (virtual or saved)
const isRehearsal = computed(() => {
    // Virtual rehearsals from schedule
    if (props.event.is_virtual) return true;
    // Saved rehearsals
    if (props.event.eventable_type === 'App\\Models\\Rehearsal') return true;
    return false;
});

// Check if user can edit rehearsals - get navigation from Vuex store
const canEditRehearsal = computed(() => {
    const navigation = store.state.user.navigation;
    return navigation && navigation.Rehearsals && navigation.Rehearsals.write === true;
});

</script>