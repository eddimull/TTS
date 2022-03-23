<template>
  <div class="bg-white border block p-2 my-2 shadow-lg">
    <event-header
      :name="event.event_name"
      :type="event.event_type.name"
      :date="event.event_time"
    />
    <event-body :event="event" />
    <event-footer :event="event" />
  </div>
</template>
<script>
import eventHeader from './Event/Card/Header.vue'
import eventBody from './Event/Card/Body.vue'
import eventFooter from './Event/Card/Footer.vue'
export default {
    components:{
        eventHeader,
        eventBody,
        eventFooter
    },
    props:['event']
}
</script>