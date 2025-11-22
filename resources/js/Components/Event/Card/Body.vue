<template>
  <div class="grid grid-cols-1 content-center">
    <!-- Simplified view for virtual rehearsals -->
    <ul v-if="isVirtual">
      <li
        v-if="rehearsalNotes !== null && rehearsalNotes !== undefined && rehearsalNotes !== ''"
        class="p-2"
      >
        Rehearsal Notes:
        <div
          ref="rehearsalNotesRef"
          class="ml-3 p-3 shadow-lg rounded break-normal content-container bg-gray-100 dark:bg-slate-700"
          v-html="rehearsalNotes"
        />
      </li>
      <li
        v-else
        class="p-2 text-gray-500 dark:text-gray-400 italic"
      >
        No notes for this rehearsal
      </li>

      <!-- Rehearsal Songs -->
      <li
        v-if="rehearsalSongs && rehearsalSongs.length > 0"
        class="p-2 mt-4"
      >
        <div class="font-semibold mb-2 text-purple-700 dark:text-purple-300">
          Songs:
        </div>
        <div class="ml-3 space-y-3">
          <div
            v-for="(song, index) in rehearsalSongs"
            :key="index"
            class="p-3 bg-purple-50 dark:bg-purple-900/30 rounded border border-purple-200 dark:border-purple-700"
          >
            <div class="flex items-start mb-2">
              <span class="text-purple-600 dark:text-purple-400 mr-2">♪</span>
              <div class="flex-1">
                <a
                  v-if="song.url"
                  :href="song.url"
                  target="_blank"
                  class="text-purple-600 dark:text-purple-400 hover:underline font-medium"
                >
                  {{ song.title }}
                </a>
                <span
                  v-else
                  class="font-medium"
                >{{ song.title }}</span>
              </div>
            </div>
            
            <!-- Preview for different platforms -->
            <div
              v-if="song.url"
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
                class="flex items-center gap-2 p-2 bg-white dark:bg-slate-700 rounded border dark:border-slate-500 text-sm"
              >
                <svg
                  class="w-4 h-4 text-gray-400 flex-shrink-0"
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
                  class="text-purple-600 dark:text-purple-400 hover:underline truncate"
                >
                  {{ song.url }}
                </a>
              </div>
            </div>
          </div>
        </div>
      </li>

      <!-- Rehearsal Charts -->
      <li
        v-if="rehearsalCharts && rehearsalCharts.length > 0"
        class="p-2 mt-4"
      >
        <div class="font-semibold mb-2 text-green-700 dark:text-green-300">
          Charts & Sheet Music:
        </div>
        <div class="ml-3 p-3 bg-green-50 dark:bg-green-900/30 rounded border border-green-200 dark:border-green-700 space-y-2">
          <div
            v-for="chart in rehearsalCharts"
            :key="chart.id"
            class="flex items-start"
          >
            <span class="text-green-600 dark:text-green-400 mr-2">📄</span>
            <div class="flex-1">
              <a
                :href="route('charts.show', chart.id)"
                class="font-medium text-green-700 dark:text-green-300 hover:text-green-900 dark:hover:text-green-100 hover:underline"
              >
                {{ chart.title }}
              </a>
              <div
                v-if="chart.composer"
                class="text-xs text-gray-600 dark:text-gray-400"
              >
                by {{ chart.composer }}
              </div>
              <div
                v-if="chart.uploads && chart.uploads.length > 0"
                class="mt-1 flex flex-wrap gap-1"
              >
                <span
                  v-for="upload in chart.uploads"
                  :key="upload.id"
                  class="text-xs px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 rounded"
                >
                  {{ upload.displayName || upload.name }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </li>

      <!-- Associated Events and their Performance Notes -->
      <li
        v-if="hasAssociatedEvents"
        class="p-2 mt-4"
      >
        <div class="font-semibold mb-2 text-blue-700 dark:text-blue-300">
          Associated Events:
        </div>
        
        <div
          v-for="association in event.eventable?.associations"
          :key="association.id"
          class="mb-3"
        >
          <div
            v-if="association.associable_type === 'App\\Models\\Events' && association.associable"
            class="ml-3 p-4 shadow-lg rounded bg-blue-50 dark:bg-blue-900/30 border-2 border-blue-300 dark:border-blue-600"
          >
            <div class="text-lg font-semibold mb-1 text-blue-800 dark:text-blue-200">
              {{ association.associable.title }}
            </div>
            
            <div
              v-if="association.associable.date"
              class="text-sm text-gray-600 dark:text-gray-400 mb-3"
            >
              {{ formatDate(association.associable.date) }}
            </div>
            
            <div class="border-t border-blue-200 dark:border-blue-700 pt-3 mt-2">
              <div
                v-if="association.associable.additional_data?.performance"
                class="text-sm space-y-3"
              >
                <!-- Performance Notes -->
                <div v-if="association.associable.additional_data.performance.notes">
                  <div class="font-semibold text-gray-700 dark:text-gray-300 mb-2">
                    Performance Notes:
                  </div>
                  <div
                    class="p-3 bg-white dark:bg-slate-800 rounded border border-gray-200 dark:border-gray-600 associated-event-notes"
                    v-html="association.associable.additional_data.performance.notes"
                  />
                </div>

                <!-- Requested Songs -->
                <div v-if="association.associable.additional_data.performance.songs && association.associable.additional_data.performance.songs.length > 0">
                  <div class="font-semibold text-gray-700 dark:text-gray-300 mb-2">
                    Requested Songs:
                  </div>
                  <div class="space-y-3">
                    <div
                      v-for="(song, index) in association.associable.additional_data.performance.songs"
                      :key="index"
                      class="p-3 bg-white dark:bg-slate-800 rounded border border-gray-200 dark:border-gray-600"
                    >
                      <div class="flex items-start mb-2">
                        <span class="text-blue-600 dark:text-blue-400 mr-2">♪</span>
                        <div class="flex-1">
                          <a
                            v-if="song.url"
                            :href="song.url"
                            target="_blank"
                            class="text-blue-600 dark:text-blue-400 hover:underline font-medium"
                          >
                            {{ song.title }}
                          </a>
                          <span
                            v-else
                            class="font-medium"
                          >{{ song.title }}</span>
                        </div>
                      </div>
                      
                      <!-- Preview for different platforms -->
                      <div
                        v-if="song.url"
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
                          class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-slate-700 rounded border dark:border-slate-600 text-sm"
                        >
                          <svg
                            class="w-4 h-4 text-gray-400 flex-shrink-0"
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
                            class="text-blue-600 dark:text-blue-400 hover:underline truncate"
                          >
                            {{ song.url }}
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Charts -->
                <div v-if="association.associable.additional_data.performance.charts && association.associable.additional_data.performance.charts.length > 0">
                  <div class="font-semibold text-gray-700 dark:text-gray-300 mb-2">
                    Charts:
                  </div>
                  <div class="p-3 bg-white dark:bg-slate-800 rounded border border-gray-200 dark:border-gray-600 space-y-2">
                    <div
                      v-for="chart in association.associable.additional_data.performance.charts"
                      :key="chart.id"
                      class="flex items-start"
                    >
                      <span class="text-purple-600 dark:text-purple-400 mr-2">📄</span>
                      <div class="flex-1">
                        <a
                          :href="route('charts.show', chart.id)"
                          class="font-medium text-purple-700 dark:text-purple-300 hover:text-purple-900 dark:hover:text-purple-100 hover:underline"
                        >
                          {{ chart.title }}
                        </a>
                        <div
                          v-if="chart.composer"
                          class="text-xs text-gray-600 dark:text-gray-400"
                        >
                          by {{ chart.composer }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div
                v-else
                class="text-sm text-gray-500 dark:text-gray-400 italic"
              >
                No performance information for this event
              </div>
            </div>
          </div>
        </div>
      </li>
    </ul>

    <!-- Full view for regular events -->
    <ul v-else>
      <li class="p-2">
        Venue: <strong>{{ event.venue_name }}</strong>
      </li>
      <li class="p-2">
        Location:
        <strong
          v-if="event.venue_address"
        >{{ event.venue_address }} </strong><strong
          v-else
          class="text-red-500"
        >
          No address provided
        </strong>
      </li>
      <li class="p-2">
        Public:
        <strong>{{
          event.additional_data?.public ? "Yes" : "No"
        }}</strong>
      </li>
      <li
        v-if="event.time"
        class="p-2"
      >
        Timeline:
        <Times
          :event-time="event.time"
          :event-date="event.date"
          :times="event.additional_data?.times"
        />
      </li>
      <li
        v-if="event.notes !== null && event.notes !== undefined"
        class="p-2"
      >
        Notes:
        <div
          ref="eventNotesRef"
          class="ml-3 p-3 shadow-lg rounded break-normal content-container bg-gray-100 dark:bg-slate-700"
          v-html="event.notes"
        />
      </li>
      
      <!-- Performance Information Section -->
      <li
        v-if="hasPerformanceData"
        class="p-2"
      >
        <div class="font-semibold mb-2 text-blue-700 dark:text-blue-300">
          Performance Information:
        </div>
        <div class="ml-3 space-y-4">
          <!-- Performance Notes -->
          <div v-if="event.additional_data?.performance?.notes">
            <div class="font-medium text-gray-700 dark:text-gray-300 mb-2">
              Performance Notes:
            </div>
            <div
              ref="performanceNotesRef"
              class="p-3 shadow-lg rounded break-normal content-container bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700"
              v-html="event.additional_data.performance.notes"
            />
          </div>

          <!-- Requested Songs -->
          <div v-if="event.additional_data?.performance?.songs && event.additional_data.performance.songs.length > 0">
            <div class="font-medium text-gray-700 dark:text-gray-300 mb-2">
              Requested Songs:
            </div>
            <div class="space-y-3">
              <div
                v-for="(song, index) in event.additional_data.performance.songs"
                :key="index"
                class="p-3 bg-purple-50 dark:bg-purple-900/30 rounded border border-purple-200 dark:border-purple-700"
              >
                <div class="flex items-start mb-2">
                  <span class="text-purple-600 dark:text-purple-400 mr-2">♪</span>
                  <div class="flex-1">
                    <a
                      v-if="song.url"
                      :href="song.url"
                      target="_blank"
                      class="text-purple-600 dark:text-purple-400 hover:underline font-medium"
                    >
                      {{ song.title }}
                    </a>
                    <span
                      v-else
                      class="font-medium"
                    >{{ song.title }}</span>
                  </div>
                </div>
                
                <!-- Preview for different platforms -->
                <div
                  v-if="song.url"
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
                    class="flex items-center gap-2 p-2 bg-white dark:bg-slate-700 rounded border dark:border-slate-500 text-sm"
                  >
                    <svg
                      class="w-4 h-4 text-gray-400 flex-shrink-0"
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
                      class="text-purple-600 dark:text-purple-400 hover:underline truncate"
                    >
                      {{ song.url }}
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Performance Charts -->
          <div v-if="event.additional_data?.performance?.charts && event.additional_data.performance.charts.length > 0">
            <div class="font-medium text-gray-700 dark:text-gray-300 mb-2">
              Charts & Sheet Music:
            </div>
            <div class="p-3 bg-green-50 dark:bg-green-900/30 rounded border border-green-200 dark:border-green-700 space-y-2">
              <div
                v-for="chart in event.additional_data.performance.charts"
                :key="chart.id"
                class="flex items-start"
              >
                <span class="text-green-600 dark:text-green-400 mr-2">📄</span>
                <div class="flex-1">
                  <a
                    :href="route('charts.show', chart.id)"
                    class="font-medium text-green-700 dark:text-green-300 hover:text-green-900 dark:hover:text-green-100 hover:underline"
                  >
                    {{ chart.title }}
                  </a>
                  <div
                    v-if="chart.composer"
                    class="text-xs text-gray-600 dark:text-gray-400"
                  >
                    by {{ chart.composer }}
                  </div>
                  <div
                    v-if="chart.uploads && chart.uploads.length > 0"
                    class="mt-1 flex flex-wrap gap-1"
                  >
                    <span
                      v-for="upload in chart.uploads"
                      :key="upload.id"
                      class="text-xs px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 rounded"
                    >
                      {{ upload.displayName || upload.name }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </li>
      
      <li class="p-2">
        Extra Details:
        <div
          class="ml-3 p-3 shadow-lg rounded break-normal bg-gray-100 dark:bg-slate-700"
        >
          <ul>
            <li>
              Outside Event:
              <strong>{{
                event.additional_data?.outside ? "Yes" : "No"
              }}</strong>
            </li>
            <li>
              Lodging Provided:
              <strong>{{
                event.additional_data?.lodging?.find(
                  (item) =>
                    item.title === "Lodging Provided"
                )?.data
                  ? "Yes"
                  : "No"
              }}
              </strong>
            </li>
            <li>
              Backline Provided:
              <strong>{{
                event.additional_data?.backline_provided
                  ? "Yes"
                  : "No"
              }}</strong>
            </li>
            <li>
              Production Needed:
              <strong>{{
                event.additional_data?.production_needed
                  ? "Yes"
                  : "No"
              }}</strong>
            </li>
          </ul>
        </div>
      </li>
      <li
        v-if="
          event.event_type_id === 1 && event.additional_data?.wedding
        "
        class="p-2"
      >
        Wedding Info:
        <Wedding :wedding="event.additional_data?.wedding" />
      </li>
      <li v-if="event.additional_data?.attire">
        Attire:
        <div
          ref="attireRef"
          class="ml-3 p-3 shadow-lg rounded break-normal bg-gray-100 dark:bg-slate-700"
          v-html="event.additional_data?.attire"
        />
      </li>
      <Contacts :contacts="event.contacts || []" />
    </ul>
  </div>

  <!-- Image Lightbox -->
  <ImageLightbox
    :show="showLightbox"
    :images="lightboxImages"
    :initial-index="lightboxIndex"
    @close="closeLightbox"
  />
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount, nextTick } from "vue";
import Times from "./Components/Times.vue";
import Wedding from "./Components/Wedding.vue";
import Contacts from "./Components/Contacts.vue";
import ImageLightbox from "@/Components/ImageLightbox.vue";
import { useImageThumbnails } from "@/Composables/useImageThumbnails";

const props = defineProps(["event", "type"]);

// Image thumbnail functionality
const { showLightbox, lightboxImages, lightboxIndex, processImages, closeLightbox, cleanupImages } = useImageThumbnails();

// Template refs for content containers with images
const rehearsalNotesRef = ref(null);
const eventNotesRef = ref(null);
const performanceNotesRef = ref(null);
const attireRef = ref(null);

const isVirtual = computed(() => {
  if (!props.event) return false;
  
  // Check for virtual rehearsals (generated from schedule)
  const key = props.event.key || props.event['key'];
  if (key && (key.startsWith('virtual-') || props.event.is_virtual === true || props.event['is_virtual'] === true)) {
    return true;
  }
  
  // Check for scheduled rehearsals (with notes)
  if (props.event.eventable_type === 'App\\Models\\Rehearsal' && props.event.eventable_id) {
    return true;
  }
  
  return false;
});

const hasPerformanceData = computed(() => {
  if (!props.event?.additional_data?.performance) return false;
  const perf = props.event.additional_data.performance;
  return perf.notes || 
         (perf.songs && perf.songs.length > 0) || 
         (perf.charts && perf.charts.length > 0);
});

const hasAssociatedEvents = computed(() => {
  if (!props.event?.eventable?.associations) return false;
  return props.event.eventable.associations.some(assoc => 
    assoc.associable_type === 'App\\Models\\Events' && assoc.associable
  );
});

const rehearsalNotes = computed(() => {
  // For saved rehearsals, get notes from the rehearsal record
  if (props.event?.eventable?.notes) {
    return props.event.eventable.notes;
  }
  // For virtual rehearsals, get notes from the schedule if available
  if (props.event?.notes) {
    return props.event.notes;
  }
  return null;
});

const rehearsalSongs = computed(() => {
  // Get songs from rehearsal's additional_data
  if (props.event?.eventable?.additional_data?.songs) {
    return props.event.eventable.additional_data.songs;
  }
  return [];
});

const rehearsalCharts = computed(() => {
  // Get charts from rehearsal's additional_data
  if (props.event?.eventable?.additional_data?.charts) {
    return props.event.eventable.additional_data.charts;
  }
  return [];
});

const formatDate = (date) => {
  if (!date) return '';
  // Handle date string as local date to avoid timezone conversion issues
  // If date is in format YYYY-MM-DD, parse it as local date
  const dateStr = String(date);
  if (dateStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
    const [year, month, day] = dateStr.split('-').map(Number);
    const d = new Date(year, month - 1, day);
    return d.toLocaleDateString('en-US', { 
      weekday: 'short', 
      year: 'numeric', 
      month: 'short', 
      day: 'numeric' 
    });
  }
  // For other formats, use standard Date parsing
  const d = new Date(date);
  return d.toLocaleDateString('en-US', { 
    weekday: 'short', 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric' 
  });
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

// Process images after component is mounted
onMounted(async () => {
  await nextTick();

  // Process images in all content containers
  const containers = [
    rehearsalNotesRef.value,
    eventNotesRef.value,
    performanceNotesRef.value,
    attireRef.value
  ];

  // Also process any associated event notes containers
  const associatedContainers = document.querySelectorAll('.associated-event-notes');
  associatedContainers.forEach(container => containers.push(container));

  containers.forEach(container => {
    if (container) {
      processImages(container);
    }
  });
});

// Cleanup on unmount
onBeforeUnmount(() => {
  const containers = [
    rehearsalNotesRef.value,
    eventNotesRef.value,
    performanceNotesRef.value,
    attireRef.value
  ];

  const associatedContainers = document.querySelectorAll('.associated-event-notes');
  associatedContainers.forEach(container => containers.push(container));

  containers.forEach(container => {
    if (container) {
      cleanupImages(container);
    }
  });
});
</script>
