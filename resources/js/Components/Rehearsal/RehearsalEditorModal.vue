<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    :header="(rehearsal && rehearsal.id) ? 'Edit Rehearsal' : 'Add Notes to Rehearsal'"
    :style="{ width: '50rem' }"
    :breakpoints="{ '1199px': '75vw', '575px': '90vw' }"
    @hide="onHide"
  >
    <div class="p-4">
      <!-- Show full event/venue details only for saved rehearsals -->
      <template v-if="rehearsal && rehearsal.id">
        <!-- Rehearsal Summary (collapsed by default) -->
        <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900 rounded-lg">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <h3 class="text-lg font-semibold">
                {{ form.event_title || 'Rehearsal' }}
              </h3>
              <div class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                <div><strong>Date:</strong> {{ formatDisplayDate(form.event_date) }}</div>
                <div><strong>Time:</strong> {{ form.event_time }}</div>
                <div v-if="form.venue_name || schedule?.location_name">
                  <strong>Location:</strong> {{ form.venue_name || schedule?.location_name }}
                </div>
              </div>
            </div>
            <button
              type="button"
              class="ml-4 text-sm text-blue-600 dark:text-blue-400 hover:underline"
              @click="showEventDetails = !showEventDetails"
            >
              {{ showEventDetails ? 'Hide Details' : 'Edit Details' }}
            </button>
          </div>

          <!-- Collapsible Event & Venue Details -->
          <div
            v-if="showEventDetails"
            class="mt-4 pt-4 border-t border-blue-200 dark:border-blue-700"
          >
            <!-- Event Title -->
            <div class="mb-4">
              <label
                for="event_title"
                class="block text-sm font-medium mb-1"
              >Rehearsal Title *</label>
              <InputText
                id="event_title"
                v-model="form.event_title"
                type="text"
                class="w-full"
                placeholder="e.g., Weekly Band Practice, Pre-Wedding Rehearsal"
              />
              <small
                v-if="form.errors.event_title"
                class="p-error"
              >{{ form.errors.event_title }}</small>
            </div>

            <!-- Date and Time -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
              <div>
                <label
                  for="event_date"
                  class="block text-sm font-medium mb-1"
                >Date *</label>
                <Calendar
                  id="event_date"
                  v-model="eventDateObject"
                  date-format="yy-mm-dd"
                  class="w-full"
                  :min-date="new Date()"
                  show-icon
                />
                <small
                  v-if="form.errors.event_date"
                  class="p-error"
                >{{ form.errors.event_date }}</small>
              </div>

              <div>
                <label
                  for="event_time"
                  class="block text-sm font-medium mb-1"
                >Time *</label>
                <InputText
                  id="event_time"
                  v-model="form.event_time"
                  type="time"
                  class="w-full"
                />
                <small
                  v-if="form.errors.event_time"
                  class="p-error"
                >{{ form.errors.event_time }}</small>
              </div>
            </div>

            <!-- Venue Name -->
            <div class="mb-4">
              <label
                for="venue_name"
                class="block text-sm font-medium mb-1"
              >Venue Name</label>
              <InputText
                id="venue_name"
                v-model="form.venue_name"
                type="text"
                class="w-full"
                :placeholder="schedule?.location_name || 'Custom venue name for this rehearsal'"
              />
              <small
                v-if="form.errors.venue_name"
                class="p-error"
              >{{ form.errors.venue_name }}</small>
            </div>

            <!-- Venue Address -->
            <div class="mb-4">
              <label
                for="venue_address"
                class="block text-sm font-medium mb-1"
              >Venue Address</label>
              <Textarea
                id="venue_address"
                v-model="form.venue_address"
                rows="2"
                class="w-full"
                :placeholder="schedule?.location_address || 'Custom venue address for this rehearsal'"
              />
              <small
                v-if="form.errors.venue_address"
                class="p-error"
              >{{ form.errors.venue_address }}</small>
            </div>
          </div>
        </div>
      </template>

      <!-- For virtual rehearsals, show minimal info -->
      <template v-else>
        <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900 rounded-lg">
          <h3 class="text-lg font-semibold mb-2">
            {{ form.event_title }}
          </h3>
          <div class="text-sm text-gray-700 dark:text-gray-300">
            <div><strong>Date:</strong> {{ formatDisplayDate(form.event_date) }}</div>
            <div><strong>Time:</strong> {{ form.event_time }}</div>
            <div v-if="schedule?.location_name">
              <strong>Location:</strong> {{ schedule.location_name }}
            </div>
          </div>
        </div>
      </template>

      <!-- Rehearsal Notes - Always shown -->
      <div class="mb-4">
        <label
          for="notes"
          class="block text-sm font-medium mb-1"
        >Rehearsal Notes</label>
        <Editor
          id="notes"
          v-model="form.notes"
          editor-style="height: 200px"
          class="w-full"
        />
        <small
          v-if="form.errors.notes"
          class="p-error"
        >{{ form.errors.notes }}</small>
      </div>

      <!-- Song Links Section -->
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">
          Songs
        </label>
        <div class="space-y-3">
          <div
            v-for="(song, index) in songLinks"
            :key="index"
            class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
          >
            <div class="flex items-start gap-2">
              <div class="flex-1 space-y-2">
                <input
                  v-model="song.url"
                  type="url"
                  placeholder="Paste link (YouTube, Spotify, etc.)"
                  class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:text-gray-50 dark:border-slate-500"
                  @paste="handleUrlPaste($event, index)"
                  @blur="fetchSongMetadata(index)"
                >
                <input
                  v-model="song.title"
                  type="text"
                  placeholder="Song title (auto-filled from link)"
                  class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:text-gray-50 dark:border-slate-500"
                >
                
                <!-- Loading indicator -->
                <div
                  v-if="song.loading"
                  class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                >
                  <svg
                    class="animate-spin h-4 w-4"
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
                  <span>Fetching song info...</span>
                </div>

                <!-- Preview for different platforms -->
                <div
                  v-if="song.url && !song.loading"
                  class="mt-2"
                >
                  <!-- YouTube Preview -->
                  <div
                    v-if="isYouTubeUrl(song.url)"
                    class="aspect-video rounded overflow-hidden"
                  >
                    <iframe
                      :src="getYouTubeEmbedUrl(song.url)"
                      class="w-full h-full"
                      frameborder="0"
                      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                      allowfullscreen
                    />
                  </div>

                  <!-- Spotify Preview -->
                  <div
                    v-else-if="isSpotifyUrl(song.url)"
                    class="rounded overflow-hidden"
                  >
                    <iframe
                      :src="getSpotifyEmbedUrl(song.url)"
                      class="w-full h-20"
                      frameborder="0"
                      allowtransparency="true"
                      allow="encrypted-media"
                    />
                  </div>

                  <!-- Generic Link Preview -->
                  <div
                    v-else
                    class="flex items-center gap-2 p-2 bg-white dark:bg-slate-700 rounded border dark:border-slate-500"
                  >
                    <svg
                      class="w-5 h-5 text-gray-400"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                      />
                    </svg>
                    <a
                      :href="song.url"
                      target="_blank"
                      class="text-sm text-blue-600 dark:text-blue-400 hover:underline truncate"
                    >
                      {{ song.url }}
                    </a>
                  </div>
                </div>
              </div>
              
              <button
                type="button"
                class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded flex-shrink-0"
                @click="removeSongLink(index)"
              >
                <svg
                  class="w-5 h-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                  />
                </svg>
              </button>
            </div>
          </div>
          <button
            type="button"
            class="w-full px-4 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 border border-blue-300 dark:border-blue-600 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
            @click="addSongLink"
          >
            + Add Song Link
          </button>
        </div>
      </div>

      <!-- Charts Section -->
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">
          Charts & Sheet Music
        </label>
        
        <!-- Search/Select Charts -->
        <div class="mb-3">
          <div class="relative">
            <input
              v-model="chartSearchQuery"
              type="text"
              placeholder="Search charts..."
              class="w-full px-3 py-2 border rounded dark:bg-slate-700 dark:text-gray-50 dark:border-slate-500"
              @input="searchCharts"
              @focus="showChartResults = true"
            >
            
            <!-- Search Results Dropdown -->
            <div
              v-if="showChartResults && filteredCharts.length > 0"
              class="absolute z-10 w-full mt-1 bg-white dark:bg-slate-700 border dark:border-slate-600 rounded-lg shadow-lg max-h-60 overflow-auto"
            >
              <button
                v-for="chart in filteredCharts"
                :key="chart.id"
                type="button"
                class="w-full px-4 py-2 text-left hover:bg-gray-100 dark:hover:bg-slate-600 flex items-center justify-between"
                @click="addChart(chart)"
              >
                <div>
                  <div class="font-medium text-gray-900 dark:text-gray-50">
                    {{ chart.title }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    by {{ chart.composer }}
                  </div>
                </div>
                <svg
                  class="w-5 h-5 text-green-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 4v16m8-8H4"
                  />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Selected Charts -->
        <div class="space-y-2">
          <div
            v-for="(chart, index) in selectedCharts"
            :key="index"
            class="flex items-center gap-2 p-3 bg-gray-50 dark:bg-slate-600 rounded-lg"
          >
            <div class="flex-1">
              <div class="font-medium text-gray-900 dark:text-gray-50">
                {{ chart.title }}
              </div>
              <div class="text-sm text-gray-500 dark:text-gray-400">
                by {{ chart.composer }}
              </div>
              <div
                v-if="chart.uploads && chart.uploads.length > 0"
                class="mt-1 flex flex-wrap gap-1"
              >
                <span
                  v-for="upload in chart.uploads"
                  :key="upload.id"
                  class="text-xs px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded"
                >
                  {{ upload.displayName || upload.name }}
                </span>
              </div>
            </div>
            <button
              type="button"
              class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"
              @click="removeChart(index)"
            >
              <svg
                class="w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
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
          
          <div
            v-if="selectedCharts.length === 0"
            class="text-center py-4 text-gray-500 dark:text-gray-400 border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg text-sm"
          >
            No charts selected. Search and add charts above.
          </div>
        </div>
      </div>

      <!-- Associated Bookings Section -->
      <div class="mb-6 p-4 bg-green-50 dark:bg-green-900 rounded-lg">
        <h3 class="text-lg font-semibold mb-4">
          Associated Events
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
          Link this rehearsal to specific events you're preparing for
        </p>

        <div
          v-if="filteredAvailableBookings.length === 0"
          class="text-gray-500 dark:text-gray-400 italic"
        >
          {{ form.event_date ? 'No events on or after the selected rehearsal date' : 'No upcoming events available to associate' }}
        </div>

        <div
          v-else
          class="space-y-2"
        >
          <div
            v-for="event in filteredAvailableBookings"
            :key="event.id"
            class="flex items-start p-3 bg-white dark:bg-gray-800 rounded"
          >
            <Checkbox
              v-model="form.associated_events"
              :input-id="`event-${event.id}`"
              :value="event.id"
              class="mr-3"
            />
            <label
              :for="`event-${event.id}`"
              class="flex-1 cursor-pointer"
            >
              <div class="font-semibold">
                {{ event.title }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400">
                {{ formatBookingDate(event.date) }} - {{ event.booking_name }} @ {{ event.venue_name }}
              </div>
              <div
                v-if="event.notes"
                class="text-xs text-gray-500 dark:text-gray-500 mt-1 truncate"
              >
                Notes: {{ stripHtml(event.notes) }}
              </div>
            </label>
          </div>
        </div>

        <small
          v-if="form.errors.associated_events"
          class="p-error"
        >{{ form.errors.associated_events }}</small>
      </div>

      <!-- Cancelled Status -->
      <div class="mb-6 flex items-center">
        <Checkbox
          v-model="form.is_cancelled"
          input-id="is_cancelled"
          :binary="true"
        />
        <label
          for="is_cancelled"
          class="ml-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer"
        >
          Mark this rehearsal as cancelled
        </label>
      </div>
    </div>

    <template #footer>
      <Button
        label="Cancel"
        icon="pi pi-times"
        text
        @click="onHide"
      />
      <Button
        v-if="rehearsal && rehearsal.id"
        label="Delete"
        icon="pi pi-trash"
        severity="danger"
        text
        :loading="form.processing"
        @click="confirmDelete"
      />
      <Button
        :label="(rehearsal && rehearsal.id) ? 'Update Rehearsal' : 'Create Rehearsal'"
        icon="pi pi-check"
        :loading="form.processing"
        @click="submit"
      />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { DateTime } from 'luxon';
import axios from 'axios';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Calendar from 'primevue/calendar';
import Checkbox from 'primevue/checkbox';
import Editor from 'primevue/editor';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    rehearsal: {
        type: Object,
        default: null,
    },
    band: {
        type: Object,
        required: true,
    },
    schedule: {
        type: Object,
        default: null,
    },
    eventTypes: {
        type: Array,
        default: () => [],
    },
    availableBookings: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:visible', 'saved']);

// State for showing/hiding event details
const showEventDetails = ref(false);

// Song links and charts state
const songLinks = ref([]);
const selectedCharts = ref([]);
const chartSearchQuery = ref('');
const showChartResults = ref(false);
const availableCharts = ref([]);
const filteredCharts = ref([]);

const isVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

// Date object for PrimeVue Calendar
const eventDateObject = ref(null);

// Get the Rehearsal event type ID
const getRehearsalEventTypeId = () => {
    const rehearsalType = props.eventTypes.find(type => type.name === 'Rehearsal');
    return rehearsalType ? rehearsalType.id : null;
};

// Initialize form with rehearsal data or defaults
const getEventData = () => {
    if (props.rehearsal?.events?.[0]) {
        const event = props.rehearsal.events[0];
        // Set the date object for the calendar
        if (event.date) {
            // Parse date as local date to avoid timezone shifting
            // Split the date string and create a Date with local timezone
            const [year, month, day] = event.date.split('-').map(Number);
            eventDateObject.value = new Date(year, month - 1, day);
        }
        return {
            event_title: event.title || '',
            event_type_id: event.event_type_id || getRehearsalEventTypeId(),
            event_date: event.date || '',
            event_time: event.time?.substring(0, 5) || '', // HH:mm format
        };
    }
    return {
        event_title: '',
        event_type_id: getRehearsalEventTypeId(),
        event_date: '',
        event_time: '',
    };
};

const getAssociatedEvents = () => {
    if (props.rehearsal?.associations) {
        return props.rehearsal.associations
            .filter(a => a.associable_type === 'App\\Models\\Events')
            .map(a => a.associable_id);
    }
    return [];
};

// Initialize song links and charts from rehearsal data
const initializeSongsAndCharts = () => {
    if (props.rehearsal?.additional_data) {
        songLinks.value = props.rehearsal.additional_data.songs || [];
        selectedCharts.value = props.rehearsal.additional_data.charts || [];
    } else {
        songLinks.value = [];
        selectedCharts.value = [];
    }
};

const form = useForm({
    venue_name: props.rehearsal?.venue_name || '',
    venue_address: props.rehearsal?.venue_address || '',
    notes: props.rehearsal?.notes || '',
    additional_data: props.rehearsal?.additional_data || { songs: [], charts: [] },
    is_cancelled: props.rehearsal?.is_cancelled || false,
    ...getEventData(),
    associated_events: getAssociatedEvents(),
});

// Initialize songs and charts
initializeSongsAndCharts();

// Watch for date object changes and update form
watch(eventDateObject, (newDate) => {
    if (newDate) {
        // Format as YYYY-MM-DD for Laravel
        form.event_date = DateTime.fromJSDate(newDate).toISODate();
    }
});

// Watch for songs and charts changes
watch([songLinks, selectedCharts], () => {
    form.additional_data = {
        ...form.additional_data,
        songs: songLinks.value,
        charts: selectedCharts.value,
    };
}, { deep: true });

// Watch for rehearsal prop changes to reinitialize form
watch(() => props.rehearsal, (newRehearsal) => {
    if (newRehearsal) {
        const eventData = getEventData();
        form.venue_name = newRehearsal.venue_name || '';
        form.venue_address = newRehearsal.venue_address || '';
        form.notes = newRehearsal.notes || '';
        form.additional_data = newRehearsal.additional_data || { songs: [], charts: [] };
        form.is_cancelled = newRehearsal.is_cancelled || false;
        form.event_title = eventData.event_title;
        form.event_type_id = eventData.event_type_id || getRehearsalEventTypeId();
        form.event_date = eventData.event_date;
        form.event_time = eventData.event_time;
        form.associated_events = getAssociatedEvents();
        initializeSongsAndCharts();
    } else {
        form.reset();
        eventDateObject.value = null;
        songLinks.value = [];
        selectedCharts.value = [];
    }
}, { deep: true });

const stripHtml = (html) => {
    const tmp = document.createElement('DIV');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
};

const submit = () => {
    // Check if this is an edit or create operation
    const isEdit = props.rehearsal && props.rehearsal.id;
    
    if (isEdit) {
        form.put(route('rehearsals.update', {
            band: props.band.id,
            rehearsal_schedule: props.schedule.id,
            rehearsal: props.rehearsal.id,
        }), {
            onSuccess: () => {
                emit('saved');
                onHide();
            },
        });
    } else {
        // Creating a new rehearsal (either from scratch or from virtual event)
        form.post(route('rehearsals.store', {
            band: props.band.id,
            rehearsal_schedule: props.schedule.id,
        }), {
            onSuccess: () => {
                emit('saved');
                onHide();
            },
        });
    }
};

const confirmDelete = () => {
    // Only allow delete if this is an existing rehearsal with an ID
    if (!props.rehearsal || !props.rehearsal.id) {
        return;
    }
    
    if (confirm('Are you sure you want to delete this rehearsal? This will also remove it from the event calendar.')) {
        form.delete(route('rehearsals.destroy', {
            band: props.band.id,
            rehearsal_schedule: props.schedule.id,
            rehearsal: props.rehearsal.id,
        }), {
            onSuccess: () => {
                emit('saved');
                onHide();
            },
        });
    }
};

const onHide = () => {
    isVisible.value = false;
    form.reset();
    form.clearErrors();
    eventDateObject.value = null;
    showEventDetails.value = false;
    songLinks.value = [];
    selectedCharts.value = [];
    chartSearchQuery.value = '';
    filteredCharts.value = [];
    showChartResults.value = false;
};

const formatBookingDate = (date) => {
    if (!date) return 'N/A';
    return DateTime.fromISO(date).toLocaleString(DateTime.DATE_FULL);
};

const formatDisplayDate = (date) => {
    if (!date) return 'N/A';
    return DateTime.fromISO(date).toLocaleString(DateTime.DATE_FULL);
};

// Filter available bookings based on rehearsal date
const filteredAvailableBookings = computed(() => {
    if (!form.event_date) {
        return props.availableBookings;
    }
    
    const rehearsalDate = DateTime.fromISO(form.event_date);
    return props.availableBookings.filter(event => {
        if (!event.date) return false;
        const eventDate = DateTime.fromISO(event.date);
        return eventDate >= rehearsalDate;
    });
});

// Load charts from server
const loadCharts = async () => {
    try {
        const response = await axios.get('/api/charts');
        availableCharts.value = response.data;
    } catch (error) {
        console.error('Error loading charts:', error);
        // Fallback: use charts from page props if available
        if (window.$page?.props?.charts) {
            availableCharts.value = window.$page.props.charts;
        }
    }
};

// Search charts
const searchCharts = () => {
    const query = chartSearchQuery.value.toLowerCase();
    if (query.length === 0) {
        filteredCharts.value = [];
        return;
    }
    
    filteredCharts.value = availableCharts.value
        .filter(chart => {
            const alreadySelected = selectedCharts.value.some(sc => sc.id === chart.id);
            if (alreadySelected) return false;
            
            return chart.title.toLowerCase().includes(query) ||
                   chart.composer.toLowerCase().includes(query) ||
                   (chart.description && chart.description.toLowerCase().includes(query));
        })
        .slice(0, 10); // Limit to 10 results
};

// Add chart
const addChart = (chart) => {
    selectedCharts.value.push(chart);
    chartSearchQuery.value = '';
    filteredCharts.value = [];
    showChartResults.value = false;
};

// Remove chart
const removeChart = (index) => {
    selectedCharts.value.splice(index, 1);
};

// Song links management
const addSongLink = () => {
    songLinks.value.push({ title: '', url: '', loading: false });
};

const removeSongLink = (index) => {
    songLinks.value.splice(index, 1);
};

// Handle URL paste
const handleUrlPaste = async (event, index) => {
    // Small delay to allow v-model to update
    setTimeout(() => {
        fetchSongMetadata(index);
    }, 100);
};

// Fetch metadata from URL
const fetchSongMetadata = async (index) => {
    const song = songLinks.value[index];
    if (!song.url || song.title) return; // Skip if no URL or title already exists
    
    song.loading = true;
    
    try {
        // Extract title from YouTube URL
        if (isYouTubeUrl(song.url)) {
            const title = await fetchYouTubeTitle(song.url);
            if (title) {
                song.title = title;
            }
        }
        // Extract title from Spotify URL
        else if (isSpotifyUrl(song.url)) {
            const title = extractSpotifyTitle(song.url);
            if (title) {
                song.title = title;
            }
        }
        // Try to extract from URL for other links
        else {
            const title = extractTitleFromUrl(song.url);
            if (title) {
                song.title = title;
            }
        }
    } catch (error) {
        console.error('Error fetching song metadata:', error);
    } finally {
        song.loading = false;
    }
};

// YouTube helpers
const isYouTubeUrl = (url) => {
    return url && (url.includes('youtube.com') || url.includes('youtu.be'));
};

const getYouTubeEmbedUrl = (url) => {
    let videoId = '';
    
    if (url.includes('youtu.be/')) {
        videoId = url.split('youtu.be/')[1].split('?')[0];
    } else if (url.includes('youtube.com/watch?v=')) {
        videoId = url.split('v=')[1].split('&')[0];
    } else if (url.includes('youtube.com/embed/')) {
        return url;
    }
    
    return `https://www.youtube.com/embed/${videoId}`;
};

const fetchYouTubeTitle = async (url) => {
    try {
        // Extract video ID
        let videoId = '';
        if (url.includes('youtu.be/')) {
            videoId = url.split('youtu.be/')[1].split('?')[0];
        } else if (url.includes('youtube.com/watch?v=')) {
            videoId = url.split('v=')[1].split('&')[0];
        }
        
        if (!videoId) return null;
        
        // Use oEmbed API to get video title (no API key required)
        const response = await axios.get(`https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=${videoId}&format=json`);
        return response.data.title;
    } catch (error) {
        console.error('Error fetching YouTube title:', error);
        return null;
    }
};

// Spotify helpers
const isSpotifyUrl = (url) => {
    return url && url.includes('spotify.com');
};

const getSpotifyEmbedUrl = (url) => {
    // Convert spotify.com/track/xxx to embed format
    if (url.includes('spotify.com/track/')) {
        const trackId = url.split('track/')[1].split('?')[0];
        return `https://open.spotify.com/embed/track/${trackId}`;
    } else if (url.includes('spotify.com/album/')) {
        const albumId = url.split('album/')[1].split('?')[0];
        return `https://open.spotify.com/embed/album/${albumId}`;
    } else if (url.includes('spotify.com/playlist/')) {
        const playlistId = url.split('playlist/')[1].split('?')[0];
        return `https://open.spotify.com/embed/playlist/${playlistId}`;
    }
    return url;
};

const extractSpotifyTitle = (url) => {
    // Spotify URLs don't easily give us the title without API
    // Just return a placeholder that indicates it's from Spotify
    if (url.includes('spotify.com/track/')) {
        return 'Spotify Track';
    } else if (url.includes('spotify.com/album/')) {
        return 'Spotify Album';
    } else if (url.includes('spotify.com/playlist/')) {
        return 'Spotify Playlist';
    }
    return null;
};

// Generic URL title extraction
const extractTitleFromUrl = (url) => {
    try {
        const urlObj = new URL(url);
        const pathname = urlObj.pathname;
        const lastSegment = pathname.split('/').filter(Boolean).pop();
        
        if (lastSegment) {
            // Convert hyphens and underscores to spaces and capitalize
            return lastSegment
                .replace(/[-_]/g, ' ')
                .split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        }
    } catch (error) {
        console.error('Error extracting title from URL:', error);
    }
    return null;
};

// Load charts on mount
onMounted(() => {
    loadCharts();
    
    // Close dropdown when clicking outside
    const handleClickOutside = (event) => {
        if (!event.target.closest('.relative')) {
            showChartResults.value = false;
        }
    };
    document.addEventListener('click', handleClickOutside);
    
    return () => {
        document.removeEventListener('click', handleClickOutside);
    };
});
</script>
