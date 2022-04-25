<template>
  <breeze-authenticated-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Dashboard - {{ date }}
      </h2>
    </template>
    <default-component v-if="events.length == 0" />
    <div
      v-else
      class="w-full grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-5 gap-6"
    >
      <div class="hidden xl:block">
        <ul>
          Quick links
        </ul>
      </div>
      <div class="col-span-2">
        <div
          v-for="event in events"
          :key="event.id"
          :ref="'event_' + event.id"
        >
          <event-card
            
            :event="event"
          />
        </div>
      </div>
      <div class="hidden lg:block py-2 mx-auto">
        <div
          class="sticky"
          style="top:100px"
        >
          <side-calendar
            v-model="date"
            @date="gotoDate"
          />
        </div>
      </div>
    </div>
    <!-- <div class="flex-1 max-w-5xl p-16">
      <div class="grid grid cols-2 grid-rows-3 gap-4 grid-flow-row-dense">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <h4>Upcoming Events</h4>
            <ul>
              <li
                v-for="event in events"
                :key="event.id"
              >
                {{ event.event_name }} - {{ event.venue_name }} - <strong>{{ formatDate(event.event_time) }}</strong>
              </li>
            </ul>
          </div>
          <div class="p-6 bg-gray overflow-hidden shadow-sm sm:rounded-lg">
            <h4>Stats</h4>
            <p>Mileage for the year: {{ stats.miles }}</p>
          </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg">
          <Chart
            type="bar"
            :data="basicData"
          />
        </div>
      </div>
    </div> -->
  </breeze-authenticated-layout>
</template>

<script>

    import Calendar from 'primevue/calendar';
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
    import DefaultComponent from '../Components/DefaultDashboard.vue'
    import EventCard from '../Components/EventCard.vue'
    import SideCalendar from '../Components/Dashboard/SideCalendar.vue'
    import moment from 'moment';
    export default {
        components: {
            BreezeAuthenticatedLayout,
            Calendar,
            EventCard,
            DefaultComponent,
            SideCalendar
        },
        props:['events','stats'],
        data() {
          return {
            date: null
            
          }
        },
        methods:{
            formatDate:(date)=>{

                return moment(String(date)).format('MM/DD/YYYY')
            },
            gotoDate(id){
              // this.$refs
              // console.log(this.$refs);
              this.$refs[`event_${id}`].scrollIntoView({behavior: "smooth"});
            }
        }
    }
</script>
<style scoped>
.card{
    background-color: var(--surface-card);
    padding: 1.5rem;
    margin-bottom: 1rem;
    border-radius: 12px;
    box-shadow: 0 3px 5px rgba(0,0,0,.02),0 0 2px rgba(0,0,0,.05),0 1px 4px rgba(0,0,0,.08)!important;
}
</style>
