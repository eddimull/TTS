<template>
  <div class="grid grid-cols-1 content-center">
    <ul>
      <li class="p-2">
        Venue: <strong>{{ event.venue_name }}</strong>
      </li>
      <li class="p-2">
        Location: <strong v-if="event.city">{{ event.city }}, </strong> <strong>{{ event.state.state_name }}</strong>
      </li>
      <li class="p-2">
        Load In times:

        <ul class="list-outside indent-1">
          <li class="mt-2 pl-3">
            Production: <strong>{{ toTime(event.production_loadin_time) }}</strong>
          </li>
          <li class="mt-2 pl-3">
            Rhythm: <strong>{{ toTime(event.rhythm_loadin_time) }}</strong>
          </li>
          <li class="mt-2 pl-3">
            Production: <strong>{{ toTime(event.band_loadin_time) }}</strong>
          </li>
        </ul>
      </li>
      <li
        v-if="event.notes !== null"
        class="p-2"
      >
        Notes: <div
          style="background-color: rgb(244 244 245);"
          class="ml-3 p-3 shadow-lg rounded"
          v-html="event.notes"
        />
      </li>
      <li v-if="event.colorway_text">
        Attire: <div
          style="background-color: rgb(244 244 245);"
          class="ml-3 p-3 shadow-lg rounded"
          v-html="event.colorway_text"
        />
      </li>
    </ul>
    <!-- {{ event }} -->
  </div>
</template>

<script>
import moment from 'moment'
export default {
    props:['event'],
    methods:{
      toTime(time){
        return moment(time).format('h:mm A')
      }
    }
}
</script>