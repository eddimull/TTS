<template>
  <default-component v-if="localEvents.length == 0" />
  <div
    v-else
    class="w-full grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-5 gap-6"
  >
    <div class="hidden xl:block">
        &nbsp;
    </div>
    <div class="col-span-2">
      <!-- Scroll to Load Older Indicator (shows when near top) -->
      <div
        v-show="showScrollLoadIndicator && !isLoadingOlder"
        class="flex items-center justify-center mb-4 transition-all duration-300"
        :style="{ 
          opacity: scrollLoadOpacity
        }"
      >
        <div class="bg-blue-600 dark:bg-blue-500 text-white px-4 py-2 rounded-full shadow-lg flex items-center gap-2">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5 animate-bounce"
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
          <span class="text-sm font-medium">Scroll up to load older events</span>
        </div>
      </div>
      
      <!-- Pull to Refresh Indicator (shows in the gap) -->
      <div
        v-show="(pullDistance > 0 || !isPulling) && !isLoadingOlder"
        class="flex items-center justify-center pull-indicator"
        :class="{ 'pull-releasing': !isPulling }"
        :style="{ 
          height: `${Math.min(pullDistance, pullThreshold + 20)}px`, 
          opacity: Math.min(pullDistance / pullThreshold, 1)
        }"
      >
        <div class="bg-blue-600 dark:bg-blue-500 text-white px-4 py-2 rounded-full shadow-lg flex items-center gap-2">
          <svg
            v-if="pullDistance < pullThreshold"
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5 transition-transform duration-200"
            :style="{ transform: `rotate(${(pullDistance / pullThreshold) * 180}deg)` }"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M19 14l-7 7m0 0l-7-7m7 7V3"
            />
          </svg>
          <svg
            v-else
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M5 13l4 4L19 7"
            />
          </svg>
          <span class="text-sm font-medium">
            {{ pullDistance < pullThreshold ? 'Pull to load older events' : 'Release to load' }}
          </span>
        </div>
      </div>
      
      <!-- Events Container -->
      <div>
        <!-- Loading Indicator for Older Events -->
        <transition name="fade">
          <div
            v-if="isLoadingOlder"
            class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg flex items-center justify-center gap-3"
          >
            <svg
              class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              />
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              />
            </svg>
            <span class="text-sm font-medium text-blue-700 dark:text-blue-300">Loading older events...</span>
          </div>
        </transition>
      
        <div
          v-if="!canLoadMore && localEvents.length > 0"
          class="mb-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg text-center text-sm text-gray-600 dark:text-gray-400"
        >
          No more events to load
        </div>
      
        <div
          v-for="event in localEvents"
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
      <!-- End events container with pull transform -->
    </div>
    <div class="hidden lg:block py-2 mx-auto">
      <div
        class="sticky"
        style="top:100px"
      >
        <side-calendar
          :events="localEvents"
          @date="gotoDate"
        />
      </div>
    </div>
  </div>

  <!-- Floating Action Buttons -->
  <transition name="fade">
    <div
      v-if="showBackToTop"
      class="fixed bottom-8 right-8 z-50 flex flex-col gap-3"
    >
      <!-- Search Button -->
      <button
        class="p-4 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg transition-all duration-300 hover:scale-110"
        aria-label="Search events"
        @click="showSearchModal = true"
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
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
          />
        </svg>
      </button>
      
      <!-- Back to Today Button -->
      <button
        class="p-4 bg-green-600 hover:bg-green-700 text-white rounded-full shadow-lg transition-all duration-300 hover:scale-110"
        :aria-label="scrollDirection === 'down' ? 'Jump to today (scroll down)' : 'Jump to today (scroll up)'"
        @click="scrollToToday()"
      >
        <!-- Single arrow that rotates based on direction -->
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-6 w-6 transition-transform duration-500 ease-in-out"
          :class="{ 'rotate-180': scrollDirection === 'up' }"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M19 14l-7 7m0 0l-7-7m7 7V3"
          />
        </svg>
      </button>
    </div>
  </transition>

  <!-- Search Modal -->
  <transition name="fade">
    <div
      v-if="showSearchModal"
      class="fixed inset-0 z-50 flex items-start justify-center pt-20 px-4"
      @click.self="showSearchModal = false"
    >
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full p-6 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Navigate Events
          </h3>
          <button
            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            @click="showSearchModal = false"
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
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>
        
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Jump to Date</label>
            <side-calendar
              class="w-full"
              :events="localEvents"
              @date="gotoDate"
            />
          </div>
          
          <div>
            <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Search by Name</label>
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
            class="max-h-64 overflow-y-auto border border-gray-300 dark:border-gray-600 rounded-md"
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
            class="text-sm text-gray-500 dark:text-gray-400 text-center py-2"
          >
            No events found
          </div>
        </div>
      </div>
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
import { pull } from 'lodash'
    
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

    // Create a local reactive copy of events that we can mutate
    const localEvents = ref([...props.events]);

    // Rehearsal editor state
    const showRehearsalEditor = ref(false);
    const selectedRehearsal = ref(null);
    const selectedSchedule = ref(null);
    const currentBand = ref(null);
    const eventTypes = ref([]);
    const upcomingBookings = ref([]);

    // Back to top button state
    const showBackToTop = ref(false);
    const scrollDirection = ref('down'); // 'up' or 'down' - indicates direction to today
    
    // Go to date/month state
    const selectedDate = ref('');

    // Search state
    const searchQuery = ref('');
    const filteredEventsList = ref([]);
    const showSearchModal = ref(false);

    // Scroll tracking
    let scrollTimeout = null;
    let lastScrollY = 0;

    // Pull to refresh state
    let touchStartY = 0;
    let touchCurrentY = 0;
    const pullThreshold = 80; // Distance in pixels to trigger refresh
    const pullDistance = ref(0); // Visual feedback for pull distance
    const isPulling = ref(false); // Track if user is actively pulling

    // Scroll to load state
    const showScrollLoadIndicator = ref(false);
    const scrollLoadOpacity = ref(0);
    const scrollLoadThreshold = 300; // Distance from top to start showing indicator
    const scrollLoadTrigger = 50; // Distance from top to trigger load

    // Infinite scroll state
    const isLoadingOlder = ref(false);
    const canLoadMore = ref(true);
    const oldestEventDate = ref(null);
    const hasLoadedOlderEvents = ref(false);
    const isInitialized = ref(false);

    const gotoDate = (identifier) => {
      const el = document.querySelector(`#event_${identifier}`);
      
      // If element doesn't exist, skip (elements not rendered yet)
      if (!el) {
        return;
      }
      
      const header = document.querySelector('nav'); // Adjust this selector to match your header
      
      // Get the actual header height
      const headerHeight = header ? header.offsetHeight : 0;
      
      // Add a small additional padding if desired
      const additionalPadding = 0; 
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
      
      // Close the search modal if it's open
      showSearchModal.value = false;
    };

    const scrollToTop = () => {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    };

    const updateScrollDirection = () => {
      // Find the first event that is today or in the future
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      
      const todayOrFutureEvent = localEvents.value.find(event => {
        const eventDate = new Date(event.date);
        eventDate.setHours(0, 0, 0, 0);
        return eventDate >= today;
      });
      
      if (!todayOrFutureEvent) {
        scrollDirection.value = 'up'; // All events are in the past
        return;
      }
      
      // Get the position of the "today" event
      const el = document.querySelector(`#event_${todayOrFutureEvent.id || todayOrFutureEvent.key}`);
      if (el) {
        const rect = el.getBoundingClientRect();
        const viewportMiddle = window.innerHeight / 2;
        
        // If the "today" event is below the middle of the viewport, we need to scroll down
        // If it's above, we need to scroll up
        scrollDirection.value = rect.top > viewportMiddle ? 'down' : 'up';
      }
    };

    const scrollToToday = () => {
      // Find the first event that is today or in the future
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      
      const todayOrFutureEvent = localEvents.value.find(event => {
        const eventDate = new Date(event.date);
        eventDate.setHours(0, 0, 0, 0);
        return eventDate >= today;
      });
      
      if (todayOrFutureEvent) {
        gotoDate(todayOrFutureEvent.id || todayOrFutureEvent.key);
      } else {
        // If no future events, just scroll to the bottom
        window.scrollTo({
          top: document.documentElement.scrollHeight,
          behavior: 'smooth'
        });
      }
    };

    const jumpToMonth = () => {
      if (!selectedDate.value) return;
      
      const [year, month] = selectedDate.value.split('-');
      const targetDate = new Date(year, month - 1, 1);
      
      // Find the first event in the selected month
      const eventInMonth = localEvents.value.find(event => {
        const eventDate = new Date(event.date);
        return eventDate.getFullYear() === targetDate.getFullYear() && 
               eventDate.getMonth() === targetDate.getMonth();
      });
      
      if (eventInMonth) {
        gotoDate(eventInMonth.id || eventInMonth.key);
        showSearchModal.value = false;
      }
    };

    const filterEvents = () => {
      if (!searchQuery.value) {
        filteredEventsList.value = [];
        return;
      }
      
      const query = searchQuery.value.toLowerCase();
      filteredEventsList.value = localEvents.value.filter(event => {
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
      showSearchModal.value = false;
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
      const currentScrollY = window.scrollY;
      
      // Show/hide back to top button
      showBackToTop.value = currentScrollY > 300;
      
      // Update scroll direction based on position relative to "today"
      updateScrollDirection();
      
      // Handle scroll-to-load-older indicator and trigger
      if (isInitialized.value && canLoadMore.value && !isLoadingOlder.value) {
        // Show indicator when within threshold distance from top
        if (currentScrollY <= scrollLoadThreshold) {
          showScrollLoadIndicator.value = true;
          // Calculate opacity based on distance from top (closer = more opaque)
          scrollLoadOpacity.value = Math.min(1, (scrollLoadThreshold - currentScrollY) / scrollLoadThreshold);
          
          // Trigger load when very close to top
          if (currentScrollY <= scrollLoadTrigger && lastScrollY > scrollLoadTrigger) {
            loadOlderEvents();
          }
        } else {
          showScrollLoadIndicator.value = false;
          scrollLoadOpacity.value = 0;
        }
      } else {
        showScrollLoadIndicator.value = false;
        scrollLoadOpacity.value = 0;
      }
      
      // Update last scroll position for next comparison
      lastScrollY = currentScrollY;
      
      // Debounce hash update on scroll
      if (scrollTimeout) {
        clearTimeout(scrollTimeout);
      }
      
      scrollTimeout = setTimeout(() => {
        updateHashOnScroll();
      }, 150);
    };

    const handleTouchStart = (e) => {
      if (window.scrollY === 0) {
        touchStartY = e.touches[0].clientY;
        pullDistance.value = 0;
        isPulling.value = true; // Start pulling - disable transitions
      }
    };

    const handleTouchMove = (e) => {
      if (!isInitialized.value || isLoadingOlder.value || !canLoadMore.value) {
        return;
      }

      if (touchStartY > 0) {
        touchCurrentY = e.touches[0].clientY;
        const distance = touchCurrentY - touchStartY;
        
        // Always update pull distance based on current touch position
        // This allows dragging back up to reduce the distance even after threshold is met
        pullDistance.value = Math.max(0, distance);
      }

      if(pullDistance.value >= pullThreshold) {
        touchStartY = e.touches[0].clientY - pullThreshold;
      }
    };

    const handleTouchEnd = () => {
      if (!isInitialized.value || isLoadingOlder.value || !canLoadMore.value) {
        touchStartY = 0;
        touchCurrentY = 0;
        isPulling.value = false;
        setTimeout(() => {
          pullDistance.value = 0;
        }, 250);
        return;
      }

      if (window.scrollY === 0 && touchStartY > 0) {
        const distance = touchCurrentY - touchStartY;
        
        // Only load if pulled down more than threshold AND still at or above threshold when released
        // If user dragged back up before releasing, cancel the operation
        if (distance >= pullThreshold && pullDistance.value >= pullThreshold) {
          loadOlderEvents();
        }
      }
      
      // Reset touch tracking
      touchStartY = 0;
      touchCurrentY = 0;
      
      // First enable the transition by setting isPulling to false
      isPulling.value = false;
      
      // Then wait for the browser to apply the transition style before changing height
      // Using nextTick + setTimeout to ensure the class is applied first
      nextTick(() => {
        setTimeout(() => {
          pullDistance.value = 0;
        }, 20);
      });
    };

    const updateHashOnScroll = () => {
      if (!localEvents.value.length) return;
      
      const header = document.querySelector('nav');
      const headerHeight = header ? header.offsetHeight : 0;
      const offset = headerHeight + 100; // Add some buffer
      
      // Find the event that's currently in view
      for (const event of localEvents.value) {
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
          // Update local events with the new data
          localEvents.value = [...props.events];
          
          // Navigate back to the event with the same date after reload
          if (eventDateBeforeEdit.value) {
            nextTick(() => {
              setTimeout(() => {
                // Find the event with the matching date
                const matchingEvent = localEvents.value.find(e => e.date === eventDateBeforeEdit.value);
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

    const loadOlderEvents = async () => {
      if (isLoadingOlder.value || !canLoadMore.value || localEvents.value.length === 0) {
        return;
      }

      isLoadingOlder.value = true;

      // Get the oldest event date
      const beforeDate = oldestEventDate.value || localEvents.value[0]?.date;
      
      if (!beforeDate) {
        isLoadingOlder.value = false;
        canLoadMore.value = false;
        return;
      }

      try {
        const response = await fetch(route('dashboard.load-older') + `?before_date=${beforeDate}`);
        const data = await response.json();

        if (data.events && data.events.length > 0) {
          // Store current scroll position
          const currentScrollY = window.scrollY;
          const currentScrollHeight = document.documentElement.scrollHeight;

          // Prepend older events to the local events array
          localEvents.value.unshift(...data.events);

          // Update the oldest event date
          oldestEventDate.value = data.events[0].date;
          
          // Mark that we've loaded older events
          hasLoadedOlderEvents.value = true;

          // After DOM updates, restore scroll position
          nextTick(() => {
            const newScrollHeight = document.documentElement.scrollHeight;
            const heightDifference = newScrollHeight - currentScrollHeight;
            window.scrollTo(0, currentScrollY + heightDifference);
          });
        } else {
          // No more events to load
          canLoadMore.value = false;
        }
      } catch (error) {
        console.error('Error loading older events:', error);
      } finally {
        isLoadingOlder.value = false;
      }
    };

    onMounted(()=> {
      // Add scroll listener
      window.addEventListener('scroll', handleScroll);
      
      // Add touch listeners for pull-to-refresh on mobile
      window.addEventListener('touchstart', handleTouchStart, { passive: true });
      window.addEventListener('touchmove', handleTouchMove, { passive: true });
      window.addEventListener('touchend', handleTouchEnd, { passive: true });
      
      // Initialize oldest event date
      if (localEvents.value.length > 0) {
        oldestEventDate.value = localEvents.value[0].date;
      }
      
      // Initialize scroll direction
      nextTick(() => {
        updateScrollDirection();
      });
      
      // Handle initial hash navigation - wait for DOM to be fully rendered
      if(window.location.hash.includes('event_'))
      {
        const identifier = window.location.hash.replace('#event_','');
        // Wait for nextTick to ensure Vue has rendered, then add extra delay for DOM to settle
        nextTick(() => {
          setTimeout(()=>{
            gotoDate(identifier);
          }, 150) // scroll to the item that includes the offset after DOM is ready
        })        
      }
      
      // Mark as initialized after a short delay to prevent immediate loading
      setTimeout(() => {
        isInitialized.value = true;
      }, 500);
    })

    onUnmounted(() => {
      // Clean up scroll listener
      window.removeEventListener('scroll', handleScroll);
      
      // Clean up touch listeners
      window.removeEventListener('touchstart', handleTouchStart);
      window.removeEventListener('touchmove', handleTouchMove);
      window.removeEventListener('touchend', handleTouchEnd);
      
      if (scrollTimeout) {
        clearTimeout(scrollTimeout);
      }
    });
    

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

.pull-indicator {
  transition: none;
}

.pull-indicator.pull-releasing {
  transition: height 0.2s ease-out;
}

/* Modal backdrop */
.fixed.inset-0 {
  background-color: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
}
</style>
