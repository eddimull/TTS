<template>
  <div
    ref="detailsContainer"
    class="mt-4 p-6 bg-white dark:bg-slate-800 dark:text-gray-50 rounded-xl shadow-lg"
  >
    <!-- Activity History Modal -->
    <ActivityHistoryModal
      v-model:visible="showHistoryModal"
      :event-key="event.key"
      :event-title="event.title"
    />

    <!-- Header -->
    <div class="flex justify-between items-start mb-6 pb-4 border-b dark:border-slate-600">
      <h2 class="text-3xl font-bold text-gray-800 dark:text-gray-50">
        {{ event.title }}
      </h2>
      <div class="flex gap-2">
        <Button
          label="History"
          icon="pi pi-history"
          severity="secondary"
          outlined
          @click="viewHistory"
        />
        <Button
          label="Edit Event"
          icon="pi pi-pencil"
          severity="secondary"
          @click="editEvent"
        />
      </div>
    </div>
    
    <div class="space-y-6">
      <!-- Basic Information Section -->
      <SectionCard
        title="Basic Information"
        icon="info"
        :is-open="openSections.basicInfo"
        :view-mode="true"
        @toggle="toggleSection('basicInfo')"
      >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
              Date
            </div>
            <div class="text-lg font-medium text-gray-900 dark:text-gray-50">
              {{ formatDate(event.date) }}
            </div>
          </div>
          <div>
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
              Show Time
            </div>
            <div class="text-lg font-medium text-gray-900 dark:text-gray-50">
              {{ formatTime(event.time) }}
            </div>
          </div>
        </div>
      </SectionCard>

      <!-- Roster Section -->
      <SectionCard
        v-if="hasRoster"
        title="Event Roster"
        icon="users"
        :is-open="openSections.roster"
        :view-mode="true"
        @toggle="toggleSection('roster')"
      >
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
          <div
            v-for="(member, index) in event.roster_members"
            :key="index"
            class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-700 rounded-lg"
          >
            <div
              class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center flex-shrink-0"
            >
              <i class="pi pi-user text-blue-600 dark:text-blue-300" />
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-medium text-gray-900 dark:text-gray-50 truncate">
                {{ member.name }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400 truncate">
                {{ member.role || 'No role specified' }}
              </div>
            </div>
          </div>
        </div>
      </SectionCard>

      <!-- Notes Section -->
      <SectionCard
        v-if="event.notes"
        title="Notes"
        icon="notes"
        :is-open="openSections.notes"
        :view-mode="true"
        @toggle="toggleSection('notes')"
      >
        <div
          class="prose dark:prose-invert max-w-none"
          v-html="event.notes"
        />
      </SectionCard>

      <!-- Timeline Section -->
      <SectionCard
        v-if="hasTimeline"
        title="Event Timeline"
        icon="clock"
        :is-open="openSections.timeline"
        :view-mode="true"
        @toggle="toggleSection('timeline')"
      >
        <div class="space-y-3">
          <div
            v-for="(time, index) in sortedTimes"
            :key="index"
            class="flex items-center gap-4 p-3 bg-gray-50 dark:bg-slate-700 rounded-lg"
          >
            <div class="flex-shrink-0">
              <div
                class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center"
              >
                <i class="pi pi-clock text-blue-600 dark:text-blue-300" />
              </div>
            </div>
            <div class="flex-1">
              <div class="font-semibold text-gray-900 dark:text-gray-50">
                {{ time.title }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400">
                {{ formatDateTime(time.time) }}
              </div>
            </div>
          </div>
        </div>
      </SectionCard>

      <!-- Attire Section -->
      <SectionCard
        v-if="event.additional_data?.attire"
        title="Attire"
        icon="attire"
        :is-open="openSections.attire"
        :view-mode="true"
        @toggle="toggleSection('attire')"
      >
        <div
          class="prose dark:prose-invert max-w-none"
          v-html="event.additional_data.attire"
        />
      </SectionCard>

      <!-- Additional Data Section -->
      <SectionCard
        v-if="hasAdditionalData"
        title="Additional Information"
        icon="data"
        :is-open="openSections.additionalData"
        :view-mode="true"
        @toggle="toggleSection('additionalData')"
      >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-if="event.additional_data?.public !== undefined"
            class="flex items-center gap-2"
          >
            <i
              :class="event.additional_data.public ? 'pi pi-check text-green-600' : 'pi pi-times text-gray-400'"
            />
            <span class="text-gray-900 dark:text-gray-50">Public Event</span>
          </div>
          <div
            v-if="event.additional_data?.outside !== undefined"
            class="flex items-center gap-2"
          >
            <i
              :class="event.additional_data.outside ? 'pi pi-check text-green-600' : 'pi pi-times text-gray-400'"
            />
            <span class="text-gray-900 dark:text-gray-50">Outdoor Event</span>
          </div>
          <div
            v-if="event.additional_data?.backline_provided !== undefined"
            class="flex items-center gap-2"
          >
            <i
              :class="event.additional_data.backline_provided ? 'pi pi-check text-green-600' : 'pi pi-times text-gray-400'"
            />
            <span class="text-gray-900 dark:text-gray-50">Backline Provided</span>
          </div>
          <div
            v-if="event.additional_data?.production_needed !== undefined"
            class="flex items-center gap-2"
          >
            <i
              :class="event.additional_data.production_needed ? 'pi pi-check text-green-600' : 'pi pi-times text-gray-400'"
            />
            <span class="text-gray-900 dark:text-gray-50">Production Needed</span>
          </div>
        </div>
      </SectionCard>

      <!-- Lodging Section -->
      <SectionCard
        v-if="hasLodging"
        title="Lodging Information"
        icon="lodging"
        :is-open="openSections.lodging"
        :view-mode="true"
        @toggle="toggleSection('lodging')"
      >
        <div class="space-y-3">
          <div
            v-for="(item, index) in event.additional_data.lodging"
            :key="index"
            class="flex items-center gap-2"
          >
            <i
              v-if="item.type === 'checkbox'"
              :class="item.data ? 'pi pi-check-square text-green-600' : 'pi pi-square text-gray-400'"
              class="text-lg"
            />
            <div>
              <div class="font-medium text-gray-900 dark:text-gray-50">
                {{ item.title }}
              </div>
              <div
                v-if="item.type === 'text' && item.data"
                class="text-sm text-gray-600 dark:text-gray-400"
              >
                {{ item.data }}
              </div>
            </div>
          </div>
        </div>
      </SectionCard>

      <!-- Performance Section -->
      <SectionCard
        v-if="hasPerformanceData"
        title="Performance Notes"
        icon="performance"
        :is-open="openSections.performance"
        :view-mode="true"
        @toggle="toggleSection('performance')"
      >
        <div class="space-y-6">
          <!-- Performance Notes -->
          <div
            v-if="event.additional_data.performance?.notes"
            class="prose dark:prose-invert max-w-none"
            v-html="event.additional_data.performance.notes"
          />

          <!-- Song Links -->
          <div v-if="hasSongs">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-50 mb-3">
              Songs
            </h4>
            <div class="space-y-3">
              <div
                v-for="(song, index) in event.additional_data.performance.songs"
                :key="index"
                class="p-3 bg-gray-50 dark:bg-slate-700 rounded-lg"
              >
                <div
                  v-if="song.title"
                  class="font-medium text-gray-900 dark:text-gray-50 mb-2"
                >
                  {{ song.title }}
                </div>
                
                <!-- YouTube Embed -->
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

                <!-- Spotify Embed -->
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

                <!-- Generic Link -->
                <div
                  v-else
                  class="flex items-center gap-2"
                >
                  <i class="pi pi-link text-gray-400" />
                  <a
                    :href="song.url"
                    target="_blank"
                    class="text-blue-600 dark:text-blue-400 hover:underline"
                  >
                    {{ song.url }}
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Charts -->
          <div v-if="hasCharts">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-50 mb-3">
              Charts & Sheet Music
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div
                v-for="(chart, index) in event.additional_data.performance.charts"
                :key="index"
                class="p-3 border border-gray-200 dark:border-slate-600 rounded-lg hover:shadow-md transition-shadow"
              >
                <div class="font-medium text-gray-900 dark:text-gray-50">
                  {{ chart.title }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                  by {{ chart.composer }}
                </div>
                <div
                  v-if="chart.uploads && chart.uploads.length > 0"
                  class="mt-2 flex flex-wrap gap-1"
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
            </div>
          </div>
        </div>
      </SectionCard>

      <!-- Wedding Section -->
      <SectionCard
        v-if="isWedding && hasWeddingData"
        title="Wedding Details"
        icon="wedding"
        :is-open="openSections.wedding"
        :view-mode="true"
        @toggle="toggleSection('wedding')"
      >
        <div class="space-y-4">
          <!-- Onsite -->
          <div
            v-if="event.additional_data.wedding?.onsite !== undefined"
            class="flex items-center gap-2"
          >
            <i
              :class="event.additional_data.wedding.onsite ? 'pi pi-check text-green-600' : 'pi pi-times text-gray-400'"
            />
            <span class="text-gray-900 dark:text-gray-50">Onsite Wedding</span>
          </div>

          <!-- Dances -->
          <div v-if="hasDances">
            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-50 mb-3">
              Dances
            </h4>
            <div class="space-y-2">
              <div
                v-for="(dance, index) in event.additional_data.wedding.dances"
                :key="index"
                class="p-3 bg-gray-50 dark:bg-slate-700 rounded-lg"
              >
                <div class="font-medium text-gray-900 dark:text-gray-50">
                  {{ dance.title }}
                </div>
                <div
                  v-if="dance.data && dance.data !== 'TBD'"
                  class="text-sm text-gray-600 dark:text-gray-400 mt-1"
                >
                  {{ dance.data }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </SectionCard>
    </div>

    <!-- Action Buttons -->
    <div class="mt-6 pt-4 border-t dark:border-slate-600 flex justify-between">
      <Button
        label="Back to Event List"
        icon="pi pi-arrow-left"
        severity="secondary"
        outlined
        @click="cancel"
      />
      <div class="flex gap-2">
        <Button
          label="View on Dashboard"
          icon="pi pi-calendar"
          severity="info"
          outlined
          @click="viewOnDashboard"
        />
        <Button
          v-if="event.id"
          label="Remove Event"
          icon="pi pi-trash"
          severity="danger"
          outlined
          @click="removeEvent"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted, nextTick } from "vue";
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import SectionCard from "./EventEditor/SectionCard.vue";
import ActivityHistoryModal from "@/Components/ActivityHistoryModal.vue";
import { DateTime } from 'luxon';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(["edit", "cancel", "removeEvent"]);

const isWedding = computed(() => props.event.event_type_id === 1);
const detailsContainer = ref(null);
const showHistoryModal = ref(false);

// Track which sections are open
const openSections = reactive({
    basicInfo: true,
    roster: true,
    notes: true,
    timeline: true, // Default open so timeline can auto-scroll
    attire: false,
    additionalData: false,
    lodging: false,
    performance: false,
    wedding: false,
});

const toggleSection = (section) => {
    openSections[section] = !openSections[section];
};

// Scroll to the details view on mount
onMounted(() => {
    nextTick(() => {
        if (detailsContainer.value) {
            const headerOffset = 80; // Adjust for any fixed headers
            const elementPosition = detailsContainer.value.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
            
            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });
        }
    });
});

// Computed properties for conditional rendering
const hasRoster = computed(() => {
    return props.event.roster_members?.length > 0;
});

const hasTimeline = computed(() => {
    return props.event.additional_data?.times?.length > 0;
});

const sortedTimes = computed(() => {
    if (!props.event.additional_data?.times) return [];
    return [...props.event.additional_data.times].sort((a, b) => {
        return new Date(a.time) - new Date(b.time);
    });
});

const hasAdditionalData = computed(() => {
    const data = props.event.additional_data;
    return data?.public !== undefined || 
           data?.outside !== undefined || 
           data?.backline_provided !== undefined || 
           data?.production_needed !== undefined;
});

const hasLodging = computed(() => {
    return props.event.additional_data?.lodging?.length > 0;
});

const hasPerformanceData = computed(() => {
    const perf = props.event.additional_data?.performance;
    return perf?.notes || perf?.songs?.length > 0 || perf?.charts?.length > 0;
});

const hasSongs = computed(() => {
    return props.event.additional_data?.performance?.songs?.length > 0;
});

const hasCharts = computed(() => {
    return props.event.additional_data?.performance?.charts?.length > 0;
});

const hasWeddingData = computed(() => {
    const wedding = props.event.additional_data?.wedding;
    return wedding?.onsite !== undefined || wedding?.dances?.length > 0;
});

const hasDances = computed(() => {
    return props.event.additional_data?.wedding?.dances?.length > 0;
});

// Formatting functions
const formatDate = (dateString) => {
    if (!dateString) return 'Not specified';
    return DateTime.fromISO(dateString).toFormat('EEEE, MMMM d, yyyy');
};

const formatTime = (timeString) => {
    if (!timeString) return 'Not specified';
    const dt = DateTime.fromFormat(timeString, 'HH:mm:ss');
    if (!dt.isValid) {
        const dt2 = DateTime.fromFormat(timeString, 'HH:mm');
        return dt2.isValid ? dt2.toFormat('h:mm a') : timeString;
    }
    return dt.toFormat('h:mm a');
};

const formatDateTime = (dateTimeString) => {
    if (!dateTimeString) return 'Not specified';
    const dt = DateTime.fromISO(dateTimeString);
    return dt.isValid ? dt.toFormat('h:mm a') : dateTimeString;
};

// Media helpers
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

const isSpotifyUrl = (url) => {
    return url && url.includes('spotify.com');
};

const getSpotifyEmbedUrl = (url) => {
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

// Actions
const editEvent = () => {
    emit("edit", props.event);
};

const viewHistory = () => {
    showHistoryModal.value = true;
};

const cancel = () => {
    emit("cancel");
};

const removeEvent = () => {
    if (confirm(`Are you sure you want to remove "${props.event.title}"? This action cannot be undone.`)) {
        emit("removeEvent", props.event.id);
    }
};

const viewOnDashboard = () => {
    const identifier = props.event.id || props.event.key;
    router.visit(route('dashboard') + '#event_' + identifier);
};
</script>

<style scoped>
.prose {
    max-width: 100%;
}
</style>
