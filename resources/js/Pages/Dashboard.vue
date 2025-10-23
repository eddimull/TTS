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
      <!-- Mobile Jump to Date and Search -->
      <div class="lg:hidden mb-4 p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Jump to Date</label>
            <input
              v-model="selectedDate"
              type="month"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
              @change="jumpToMonth"
            >
          </div>
          <div>
            <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Find by Name</label>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search events..."
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
              @input="filterEvents"
            >
          </div>
        </div>
        <div
          v-if="searchQuery && filteredEventsList.length > 0"
          class="mt-3 max-h-48 overflow-y-auto border border-gray-300 dark:border-gray-600 rounded-md"
        >
          <button
            v-for="event in filteredEventsList"
            :key="event.id || event.key"
            class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 border-b border-gray-200 dark:border-gray-700 last:border-b-0"
            @click="selectEvent(event)"
          >
            <div class="text-sm font-medium text-gray-900 dark:text-white">
              {{ event.title }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
              {{ formatEventDate(event.date) }}
            </div>
          </button>
        </div>
        <div
          v-if="searchQuery && filteredEventsList.length === 0"
          class="mt-3 text-sm text-gray-500 dark:text-gray-400"
        >
          No events found
        </div>
      </div>
      
      <div
        v-for="event in events"
        :id="'event_' + (event.id || event.key)"
        :key="event.id || event.key"
        :ref="'event_' + (event.id || event.key)"
      >
        <event-card
          :event="event"
          @edit-rehearsal="openRehearsalEditor"
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
        <!-- Go to Date/Month and Search -->
        <div class="mt-4 p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
          <div class="mb-3">
            <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Jump to Date</label>
            <input
              v-model="selectedDate"
              type="month"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
              @change="jumpToMonth"
            >
          </div>
          <div>
            <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Find by Name</label>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search events..."
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
              @input="filterEvents"
            >
          </div>
          <div
            v-if="searchQuery && filteredEventsList.length > 0"
            class="mt-3 max-h-64 overflow-y-auto border border-gray-300 dark:border-gray-600 rounded-md"
          >
            <button
              v-for="event in filteredEventsList"
              :key="event.id || event.key"
              class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 border-b border-gray-200 dark:border-gray-700 last:border-b-0"
              @click="selectEvent(event)"
            >
              <div class="text-sm font-medium text-gray-900 dark:text-white">
                {{ event.title }}
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ formatEventDate(event.date) }}
              </div>
            </button>
          </div>
          <div
            v-if="searchQuery && filteredEventsList.length === 0"
            class="mt-3 text-sm text-gray-500 dark:text-gray-400"
          >
            No events found
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Back to Top Button -->
  <transition name="fade">
    <button
      v-if="showBackToTop"
      class="fixed bottom-8 right-8 z-50 p-4 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg transition-all duration-300 hover:scale-110"
      aria-label="Back to top"
      @click="scrollToTop"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="h-6 w-6"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M5 10l7-7m0 0l7 7m-7-7v18"
        />
      </svg>
    </button>
  </transition>

  <!-- Mobile Jump to Date (Bottom) -->
  <transition name="fade">
    <div
      v-if="showBackToTop"
      class="lg:hidden fixed bottom-8 left-8 z-50 bg-white dark:bg-gray-800 p-3 rounded-lg shadow-lg"
    >
      <input
        v-model="selectedDate"
        type="month"
        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white text-sm"
        @change="jumpToMonth"
      >
    </div>
  </transition>

  <!-- Rehearsal Editor Modal -->
  <rehearsal-editor-modal
    v-model:visible="showRehearsalEditor"
    :rehearsal="selectedRehearsal"
    :band="currentBand"
    :schedule="selectedSchedule"
    :event-types="eventTypes"
    :available-bookings="upcomingBookings"
    @saved="onRehearsalSaved"
  />
</template>

