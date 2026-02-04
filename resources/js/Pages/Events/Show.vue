<template>
  <Container class="md:container md:mx-auto">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
      <!-- Compact Header -->
      <Card class="mb-4">
        <template #content>
          <div class="flex items-center gap-3 mb-3">
            <Link
              href="#"
              @click="back"
              class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
            >
              <i class="pi pi-arrow-left text-lg" />
            </Link>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-50 flex-1">
              {{ event.title }}
            </h1>
          </div>

          <!-- Compact Info Pills -->
          <div class="flex flex-wrap gap-2 mb-3">
            <Tag
              :icon="'pi pi-calendar'"
              :value="formatDate(event.date)"
              severity="info"
              rounded
            />
            <Tag
              :icon="'pi pi-clock'"
              :value="formatTime(event.time)"
              severity="info"
              rounded
            />
            <Tag
              v-if="event.type"
              :icon="'pi pi-tag'"
              :value="event.type.name"
              severity="info"
              rounded
            />
            <Tag
              v-if="userPayout"
              :icon="'pi pi-user'"
              :value="formatCurrency(userPayout)"
              severity="success"
              rounded
            />
          </div>
        </template>
      </Card>

      <!-- Booking Details -->
      <Card
        v-if="event.eventable"
        class="mb-4"
      >
        <template #title>
          Booking Details
        </template>
        <template #content>
          <div class="space-y-2 text-sm">
            <InfoRow
              v-if="event.eventable.venue_name"
              label="Venue"
              :value="event.eventable.venue_name"
            />
            <InfoRow
              v-if="event.eventable.venue_address"
              label="Address"
              :value="event.eventable.venue_address"
            />
            <InfoRow
              v-if="event.eventable.client_name"
              label="Client"
              :value="event.eventable.client_name"
            />
            <InfoRow
              v-if="event.eventable.client_email"
              label="Email"
              :href="`mailto:${event.eventable.client_email}`"
              :value="event.eventable.client_email"
            />
            <InfoRow
              v-if="event.eventable.client_phone"
              label="Phone"
              :href="`tel:${event.eventable.client_phone}`"
              :value="event.eventable.client_phone"
            />
          </div>
        </template>
      </Card>

      <!-- Timeline -->
       <Card class="mb-4">
        <template #title>
          Timeline
        </template>
        <template #content>
      <Times
        v-if="hasTimeline"
        class="mb-4"
        :event-date="event.date"
        :event-time="event.time"
        :times="event.additional_data.times"
      />
          </template>
       </Card>


      <!-- Notes -->
      <Card
        v-if="event.notes"
        class="mb-4"
      >
        <template #title>
          Notes
        </template>
        <template #content>
          <div
            class="prose prose-sm dark:prose-invert max-w-none whitespace-pre-wrap text-gray-700 dark:text-gray-300"
            v-html="event.notes"
          />

          <!-- Attachments inside Notes -->
          <div
            v-if="hasAttachments"
            class="mt-4 pt-4 border-t border-gray-200 dark:border-slate-600"
          >
            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
              Attachments
            </h4>
            <div class="space-y-2">
              <div
                v-for="attachment in event.attachments"
                :key="attachment.id"
              >
                <!-- Image thumbnail -->
                <div
                  v-if="isAttachmentImage(attachment.mime_type)"
                  class="inline-block mr-3 mb-3 group cursor-pointer"
                  @click="handleAttachmentClick(attachment)"
                >
                  <div class="relative inline-block">
                    <img
                      :src="getAttachmentShowUrl(attachment)"
                      :alt="attachment.file_name"
                      loading="lazy"
                      class="block max-w-[200px] max-h-[150px] object-cover rounded-lg border-2 border-gray-200 dark:border-gray-600 group-hover:border-blue-400 dark:group-hover:border-blue-500 shadow-md group-hover:shadow-xl transition-all duration-200"
                    >
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 rounded-lg flex items-center justify-center pointer-events-none">
                      <i class="pi pi-search-plus text-3xl text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200 drop-shadow-lg" />
                    </div>
                  </div>
                  <div class="text-xs text-gray-600 dark:text-gray-400 mt-1 max-w-[200px] truncate">
                    {{ attachment.file_name }}
                  </div>
                </div>

                <!-- Non-image attachments -->
                <div
                  v-else
                  class="flex items-center gap-2 py-2 px-3 bg-gray-50 dark:bg-slate-700 rounded hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors group cursor-pointer"
                  @click="handleAttachmentClick(attachment)"
                >
                  <i
                    :class="getAttachmentIconClass(attachment.mime_type)"
                    class="text-blue-600 dark:text-blue-400 text-sm flex-shrink-0"
                  />
                  <span class="flex-1 text-sm text-gray-900 dark:text-gray-100 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
                    {{ attachment.file_name }}
                  </span>
                  <span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">
                    {{ attachment.formatted_size }}
                  </span>
                  <i class="pi pi-download text-xs text-gray-400 group-hover:text-blue-600 dark:group-hover:text-blue-400" />
                </div>
              </div>
            </div>
          </div>
        </template>
      </Card>

      <!-- Attire -->
      <Card
        v-if="event.additional_data?.attire"
        class="mb-4"
      >
        <template #title>
          Attire
        </template>
        <template #content>
          <div
            class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap"
            v-html="event.additional_data.attire"
          />
        </template>
      </Card>

      <!-- Additional Info -->
      <Card
        v-if="hasAdditionalData"
        class="mb-4"
      >
        <template #title>
          Additional Info
        </template>
        <template #content>
          <div class="flex flex-wrap gap-2">
            <Tag
              v-if="event.additional_data?.public !== undefined"
              :icon="event.additional_data.public ? 'pi pi-check' : 'pi pi-times'"
              :severity="event.additional_data.public ? 'success' : 'secondary'"
              :value="'Public Event'"
              rounded
            />
            <Tag
              v-if="event.additional_data?.outside !== undefined"
              :icon="event.additional_data.outside ? 'pi pi-check' : 'pi pi-times'"
              :severity="event.additional_data.outside ? 'success' : 'secondary'"
              :value="'Outdoor'"
              rounded
            />
            <Tag
              v-if="event.additional_data?.backline_provided !== undefined"
              :icon="event.additional_data.backline_provided ? 'pi pi-check' : 'pi pi-times'"
              :severity="event.additional_data.backline_provided ? 'success' : 'secondary'"
              :value="'Backline'"
              rounded
            />
            <Tag
              v-if="event.additional_data?.production_needed !== undefined"
              :icon="event.additional_data.production_needed ? 'pi pi-check' : 'pi pi-times'"
              :severity="event.additional_data.production_needed ? 'success' : 'secondary'"
              :value="'Production'"
              rounded
            />
          </div>
        </template>
      </Card>

      <!-- Lodging -->
      <Card
        v-if="hasLodging"
        class="mb-4"
      >
        <template #title>
          Lodging
        </template>
        <template #content>
          <div class="space-y-2 text-sm">
            <div
              v-for="(item, index) in event.additional_data.lodging"
              :key="index"
              class="flex items-start gap-2"
            >
              <i
                v-if="item.type === 'checkbox'"
                :class="item.data ? 'pi pi-check-square text-green-600' : 'pi pi-square text-gray-400'"
                class="text-base mt-0.5"
              />
              <div class="flex-1">
                <div class="font-medium text-gray-900 dark:text-gray-50">
                  {{ item.title }}
                </div>
                <div
                  v-if="item.type === 'text' && item.data"
                  class="text-gray-600 dark:text-gray-400"
                >
                  {{ item.data }}
                </div>
              </div>
            </div>
          </div>
        </template>
      </Card>

      <!-- Performance -->
      <Card
        v-if="hasPerformanceData"
        class="mb-4"
      >
        <template #title>
          Performance
        </template>
        <template #content>
          <div class="space-y-4">
          <div
            v-if="event.additional_data.performance?.notes"
            class="prose prose-sm dark:prose-invert max-w-none text-gray-700 dark:text-gray-300"
            v-html="event.additional_data.performance.notes"
          />

          <div v-if="hasSongs">
            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
              Songs
            </h4>
            <div class="space-y-3">
              <div
                v-for="(song, index) in event.additional_data.performance.songs"
                :key="index"
              >
                <div
                  v-if="song.title"
                  class="text-sm font-medium text-gray-900 dark:text-gray-50 mb-2"
                >
                  {{ song.title }}
                </div>

                <div
                  v-if="isYouTubeUrl(song.url)"
                  class="aspect-video rounded overflow-hidden bg-gray-100 dark:bg-slate-700"
                >
                  <iframe
                    :src="getYouTubeEmbedUrl(song.url)"
                    class="w-full h-full"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                  />
                </div>

                <div
                  v-else-if="isSpotifyUrl(song.url)"
                  class="rounded overflow-hidden"
                >
                  <iframe
                    :src="getSpotifyEmbedUrl(song.url)"
                    width="100%"
                    height="152"
                    frameBorder="0"
                    allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                    loading="lazy"
                  />
                </div>

                <a
                  v-else
                  :href="song.url"
                  target="_blank"
                  class="text-sm text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1"
                >
                  {{ song.url }}
                  <i class="pi pi-external-link text-xs" />
                </a>
              </div>
            </div>
          </div>

          <div v-if="hasCharts">
            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
              Charts
            </h4>
            <div class="space-y-1">
              <div
                v-for="(chart, index) in event.additional_data.performance.charts"
                :key="index"
                class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-slate-700 rounded"
              >
                <i class="pi pi-file text-blue-500" />
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-gray-900 dark:text-gray-50 truncate">
                    {{ chart.title }}
                  </div>
                  <div
                    v-if="chart.composer"
                    class="text-xs text-gray-600 dark:text-gray-400 truncate"
                  >
                    {{ chart.composer }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        </template>
      </Card>

      <!-- Wedding -->
      <Card
        v-if="hasWeddingData"
        class="mb-4"
      >
        <template #title>
          Wedding Details
        </template>
        <template #content>
          <div class="space-y-3">
            <div
              v-if="event.additional_data.wedding?.onsite !== undefined"
              class="flex items-center gap-2 text-sm"
            >
              <i
                :class="event.additional_data.wedding.onsite ? 'pi pi-check text-green-600' : 'pi pi-times text-gray-400'"
              />
              <span class="text-gray-900 dark:text-gray-50">Ceremony Onsite</span>
            </div>

            <div v-if="hasDances">
              <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                Special Dances
              </h4>
              <div class="space-y-2">
                <div
                  v-for="(dance, index) in event.additional_data.wedding.dances"
                  :key="index"
                  class="p-2 bg-gray-50 dark:bg-slate-700 rounded"
                >
                  <div class="text-sm font-medium text-gray-900 dark:text-gray-50">
                    {{ formatDanceTitle(dance.title) }}
                  </div>
                  <div class="text-xs text-gray-600 dark:text-gray-400">
                    {{ dance.data || 'TBD' }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </Card>

      <!-- Roster -->
      <Card
        v-if="hasRoster"
        class="mb-4"
      >
        <template #title>
          Roster
        </template>
        <template #content>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <RosterMember
              v-for="member in event.roster_members"
              :key="member.name"
              :member="member"
            />
          </div>
        </template>
      </Card>

      <!-- Contacts -->
      <Card
        v-if="hasContacts"
        class="mb-4"
      >
        <template #title>
          Contacts
        </template>
        <template #content>
          <div class="space-y-2">
            <ContactCard
              v-for="contact in event.eventable.contacts"
              :key="contact.id"
              :contact="contact"
            />
          </div>
        </template>
      </Card>
    </div>
  </Container>

  <!-- Image Lightbox -->
  <ImageLightbox
    :show="showLightbox"
    :images="lightboxImages"
    :initial-index="lightboxIndex"
    @close="closeLightbox"
  />
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Container from '@/Components/Container.vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Card from 'primevue/card';
import { DateTime } from 'luxon';
import { formatCurrency as formatCurrencyUtil } from '@/utils/formatters';

// Import reusable components
import InfoRow from './Show/InfoRow.vue';
import ContactCard from './Show/ContactCard.vue';
import RosterMember from './Show/RosterMember.vue';
import Times from '@/Components/Event/Card/Components/Times.vue';
import ImageLightbox from '@/Components/ImageLightbox.vue';

defineOptions({
  layout: BreezeAuthenticatedLayout,
});

const props = defineProps({
  event: {
    type: Object,
    required: true
  },
  canEdit: {
    type: Boolean,
    default: false
  },
  band: {
    type: Object,
    required: true
  },
  userPayout: {
    type: Number,
    default: null
  }
});

// Computed properties
const hasRoster = computed(() => props.event.roster_members?.length > 0);
const hasAttachments = computed(() => props.event.attachments?.length > 0);
const hasTimeline = computed(() => props.event.additional_data?.times?.length > 0);
const hasAdditionalData = computed(() => {
  const data = props.event.additional_data;
  return data?.public !== undefined || data?.outside !== undefined ||
         data?.backline_provided !== undefined || data?.production_needed !== undefined;
});
const hasLodging = computed(() => props.event.additional_data?.lodging?.length > 0);
const hasPerformanceData = computed(() => {
  const perf = props.event.additional_data?.performance;
  return perf?.notes || perf?.songs?.length > 0 || perf?.charts?.length > 0;
});
const hasSongs = computed(() => props.event.additional_data?.performance?.songs?.length > 0);
const hasCharts = computed(() => props.event.additional_data?.performance?.charts?.length > 0);
const hasWeddingData = computed(() => {
  const wedding = props.event.additional_data?.wedding;
  return wedding?.onsite !== undefined || wedding?.dances?.length > 0;
});
const hasDances = computed(() => props.event.additional_data?.wedding?.dances?.length > 0);
const hasContacts = computed(() => props.event.eventable?.contacts?.length > 0);

// Formatting
const formatDate = (dateString) => {
  if (!dateString) return 'Not specified';
  return DateTime.fromISO(dateString).toFormat('MMM d, yyyy');
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

const formatCurrency = (value) => formatCurrencyUtil(value);

const formatDanceTitle = (title) => {
  if (!title) return '';
  return title.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

// Media helpers
const isYouTubeUrl = (url) => url && (url.includes('youtube.com') || url.includes('youtu.be'));
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

const isSpotifyUrl = (url) => url && url.includes('spotify.com');
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

// Lightbox state
const showLightbox = ref(false);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);

// Attachment helpers
const isAttachmentImage = (mimeType) => {
  return mimeType && mimeType.startsWith('image/');
};

const getAttachmentIconClass = (mimeType) => {
  if (mimeType.startsWith('image/')) return 'pi pi-image';
  if (mimeType === 'application/pdf') return 'pi pi-file-pdf';
  if (mimeType.startsWith('video/')) return 'pi pi-video';
  if (mimeType.startsWith('audio/')) return 'pi pi-volume-up';
  return 'pi pi-file';
};

const getAttachmentDownloadUrl = (attachment) => {
  return route('events.attachments.download', attachment.id);
};

const getAttachmentShowUrl = (attachment) => {
  if (attachment.url) {
    return attachment.url;
  }
  return route('events.attachments.show', attachment.id);
};

const handleAttachmentClick = (attachment) => {
  if (isAttachmentImage(attachment.mime_type)) {
    // Get all image attachments for the lightbox gallery
    const imageAttachments = (props.event.attachments || []).filter(att =>
      isAttachmentImage(att.mime_type)
    );

    // Create array of image URLs for lightbox
    lightboxImages.value = imageAttachments.map(att => getAttachmentShowUrl(att));

    // Find the index of the clicked image
    const clickedIndex = imageAttachments.findIndex(att => att.id === attachment.id);
    lightboxIndex.value = clickedIndex >= 0 ? clickedIndex : 0;

    showLightbox.value = true;
  } else {
    // Download non-image files
    window.open(getAttachmentDownloadUrl(attachment), '_blank');
  }
};

const closeLightbox = () => {
  showLightbox.value = false;
};

const back = () => {
  window.history.back();
}
</script>
