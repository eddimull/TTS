<template>
  <BreezeAuthenticatedLayout>
    <Container class="md:container md:mx-auto bg-transparent dark:bg-transparent">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
        <!-- Header -->
        <Card class="mb-4">
          <template #content>
            <div class="flex items-start justify-between gap-3 mb-3">
              <div>
                <Link
                  :href="route('lodgings.index')"
                  class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 text-sm"
                >
                  ← Back to Lodging
                </Link>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-50 mt-1">
                  {{ lodging.name }}
                </h1>
              </div>
              <Link
                v-if="canWrite"
                :href="route('lodgings.edit', lodging.id)"
              >
                <Button
                  icon="pi pi-pencil"
                  label="Edit"
                  size="small"
                  outlined
                />
              </Link>
            </div>

            <div class="flex flex-wrap gap-2 mb-3">
              <Tag
                v-if="lodging.check_in_at"
                icon="pi pi-sign-in"
                :value="`Check-in: ${formatDateTime(lodging.check_in_at)}`"
                severity="info"
                rounded
              />
              <Tag
                v-if="lodging.check_out_at"
                icon="pi pi-sign-out"
                :value="`Check-out: ${formatDateTime(lodging.check_out_at)}`"
                severity="info"
                rounded
              />
            </div>

            <div
              v-if="lodging.notes"
              class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap mt-3"
            >
              {{ lodging.notes }}
            </div>
          </template>
        </Card>

        <!-- Address -->
        <Card
          v-if="lodging.address"
          class="mb-4"
        >
          <template #title>
            Address
          </template>
          <template #content>
            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 text-sm">
              <span class="text-gray-900 dark:text-gray-100">{{ lodging.address }}</span>
              <a
                :href="directionsUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="text-blue-600 dark:text-blue-400 hover:underline"
              >
                Directions
              </a>
            </div>
          </template>
        </Card>

        <!-- Rooms -->
        <Card
          v-if="lodging.rooms && lodging.rooms.length > 0"
          class="mb-4"
        >
          <template #title>
            Rooms
          </template>
          <template #content>
            <div class="space-y-2 text-sm">
              <div
                v-for="room in lodging.rooms"
                :key="room.id"
                class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 py-2 border-b border-gray-100 dark:border-slate-700 last:border-b-0"
              >
                <span class="font-medium text-gray-900 dark:text-gray-100 min-w-32">{{ room.label }}</span>
                <span
                  v-if="room.confirmation_number"
                  class="text-gray-600 dark:text-gray-300"
                >
                  Confirmation #: {{ room.confirmation_number }}
                </span>
                <span
                  v-if="room.notes"
                  class="text-gray-500 dark:text-gray-400"
                >
                  {{ room.notes }}
                </span>
              </div>
            </div>
          </template>
        </Card>

        <!-- Linked booking / event -->
        <Card
          v-if="lodging.booking || lodging.event"
          class="mb-4"
        >
          <template #title>
            Linked To
          </template>
          <template #content>
            <div class="space-y-2 text-sm">
              <div v-if="lodging.booking">
                <Link
                  :href="route('Booking Details', { band: band.id, booking: lodging.booking.id })"
                  class="text-blue-600 dark:text-blue-400 hover:underline"
                >
                  Booking: {{ lodging.booking.name }}
                </Link>
              </div>
              <div v-if="lodging.event">
                <Link
                  :href="route('events.show', lodging.event.id)"
                  class="text-blue-600 dark:text-blue-400 hover:underline"
                >
                  Event: {{ lodging.event.title }}
                </Link>
              </div>
            </div>
          </template>
        </Card>

        <!-- Attachments -->
        <Card
          v-if="lodging.attachments && lodging.attachments.length > 0"
          class="mb-4"
        >
          <template #title>
            Images &amp; Files
          </template>
          <template #content>
            <div class="flex flex-wrap gap-3">
              <div
                v-for="attachment in imageAttachments"
                :key="attachment.id"
                class="cursor-pointer group"
                @click="openAttachment(attachment)"
              >
                <img
                  :src="attachment.url"
                  :alt="attachment.filename"
                  loading="lazy"
                  class="block w-[150px] h-[150px] object-cover rounded-lg border-2 border-gray-200 dark:border-gray-600 group-hover:border-blue-400 dark:group-hover:border-blue-500 shadow-md transition-all duration-200"
                >
                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1 max-w-[150px] truncate">
                  {{ attachment.filename }}
                </div>
              </div>
            </div>

            <div
              v-if="fileAttachments.length > 0"
              class="mt-4 space-y-2"
            >
              <div
                v-for="attachment in fileAttachments"
                :key="attachment.id"
                class="flex items-center gap-2 py-2 px-3 bg-gray-50 dark:bg-slate-700 rounded hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors"
              >
                <i class="pi pi-file text-blue-600 dark:text-blue-400 text-sm flex-shrink-0" />
                <a
                  :href="attachment.download_url"
                  class="flex-1 text-sm text-gray-900 dark:text-gray-100 truncate hover:text-blue-600 dark:hover:text-blue-400"
                >
                  {{ attachment.filename }}
                </a>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </Container>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { DateTime } from 'luxon';
import Container from '@/Components/Container.vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Card from 'primevue/card';

const props = defineProps({
    band: { type: Object, required: true },
    lodging: { type: Object, required: true },
    canWrite: { type: Boolean, default: false },
});

const formatDateTime = (value) => {
    if (!value) return '';
    return DateTime.fromSQL(value).toFormat('EEE, MMM d h:mm a');
};

const directionsUrl = computed(() => {
    const { latitude, longitude, address } = props.lodging;
    if (latitude && longitude) {
        return `https://maps.google.com/?q=${latitude},${longitude}`;
    }
    return `https://maps.google.com/?q=${encodeURIComponent(address)}`;
});

const isImage = (mimeType) => !!mimeType && mimeType.startsWith('image/');

const imageAttachments = computed(() => (props.lodging.attachments || []).filter(a => isImage(a.mime_type)));
const fileAttachments = computed(() => (props.lodging.attachments || []).filter(a => !isImage(a.mime_type)));

const openAttachment = (attachment) => {
    window.open(attachment.url, '_blank', 'noopener,noreferrer');
};
</script>