<script setup>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
    import DefaultComponent from '../Components/DefaultDashboard.vue'
    import EventCard from '../Components/EventCard.vue'
    import SideCalendar from '../Components/Dashboard/SideCalendar.vue'
    import RehearsalEditorModal from '../Components/Rehearsal/RehearsalEditorModal.vue'
    import { nextTick, onMounted, onUnmounted, ref } from 'vue';
    import { router, usePage } from '@inertiajs/vue3';
    
    const props = defineProps({
      events: {
        type: Array,
        default: () => []
      },
      stats: {
        type: Array,
        default: () => []
      }
    });

    defineOptions({
      layout: BreezeAuthenticatedLayout,
    })

    const page = usePage();

    // Rehearsal editor state
    const showRehearsalEditor = ref(false);
    const selectedRehearsal = ref(null);
    const selectedSchedule = ref(null);
    const currentBand = ref(null);
    const eventTypes = ref([]);
    const upcomingBookings = ref([]);

    // Back to top button state
    const showBackToTop = ref(false);
    
    // Go to date/month state
    const selectedDate = ref('');

    // Search state
    const searchQuery = ref('');
    const filteredEventsList = ref([]);

    // Scroll tracking
    let scrollTimeout = null;

    const gotoDate = (identifier) => {
      const el = document.querySelector(`#event_${identifier}`);
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
          history.pushState(null, null, `#event_${identifier}`);
      }
      else {
          location.hash = `#event_${identifier}`;
      }
    };

    const scrollToTop = () => {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    };

    const jumpToMonth = () => {
      if (!selectedDate.value) return;
      
      const [year, month] = selectedDate.value.split('-');
      const targetDate = new Date(year, month - 1, 1);
      
      // Find the first event in the selected month
      const eventInMonth = props.events.find(event => {
        const eventDate = new Date(event.date);
        return eventDate.getFullYear() === targetDate.getFullYear() && 
               eventDate.getMonth() === targetDate.getMonth();
      });
      
      if (eventInMonth) {
        gotoDate(eventInMonth.id || eventInMonth.key);
      }
    };

    const filterEvents = () => {
      if (!searchQuery.value) {
        filteredEventsList.value = [];
        return;
      }
      
      const query = searchQuery.value.toLowerCase();
      filteredEventsList.value = props.events.filter(event => {
        return event.title?.toLowerCase().includes(query) ||
               event.venue_name?.toLowerCase().includes(query) ||
               event.client_name?.toLowerCase().includes(query);
      });
    };

    const selectEvent = (event) => {
      gotoDate(event.id || event.key);
      
      // Set the selected date to the event's month
      const eventDate = new Date(event.date);
      const year = eventDate.getFullYear();
      const month = String(eventDate.getMonth() + 1).padStart(2, '0');
      selectedDate.value = `${year}-${month}`;
      
      searchQuery.value = '';
      filteredEventsList.value = [];
    };

    const formatEventDate = (dateString) => {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', { 
        weekday: 'short', 
        month: 'short', 
        day: 'numeric',
        year: 'numeric'
      });
    };

    const handleScroll = () => {
      // Show/hide back to top button
      showBackToTop.value = window.scrollY > 300;
      
      // Debounce hash update on scroll
      if (scrollTimeout) {
        clearTimeout(scrollTimeout);
      }
      
      scrollTimeout = setTimeout(() => {
        updateHashOnScroll();
      }, 150);
    };

    const updateHashOnScroll = () => {
      if (!props.events.length) return;
      
      const header = document.querySelector('nav');
      const headerHeight = header ? header.offsetHeight : 0;
      const offset = headerHeight + 100; // Add some buffer
      
      // Find the event that's currently in view
      for (const event of props.events) {
        const el = document.querySelector(`#event_${event.id || event.key}`);
        if (el) {
          const rect = el.getBoundingClientRect();
          // Check if the event is in the viewport (considering header offset)
          if (rect.top <= offset && rect.bottom >= offset) {
            const newHash = `#event_${event.id || event.key}`;
            if (window.location.hash !== newHash) {
              if (history.pushState) {
                history.pushState(null, null, newHash);
              } else {
                window.location.hash = newHash;
              }
            }
            break;
          }
        }
      }
    };

    // Store event date for scroll restoration
    const eventDateBeforeEdit = ref(null);

    const openRehearsalEditor = async (event) => {
      // Store the event date so we can navigate back to it after save
      eventDateBeforeEdit.value = event.date;
      
      // Check if this is a virtual rehearsal (not yet saved)
      if (event.is_virtual && !event.eventable_id) {
        // This is a schedule-generated rehearsal that hasn't been saved yet
        // We'll create a new rehearsal from this virtual event
        try {
          // Fetch the schedule and other data needed for creating a new rehearsal
          const response = await fetch(route('api.rehearsal-schedule.get', { 
            rehearsal_schedule_id: event.rehearsal_schedule_id,
            band_id: event.band_id
          }));
          
          if (response.ok) {
            const data = await response.json();
            selectedRehearsal.value = null; // No rehearsal yet, we're creating one
            selectedSchedule.value = data.schedule;
            currentBand.value = data.band;
            eventTypes.value = data.eventTypes;
            upcomingBookings.value = data.upcomingEvents;
            
            // Pre-populate the modal with virtual event data
            selectedRehearsal.value = {
              venue_name: event.venue_name,
              venue_address: event.venue_address,
              notes: event.notes || '',
              is_cancelled: false,
              events: [{
                title: event.title,
                event_type_id: event.event_type_id,
                date: event.date,
                time: event.time,
                notes: event.notes || '',
              }],
              associations: [],
            };
            
            showRehearsalEditor.value = true;
          } else {
            console.error('Failed to fetch rehearsal schedule data');
          }
        } catch (error) {
          console.error('Error fetching rehearsal schedule:', error);
        }
      } else if (event.eventable_type === 'App\\Models\\Rehearsal' && event.eventable_id) {
        // This is an existing saved rehearsal
        try {
          // Fetch full rehearsal data with associations
          const response = await fetch(route('api.rehearsal.get', { 
            rehearsal_id: event.eventable_id 
          }));
          
          if (response.ok) {
            const data = await response.json();
            selectedRehearsal.value = data.rehearsal;
            selectedSchedule.value = data.schedule;
            currentBand.value = data.band;
            eventTypes.value = data.eventTypes;
            upcomingBookings.value = data.upcomingEvents;
            showRehearsalEditor.value = true;
          } else {
            console.error('Failed to fetch rehearsal data');
          }
        } catch (error) {
          console.error('Error fetching rehearsal:', error);
        }
      }
    };

    const onRehearsalSaved = () => {
      // Reload the current page to refresh the events
      router.reload({ 
        only: ['events'],
        preserveScroll: true,
        onSuccess: () => {
          // Navigate back to the event with the same date after reload
          if (eventDateBeforeEdit.value) {
            nextTick(() => {
              setTimeout(() => {
                // Find the event with the matching date
                const matchingEvent = props.events.find(e => e.date === eventDateBeforeEdit.value);
                if (matchingEvent) {
                  gotoDate(matchingEvent.id || matchingEvent.key);
                }
                eventDateBeforeEdit.value = null;
              }, 150); // Slightly longer delay to ensure DOM is fully updated
            });
          }
        }
      });
    };

    onMounted(()=> {
      // Add scroll listener
      window.addEventListener('scroll', handleScroll);
      
      // Handle initial hash navigation
      if(window.location.hash.includes('event_'))
      {
        const identifier = window.location.hash.replace('#event_','');
        nextTick(() => {
          setTimeout(()=>{
            gotoDate(identifier);
          },100) // scroll to the item that includes the offset after 100ms. 
        })        
      }
    })

    onUnmounted(() => {
      // Clean up scroll listener
      window.removeEventListener('scroll', handleScroll);
      if (scrollTimeout) {
        clearTimeout(scrollTimeout);
      }
    });
</script>
<style scoped>
.card{
    background-color: var(--surface-card);
    padding: 1.5rem;
    margin-bottom: 1rem;
    border-radius: 12px;
    box-shadow: 0 3px 5px rgba(0,0,0,.02),0 0 2px rgba(0,0,0,.05),0 1px 4px rgba(0,0,0,.08)!important;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
