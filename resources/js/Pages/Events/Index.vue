<template>
  <breeze-authenticated-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Events
      </h2>
    </template>
    <event-list :events="events" />
    <div
      v-if="false"
      class="md:container md:mx-auto"
    >
      <div class="container">
        <a
          href="/events/create"
          class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
        >Create Event</a>
        <div class="shadow overflow-hidden rounded border-b border-gray-200">
          <table class="min-w-full bg-white m-5 rounded">
            <thead class="bg-gray-800 text-white">
              <tr>
                <th scope="w-1/3 text-center py-3 uppercase font-semibold text-sm">
                  Name
                </th>
                <th scope="w-1/3 text-center py-3 uppercase font-semibold text-sm">
                  Venue
                </th>
                <th scope="w-1/3 text-center py-3 uppercase font-semibold text-sm">
                  Date
                </th>
                <th scope="w-1/3 text-center py-3 uppercase font-semibold text-sm">
                  Edit
                </th>
              </tr>
            </thead>
            <tbody class="text-gray-700">
              <tr
                v-for="(event,index) in events"
                :key="event.id"
                :class="{'bg-gray-100': index % 2 === 0, 'border-b': true, 'hover:bg-gray-50':true }"
              >
                <td class="w-1/4 text-center py-3 px-4">
                  <Link :href="`/events/${event.key}/advance`">
                    {{ event.title }} ({{ event.eventType.name }})
                  </Link>
                </td>
                <td class="w-1/4 text-center py-3 px-4">
                  {{ event.title }}
                </td>
                <td class="w-1/4 text-center py-3 px-4">
                  {{ formatDate(event.date) }}
                </td>
                <td class="w-1/4">
                  <Link
                    :dusk="event.title.replace(/ /g,'_')"
                    class="border bg-white hover:bg-blue-500 rounded p-1"
                    :href="`/events/${event.event_key}/edit`"
                  >
                    Edit
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </breeze-authenticated-layout>
</template>

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
    import moment from 'moment';
    import EventList from './EventList.vue';
    export default {
        components: {
            BreezeAuthenticatedLayout,
            EventList
        },
        props:['events','successMessage'],
        methods:{
            formatDate(date){
                return moment(String(date)).format('MM/DD/YYYY')
            }
        }
    }
</script>
