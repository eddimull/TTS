<template>
  <!-- Drag & Drop Overlay -->
  <div
    v-if="isDraggingFiles"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 backdrop-blur-sm"
    @drop.prevent="handleWindowDrop"
    @dragover.prevent
  >
    <div class="text-center text-white">
      <i class="pi pi-cloud-upload text-8xl mb-6 animate-bounce" />
      <h3 class="text-3xl font-bold mb-2">Drop files to upload</h3>
      <p class="text-xl">
        <template v-if="eventFolderPath">
          Files will be uploaded to <strong>{{ eventFolderPath }}</strong>
        </template>
        <template v-else>
          Files will be uploaded to this event
        </template>
      </p>
    </div>
  </div>

  <Container class="p-4">
    <div class="space-y-4">
      <!-- Header -->
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4">
        <div class="flex justify-between items-center">
          <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-50 flex items-center gap-2">
              <i class="pi pi-images" />
              Event Media
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
              Upload and manage photos, videos, and documents for this event
            </p>
          </div>
          <Button
            v-if="canUploadMedia"
            label="Upload Files"
            icon="pi pi-upload"
            @click="showUploadDialog = true"
          />
        </div>
      </div>

      <!-- Event Folder Info -->
      <div
        v-if="eventFolderPath"
        class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4"
      >
        <div class="flex items-start gap-3">
          <i class="pi pi-info-circle text-blue-600 dark:text-blue-400 mt-1" />
          <div class="flex-1">
            <h3 class="font-medium text-blue-900 dark:text-blue-100 mb-1">
              Event Media Folder
            </h3>
            <p class="text-sm text-blue-700 dark:text-blue-300">
              Files uploaded here will be stored in: <strong>{{ eventFolderPath }}</strong>
            </p>
            <p v-if="enablePortalAccess" class="text-sm text-blue-700 dark:text-blue-300 mt-1">
              <i class="pi pi-share-alt mr-1" />
              Contacts can view these files in their portal
            </p>
          </div>
        </div>
      </div>

      <!-- Media Gallery -->
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-50">
            Media Files ({{ mediaFiles.length }})
          </h3>
          <div class="flex gap-2">
            <Button
              v-if="mediaFiles.length > 0"
              label="Refresh"
              icon="pi pi-refresh"
              size="small"
              text
              @click="refreshMedia"
            />
          </div>
        </div>

        <MediaGallery
          :media="mediaFiles"
          :show-download="true"
          :show-delete="canUploadMedia"
          :show-file-size="true"
          @media-click="handleMediaClick"
          @download="handleDownload"
          @delete="handleDelete"
        />
      </div>
    </div>

    <!-- Upload Dialog -->
    <MediaUploadDialog
      v-model:visible="showUploadDialog"
      :band-id="band.id"
      :folders="[]"
      :bookings="[booking]"
      :events="event ? [{ id: event.id, name: event.title }] : []"
      :default-folder="eventFolderPath"
      :default-booking-id="booking.id"
      :default-event-id="event?.id"
      :hide-folder="true"
      :hide-associations="true"
      @upload-complete="handleUploadComplete"
      @upload-error="handleUploadError"
    />

    <!-- Preview Dialog -->
    <Dialog
      v-model:visible="showPreviewDialog"
      :style="{ width: '90vw', maxWidth: '1200px' }"
      :header="selectedMedia?.title || selectedMedia?.filename"
      :modal="true"
    >
      <div v-if="selectedMedia" class="flex flex-col items-center">
        <img
          v-if="selectedMedia.media_type === 'image'"
          :src="selectedMedia.url"
          :alt="selectedMedia.filename"
          class="max-w-full max-h-[70vh] object-contain"
        />
        <video
          v-else-if="selectedMedia.media_type === 'video'"
          :src="selectedMedia.url"
          controls
          class="max-w-full max-h-[70vh]"
        />
        <div v-else class="text-center py-8">
          <i :class="getFileIcon(selectedMedia)" class="text-6xl text-gray-400 mb-4" />
          <p class="text-gray-600 dark:text-gray-400">
            Preview not available for this file type
          </p>
          <Button
            label="Download"
            icon="pi pi-download"
            class="mt-4"
            @click="handleDownload(selectedMedia)"
          />
        </div>
      </div>
    </Dialog>

    <!-- Delete Confirmation -->
    <Dialog
      v-model:visible="showDeleteDialog"
      :style="{ width: '450px' }"
      header="Confirm Delete"
      :modal="true"
    >
      <div class="flex items-start gap-3">
        <i class="pi pi-exclamation-triangle text-3xl text-orange-500" />
        <div>
          <p class="mb-2">Are you sure you want to delete this file?</p>
          <p class="text-sm text-gray-600 dark:text-gray-400">
            <strong>{{ mediaToDelete?.filename }}</strong>
          </p>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
            This action cannot be undone.
          </p>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button
            label="Cancel"
            severity="secondary"
            @click="showDeleteDialog = false"
          />
          <Button
            label="Delete"
            severity="danger"
            icon="pi pi-trash"
            :loading="deleting"
            @click="confirmDelete"
          />
        </div>
      </template>
    </Dialog>
  </Container>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import BookingLayout from './Layout/BookingLayout.vue'
