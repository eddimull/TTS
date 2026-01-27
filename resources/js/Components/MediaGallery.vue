<template>
  <div class="media-gallery">
    <!-- Empty State -->
    <div
      v-if="!media || media.length === 0"
      class="flex flex-col items-center justify-center py-12 px-4 bg-gray-50 dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600"
    >
      <i class="pi pi-images text-4xl text-gray-400 dark:text-gray-500 mb-3" />
      <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">
        No media files yet
      </h3>
      <p class="text-sm text-gray-500 dark:text-gray-400 text-center max-w-sm">
        Upload photos, videos, or documents to get started
      </p>
    </div>

    <!-- Media Grid -->
    <div
      v-else
      class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4"
    >
      <div
        v-for="item in media"
        :key="item.id"
        class="group relative aspect-square bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden cursor-pointer hover:ring-2 hover:ring-blue-500 transition-all"
        @click="$emit('media-click', item)"
      >
        <!-- Image Thumbnail -->
        <img
          v-if="item.media_type === 'image'"
          :src="item.thumbnail_url"
          :alt="item.filename"
          class="w-full h-full object-cover"
          loading="lazy"
        />

        <!-- Video Thumbnail -->
        <div
          v-else-if="item.media_type === 'video'"
          class="relative w-full h-full"
        >
          <img
            v-if="item.thumbnail_url"
            :src="item.thumbnail_url"
            :alt="item.filename"
            class="w-full h-full object-cover"
            loading="lazy"
          />
          <div
            v-else
            class="w-full h-full flex items-center justify-center bg-gray-200 dark:bg-gray-700"
          >
            <i class="pi pi-video text-4xl text-gray-400 dark:text-gray-500" />
          </div>
          <div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-20">
            <i class="pi pi-play-circle text-4xl text-white" />
          </div>
        </div>

        <!-- Document Icon -->
        <div
          v-else
          class="w-full h-full flex flex-col items-center justify-center bg-gray-200 dark:bg-gray-700"
        >
          <i :class="getDocumentIcon(item)" class="text-4xl text-gray-400 dark:text-gray-500 mb-2" />
          <span class="text-xs text-gray-500 dark:text-gray-400 uppercase">
            {{ getFileExtension(item.filename) }}
          </span>
        </div>

        <!-- Overlay with Actions -->
        <div
          class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition-all flex items-end p-2 opacity-0 group-hover:opacity-100"
        >
          <div class="flex items-center justify-between w-full">
            <span class="text-white text-xs font-medium truncate flex-1 mr-2">
              {{ item.title || item.filename }}
            </span>
            <div class="flex gap-1">
              <Button
                v-if="showDownload"
                icon="pi pi-download"
                size="small"
                rounded
                text
                severity="secondary"
                class="!text-white hover:!bg-white hover:!bg-opacity-20"
                @click.stop="$emit('download', item)"
                v-tooltip.top="'Download'"
              />
              <Button
                v-if="showDelete"
                icon="pi pi-trash"
                size="small"
                rounded
                text
                severity="danger"
                class="!text-white hover:!bg-red-500 hover:!bg-opacity-80"
                @click.stop="$emit('delete', item)"
                v-tooltip.top="'Delete'"
              />
            </div>
          </div>
        </div>

        <!-- File Size Badge -->
        <div
          v-if="showFileSize"
          class="absolute top-2 left-2 bg-black bg-opacity-60 text-white text-xs px-2 py-1 rounded"
        >
          {{ formatFileSize(item.file_size) }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'

defineProps({
  media: {
    type: Array,
    default: () => []
  },
  showDownload: {
    type: Boolean,
    default: true
  },
  showDelete: {
    type: Boolean,
    default: false
  },
  showFileSize: {
    type: Boolean,
    default: false
  }
})

defineEmits(['media-click', 'download', 'delete'])

function getDocumentIcon(item) {
  const extension = getFileExtension(item.filename).toLowerCase()
  const iconMap = {
    pdf: 'pi pi-file-pdf',
    doc: 'pi pi-file-word',
    docx: 'pi pi-file-word',
    xls: 'pi pi-file-excel',
    xlsx: 'pi pi-file-excel',
    mp3: 'pi pi-volume-up',
    wav: 'pi pi-volume-up',
    zip: 'pi pi-file'
  }
  return iconMap[extension] || 'pi pi-file'
}

function getFileExtension(filename) {
  const parts = filename.split('.')
  return parts.length > 1 ? parts.pop() : ''
}

function formatFileSize(bytes) {
  if (!bytes || bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i]
}
</script>
