<template>
  <div class="grid grid-cols-1 content-center">
    <ul>
      <li class="p-2">
        Venue: <strong>{{ event.venue_name }}</strong>
      </li>
      <li class="p-2">
        Location: <strong v-if="event.venue_address">{{ event.venue_address }} </strong><strong
          v-else
          class="text-red-500"
        >
          No address provided
        </strong>
      </li>
      <Times :times="event.additional_data?.times" />
      <li
        v-if="event.notes !== null"
        class="p-2"
      >
        Notes: <div
          style="background-color: rgb(244 244 245);"
          class="ml-3 p-3 shadow-lg rounded break-normal content-container"
          v-html="event.notes"
        />
      </li>
      <li
        v-if="event.event_type?.name === 'Wedding'"
        class="p-2"
      >
        Wedding songs:
        <ul
          style="background-color: rgb(244 244 245);"
          class="list-outside indent-1 ml-3 p-3 shadow-lg rounded"
        >
          <li class="mt-2 pl-3">
            First Dance: <strong>{{ event.first_dance }}</strong>
          </li>
          <li class="mt-2 pl-3">
            Father Daughter: <strong>{{ event.father_daughter }}</strong>
          </li>
          <li class="mt-2 pl-3">
            Mother Son: <strong>{{ event.mother_groom }}</strong>
          </li>
          <li class="mt-2 pl-3">
            Money Dance: <strong>{{ event.money_dance }}</strong>
          </li>
          <li class="mt-2 pl-3">
            Bouquet/Garter: <strong>{{ event.bouquet_garter }}</strong>
          </li>
        </ul>
      </li>
      <li v-if="event.colorway_text">
        Attire: <div
          style="background-color: rgb(244 244 245);"
          class="ml-3 p-3 shadow-lg rounded break-normal"
          v-html="event.colorway_text"
        />
      </li>
      <Contacts
        :contacts="event.contacts"
      />
    </ul>
  </div>
</template>

<script setup>
import Times from './Components/Times.vue'
import Contacts from './Components/Contacts.vue';

const props = defineProps(['event', 'type']);

</script>