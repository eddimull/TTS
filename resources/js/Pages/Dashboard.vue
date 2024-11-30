<template>
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
        :id="'event_' + event.id"
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
          :events="events"
          @date="gotoDate"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
    import DefaultComponent from '../Components/DefaultDashboard.vue'
    import EventCard from '../Components/EventCard.vue'
    import SideCalendar from '../Components/Dashboard/SideCalendar.vue'
    import { nextTick, onMounted } from 'vue';
    
    const props = defineProps(['events','stats']);

    defineOptions({
      layout: BreezeAuthenticatedLayout,
    })

    const gotoDate = (id) => {
      const el = document.querySelector(`#event_${id}`);
      const header = document.querySelector('nav'); // Adjust this selector to match your header
      
      // Get the actual header height
      const headerHeight = header ? header.offsetHeight : 0;
      
      // Add a small additional padding if desired
      const additionalPadding = 20; 
      const offset = headerHeight + additionalPadding;
      
      const elementPosition = el.getBoundingClientRect().top;
      const offsetPosition = elementPosition + window.scrollY - offset;
      window.scrollTo({
        top: offsetPosition,
        behavior: "smooth"
      })
      if(history.pushState) {
          history.pushState(null, null, `#event_${id}`);
      }
      else {
          location.hash = `#event_${id}`;
      }
    };

    onMounted(()=> {
      if(window.location.hash.includes('event_'))
      {
        const event_id = window.location.hash.replace('#event_','');
        nextTick(() => {
          setTimeout(()=>{
            gotoDate(event_id);
          },100) // scroll to the item that includes the offset after 100ms. 
        })        
      }
    })
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
