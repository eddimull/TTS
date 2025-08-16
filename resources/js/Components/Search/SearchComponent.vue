<template>
  <div class="relative">
    <div class="relative">
      <!-- Close button for overlay mode -->
      <div v-if="isOverlay" class="absolute inset-y-0 left-0 pl-3 flex items-center">
        <button
          @click="$emit('close')"
          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 focus:outline-none"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      
      <!-- Search icon for normal mode -->
      <div v-if="!isOverlay" class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
        <svg
          class="h-5 w-5 text-gray-400"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
            clip-rule="evenodd"
          />
        </svg>
      </div>
      
      <input
        v-model="searchQuery"
        id="everythingSearchInput"
        type="text"
        :placeholder="isOverlay ? 'Search bookings, contacts, events...' : 'Search bookings, contacts, events...'"
        :class="[
          'block w-full pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm',
          isOverlay ? 'pl-10 text-lg' : 'pl-10',
          { 'pr-10': searchQuery.length > 0 }
        ]"
        @input="handleSearch"
        @focus="showResults = true"
        @click="$event.stopPropagation()"
        @keydown.escape="handleEscape"
        @keydown.arrow-down="navigateDown"
        @keydown.arrow-up="navigateUp"
        @keydown.enter="selectResult"
        ref="searchInput"
      >
      
      <!-- Clear Button -->
      <div
        v-if="searchQuery.length > 0 && !loading"
        class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-auto"
      >
        <button
          @mousedown.prevent
          @click="clearSearch"
          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 focus:outline-none"
          title="Clear search"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      
      <div
        v-if="loading"
        class="absolute inset-y-0 right-0 pr-3 flex items-center"
      >
        <svg
          class="animate-spin h-5 w-5 text-gray-400"
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
      </div>
    </div>

    <!-- Search Results Dropdown -->
    <div
      v-if="showResults && (hasResults || loading)"
      :class="[
        'absolute z-50 mt-1 bg-white dark:bg-gray-800 shadow-lg rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm transition-all duration-200',
        isOverlay ? 'max-h-[32rem] w-full' : (expandedView ? 'max-h-[32rem] w-[32rem] max-w-[90vw]' : 'max-h-96 w-full')
      ]"
    >
      <div v-if="loading" class="px-4 py-2 text-center">
        <span class="text-gray-500 dark:text-gray-400">Searching...</span>
      </div>
      
      <div v-else-if="hasResults">
        <!-- Expand/Collapse Toggle -->
        <div class="sticky top-0 bg-white dark:bg-gray-800 px-4 py-2 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
              {{ totalResults }} result{{ totalResults !== 1 ? 's' : '' }}
            </span>
            <button
              @click="expandedView = !expandedView"
              class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 flex items-center"
            >
              <span>{{ expandedView ? 'Collapse' : 'Expand' }}</span>
              <svg
                :class="['ml-1 h-4 w-4 transform transition-transform', expandedView ? 'rotate-180' : '']"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Bookings Results -->
        <div v-if="results.bookings?.length > 0">
          <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide bg-gray-50 dark:bg-gray-700">
            Bookings
          </div>
          <div
            v-for="(booking, index) in results.bookings"
            :key="`booking-${booking.id}`"
            :class="[
              'cursor-pointer px-4 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors',
              selectedIndex === getResultIndex('booking', index) ? 'bg-gray-100 dark:bg-gray-700' : '',
              expandedView ? 'py-4' : 'py-2'
            ]"
            @click="selectBooking(booking)"
          >
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="h-8 w-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                  <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                </div>
              </div>
              <div class="ml-3 flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100" :class="expandedView ? '' : 'truncate'">
                  {{ booking.name }}
                </p>
                <p v-if="!expandedView" class="text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDate(booking.date) }}
                </p>                
                <p class="text-sm text-gray-500 dark:text-gray-400" :class="expandedView ? '' : 'truncate'">
                  {{ booking.venue_name }} • {{ formatDate(booking.date) }}
                </p>
                <div v-if="expandedView" class="mt-2 space-y-1">
                  <p v-if="booking.band_name" class="text-xs text-gray-600 dark:text-gray-300">
                    Band: {{ booking.band_name }}
                  </p>
                  <p v-if="booking.status" class="text-xs">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                          :class="getStatusClass(booking.status)">
                      {{ booking.status }}
                    </span>
                  </p>
                  <p v-if="booking.notes" class="text-xs text-gray-600 dark:text-gray-300">
                    Notes: {{ booking.notes }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Contacts Results -->
        <div v-if="results.contacts?.length > 0">
          <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide bg-gray-50 dark:bg-gray-700">
            Contacts
          </div>
          <div
            v-for="(contact, index) in results.contacts"
            :key="`contact-${contact.id}`"
            :class="[
              'cursor-pointer px-4 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors',
              selectedIndex === getResultIndex('contact', index) ? 'bg-gray-100 dark:bg-gray-700' : '',
              expandedView ? 'py-4' : 'py-2'
            ]"
            @click="selectContact(contact)"
          >
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="h-8 w-8 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                  <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                  </svg>
                </div>
              </div>
              <div class="ml-3 flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100" :class="expandedView ? '' : 'truncate'">
                  {{ contact.name }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400" :class="expandedView ? '' : 'truncate'">
                  {{ contact.email }}
                </p>
                <div v-if="expandedView" class="mt-2 space-y-1">
                  <p v-if="contact.phone" class="text-xs text-gray-600 dark:text-gray-300">
                    Phone: {{ contact.phone }}
                  </p>
                  <p v-if="contact.company" class="text-xs text-gray-600 dark:text-gray-300">
                    Company: {{ contact.company }}
                  </p>
                  <p v-if="contact.role" class="text-xs text-gray-600 dark:text-gray-300">
                    Role: {{ contact.role }}
                  </p>
                  <p v-if="contact.last_contacted" class="text-xs text-gray-500 dark:text-gray-400">
                    Last contacted: {{ formatDate(contact.last_contacted) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Charts Results -->
        <div v-if="results.charts?.length > 0">
          <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide bg-gray-50 dark:bg-gray-700">
            Charts
          </div>
          <div
            v-for="(chart, index) in results.charts"
            :key="`chart-${chart.id}`"
            :class="[
              'cursor-pointer px-4 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors',
              selectedIndex === getResultIndex('chart', index) ? 'bg-gray-100 dark:bg-gray-700' : '',
              expandedView ? 'py-4' : 'py-2'
            ]"
            @click="selectChart(chart)"
          >
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="h-8 w-8 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                  <svg class="h-4 w-4 text-purple-600 dark:text-purple-400" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                  </svg>
                </div>
              </div>
              <div class="ml-3 flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100" :class="expandedView ? '' : 'truncate'">
                  {{ chart.title }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400" :class="expandedView ? '' : 'truncate'">
                  by {{ chart.composer }}
                </p>
                <div v-if="expandedView" class="mt-2 space-y-1">
                  <p v-if="chart.arranger" class="text-xs text-gray-600 dark:text-gray-300">
                    Arranged by: {{ chart.arranger }}
                  </p>                  
                  <p v-if="chart.description" class="text-xs text-gray-600 dark:text-gray-300">
                    {{ chart.description }}
                  </p>
                  <div v-if="chart.uploads?.length > 0" class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                    <svg class="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                    {{ chart.uploads.length }} file{{ chart.uploads.length !== 1 ? 's' : '' }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- No Results -->
        <div v-if="searchQuery && !hasResults" class="px-4 py-2 text-center">
          <span class="text-gray-500 dark:text-gray-400">No results found for "{{ searchQuery }}"</span>
        </div>
      </div>
    </div>

    <!-- Backdrop to close search -->
    <div
      v-if="showResults"
      class="fixed inset-0 z-40"
      @click="closeSearch"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  isOverlay: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close'])

const searchInput = ref(null)
const searchQuery = ref('')
const results = ref({})
const loading = ref(false)
const showResults = ref(false)
const selectedIndex = ref(-1)
const searchTimer = ref(null)
const expandedView = ref(false)

const hasResults = computed(() => {
  return Object.values(results.value).some(resultArray => resultArray?.length > 0)
})

const totalResults = computed(() => {
  let total = 0
  if (results.value.bookings) total += results.value.bookings.length
  if (results.value.contacts) total += results.value.contacts.length
  if (results.value.charts) total += results.value.charts.length
  return total
})

const handleSearch = () => {
  if (searchTimer.value) {
    clearTimeout(searchTimer.value)
  }

  if (searchQuery.value.trim().length < 2) {
    results.value = {}
    showResults.value = false
    return
  }

  searchTimer.value = setTimeout(() => {
    performSearch()
  }, 300)
}

const performSearch = async () => {
  loading.value = true
  selectedIndex.value = -1
  
  try {
    const response = await axios.get('/api/search', {
      params: { q: searchQuery.value }
    })
    
    results.value = response.data
    showResults.value = true
  } catch (error) {
    console.error('Search error:', error)
    results.value = {}
  } finally {
    loading.value = false
  }
}

const getResultIndex = (type, index) => {
  let resultIndex = 0
  
  if (type === 'booking') {
    return index
  }
  
  if (type === 'contact') {
    resultIndex += (results.value.bookings?.length || 0)
    return resultIndex + index
  }
  
  if (type === 'chart') {
    resultIndex += (results.value.bookings?.length || 0)
    resultIndex += (results.value.contacts?.length || 0)
    return resultIndex + index
  }
  
  return resultIndex
}

const navigateDown = () => {
  if (selectedIndex.value < totalResults.value - 1) {
    selectedIndex.value++
  }
}

const navigateUp = () => {
  if (selectedIndex.value > 0) {
    selectedIndex.value--
  } else {
    selectedIndex.value = -1
  }
}

const selectResult = () => {
  if (selectedIndex.value === -1) return
  
  let currentIndex = 0
  
  // Check bookings
  if (results.value.bookings) {
    if (selectedIndex.value < currentIndex + results.value.bookings.length) {
      const booking = results.value.bookings[selectedIndex.value - currentIndex]
      selectBooking(booking)
      return
    }
    currentIndex += results.value.bookings.length
  }
  
  // Check contacts
  if (results.value.contacts) {
    if (selectedIndex.value < currentIndex + results.value.contacts.length) {
      const contact = results.value.contacts[selectedIndex.value - currentIndex]
      selectContact(contact)
      return
    }
    currentIndex += results.value.contacts.length
  }
  
  // Check charts
  if (results.value.charts) {
    if (selectedIndex.value < currentIndex + results.value.charts.length) {
      const chart = results.value.charts[selectedIndex.value - currentIndex]
      selectChart(chart)
      return
    }
  }
}

const selectBooking = (booking) => {
  router.visit(route('Booking Details', [booking.band_id, booking.id]))
}

const selectContact = (contact) => {
  const booking = contact.booking || contact.bookings?.[0]
  router.visit(route('Booking Contacts', [booking.band_id, booking.id]));
}

const selectChart = (chart) => {
  // closeSearch()
  router.visit(route('charts.edit', chart.id))
}

const handleEscape = () => {
  console.log('escaping');
  if (props.isOverlay) {
    // emit('close')
  } else {
    closeSearch()
  }
}

const closeSearch = () => {
  showResults.value = false
  if (props.isOverlay) {
    // emit('close')
  }
}

const clearSearch = () => {
  searchQuery.value = ''
  results.value = {}
  showResults.value = false
  selectedIndex.value = -1
  if (searchTimer.value) {
    clearTimeout(searchTimer.value)
  }
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const getStatusClass = (status) => {
  const statusClasses = {
    'confirmed': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    'pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    'cancelled': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    'completed': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
  }
  return statusClasses[status?.toLowerCase()] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
}

// Auto-focus when overlay opens
onMounted(() => {
  if (props.isOverlay) {
    searchInput.value?.focus()
  }
})

// Watch for route changes to close search
watch(() => router.page, () => {
  // closeSearch()
})
</script>