import Container from '@/Components/Container.vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import MediaUploadDialog from '@/Components/MediaUploadDialog.vue'
import MediaGallery from '@/Components/MediaGallery.vue'
import { useMediaDragDrop } from '@/composables/useMediaDragDrop'

defineOptions({
  layout: BookingLayout,
})

const props = defineProps({
  booking: {
    type: Object,
    required: true
  },
  band: {
    type: Object,
    required: true
  },
  event: {
    type: Object,
    default: null
  },
  mediaFiles: {
    type: Array,
    default: () => []
  },
  eventFolderPath: {
    type: String,
    default: null
  },
  enablePortalAccess: {
    type: Boolean,
    default: true
  }
})

const page = usePage()

const showUploadDialog = ref(false)
const showPreviewDialog = ref(false)
const showDeleteDialog = ref(false)
const selectedMedia = ref(null)
const mediaToDelete = ref(null)
const deleting = ref(false)

const canUploadMedia = computed(
  () => page.props.auth.user.navigation?.Media?.write || false
)

// Initialize drag and drop with composable
const { isDraggingFiles } = useMediaDragDrop({
  canUpload: () => canUploadMedia.value,
  bandId: props.band.id,
  folderPath: props.eventFolderPath,
  onFilesDropped: (files, folderPath) => {
    // Refresh media after a short delay to let uploads complete
    setTimeout(() => {
      refreshMedia()
    }, 2000)
  }
})

function refreshMedia() {
  router.reload({ only: ['mediaFiles'] })
}

function handleMediaClick(media) {
  selectedMedia.value = media
  showPreviewDialog.value = true
}

function handleDownload(media) {
  window.location.href = `/media/${media.id}/download`
}

function handleDelete(media) {
  mediaToDelete.value = media
  showDeleteDialog.value = true
}

async function confirmDelete() {
  if (!mediaToDelete.value) return

  deleting.value = true

  router.delete(`/media/${mediaToDelete.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteDialog.value = false
      mediaToDelete.value = null
    },
    onError: () => {
      alert('Failed to delete file. Please try again.')
    },
    onFinish: () => {
      deleting.value = false
    }
  })
}

function handleUploadComplete() {
  showUploadDialog.value = false
  refreshMedia()
}

function handleUploadError(error) {
  console.error('Upload error:', error)
}

function getFileIcon(media) {
  const extension = media.filename.split('.').pop().toLowerCase()
  const iconMap = {
    pdf: 'pi pi-file-pdf',
    doc: 'pi pi-file-word',
    docx: 'pi pi-file-word',
    xls: 'pi pi-file-excel',
    xlsx: 'pi pi-file-excel',
    mp3: 'pi pi-volume-up',
    wav: 'pi pi-volume-up'
  }
  return iconMap[extension] || 'pi pi-file'
}
</script>
