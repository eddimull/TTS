<template>
  <breeze-authenticated-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Dashboard
      </h2>
    </template>
    <default-component v-if="events.length == 0" />
    <div
      v-else
      class="w-full grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-5 gap-6"
    >
      <div class="hidden xl:block">
        &nbsp;
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
  </breeze-authenticated-layout>
</template>

<script>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
    import DefaultComponent from '../Components/DefaultDashboard.vue'
    import EventCard from '../Components/EventCard.vue'
    import SideCalendar from '../Components/Dashboard/SideCalendar.vue'
    export default {
        components: {
            BreezeAuthenticatedLayout,
            EventCard,
            DefaultComponent,
            SideCalendar
        },
        props:['events','stats'],
        methods:{
            gotoDate(id){
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
