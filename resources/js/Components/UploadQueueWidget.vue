<template>
  <Teleport to="body">
    <div
      v-if="uploadQueue.length > 0"
      class="fixed bottom-4 right-4 z-50 w-96 bg-white dark:bg-gray-800 rounded-lg shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden"
      :class="{ 'h-16': isMinimized, 'max-h-[600px]': !isMinimized }"
    >
      <!-- Header -->
      <div
        class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 cursor-pointer"
        @click="toggleMinimize"
      >
        <div class="flex items-center gap-3">
          <div v-if="hasActiveUploads" class="animate-spin">
            <i class="pi pi-spinner text-blue-500" />
          </div>
          <div v-else-if="failedUploads.length > 0">
            <i class="pi pi-exclamation-triangle text-red-500" />
          </div>
          <div v-else>
            <i class="pi pi-check-circle text-green-500" />
          </div>

          <div>
            <div class="font-semibold text-sm">
              <template v-if="hasActiveUploads">
                Uploading {{ activeUploads.length }} file{{ activeUploads.length !== 1 ? 's' : '' }}
              </template>
              <template v-else-if="failedUploads.length > 0">
                {{ failedUploads.length }} upload{{ failedUploads.length !== 1 ? 's' : '' }} failed
              </template>
              <template v-else>
                {{ completedUploads.length }} upload{{ completedUploads.length !== 1 ? 's' : '' }} complete
              </template>
            </div>
            <div v-if="hasActiveUploads" class="text-xs text-gray-500 dark:text-gray-400">
              {{ completedUploads.length }} of {{ uploadQueue.length }} complete
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition-colors"
            @click.stop="toggleMinimize"
          >
            <i :class="isMinimized ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" class="text-sm" />
          </button>
          <button
            v-if="!hasActiveUploads"
            type="button"
            class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition-colors"
            @click.stop="clearCompleted"
          >
            <i class="pi pi-times text-sm" />
          </button>
        </div>
      </div>

      <!-- Upload List -->
      <div
        v-if="!isMinimized"
        class="overflow-y-auto"
        style="max-height: 500px"
      >
        <TransitionGroup name="upload-list">
          <div
            v-for="item in uploadQueue"
            :key="item.id"
            class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-900 transition-colors"
          >
            <div class="flex items-start gap-3">
              <!-- Icon -->
              <div class="flex-shrink-0 mt-1">
                <i
                  v-if="item.status === 'uploading'"
                  class="pi pi-spinner animate-spin text-blue-500"
                />
                <i
                  v-else-if="item.status === 'completed'"
                  class="pi pi-check-circle text-green-500"
                />
                <i
                  v-else-if="item.status === 'failed'"
                  class="pi pi-exclamation-circle text-red-500"
                />
                <i
                  v-else-if="item.status === 'cancelled'"
                  class="pi pi-ban text-gray-400"
                />
                <i
                  v-else
                  class="pi pi-clock text-gray-400"
                />
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate" :title="item.file.name">
                  {{ item.file.name }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  {{ formatFileSize(item.file.size) }}
                  <span v-if="item.folderPath"> → {{ item.folderPath }}</span>
                </div>

                <!-- Progress Bar -->
                <div
                  v-if="item.status === 'uploading'"
                  class="mt-2"
                >
                  <div class="flex items-center justify-between text-xs mb-1">
                    <span class="text-gray-600 dark:text-gray-400">
                      {{ item.progress }}%
                    </span>
                    <span v-if="item.totalChunks > 0" class="text-gray-500">
                      Chunk {{ item.uploadedChunks }} / {{ item.totalChunks }}
                    </span>
                  </div>
                  <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                    <div
                      class="bg-blue-500 h-1.5 rounded-full transition-all duration-300"
                      :style="{ width: item.progress + '%' }"
                    />
                  </div>
                </div>

                <!-- Error Message -->
                <div
                  v-if="item.status === 'failed' && item.error"
                  class="mt-2 text-xs text-red-600 dark:text-red-400"
                >
                  {{ item.error }}
                </div>

                <!-- Completed Time -->
                <div
                  v-if="item.status === 'completed' && item.completedAt"
                  class="mt-1 text-xs text-gray-500"
                >
                  Completed {{ formatTime(item.completedAt) }}
                </div>
              </div>

              <!-- Actions -->
              <div class="flex-shrink-0 flex items-center gap-1">
                <button
                  v-if="item.status === 'uploading'"
                  type="button"
                  class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition-colors"
                  @click="cancelUpload(item.id)"
                  title="Cancel upload"
                >
                  <i class="pi pi-times text-sm" />
                </button>

                <button
                  v-if="item.status === 'failed'"
                  type="button"
                  class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition-colors"
                  @click="retryUpload(item.id)"
                  title="Retry upload"
                >
                  <i class="pi pi-refresh text-sm" />
                </button>

                <button
                  v-if="item.status === 'completed' || item.status === 'failed' || item.status === 'cancelled'"
                  type="button"
                  class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition-colors"
                  @click="removeItem(item.id)"
                  title="Remove from list"
                >
                  <i class="pi pi-times text-sm" />
                </button>
              </div>
            </div>
          </div>
        </TransitionGroup>
      </div>

      <!-- Footer Actions -->
      <div
        v-if="!isMinimized && hasActiveUploads"
        class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700"
      >
        <button
          type="button"
          class="w-full px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors"
          @click="cancelAll"
        >
          Cancel All Uploads
        </button>
      </div>
    </div>
  </Teleport>
</template>

<script>
import { useUploadQueue } from '@/composables/useUploadQueue';

export default {
  name: 'UploadQueueWidget',

  setup() {
    const {
      uploadQueue,
      isMinimized,
      activeUploads,
      completedUploads,
      failedUploads,
      hasActiveUploads,
      cancelUpload,
      cancelAll,
      retryUpload,
      clearCompleted,
      removeItem,
      toggleMinimize
    } = useUploadQueue();

    const formatFileSize = (bytes) => {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    };

    const formatTime = (timestamp) => {
      const now = Date.now();
      const diff = now - timestamp;
      const seconds = Math.floor(diff / 1000);

      if (seconds < 60) return 'just now';
      if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
      if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
      return Math.floor(seconds / 86400) + 'd ago';
    };

    return {
      uploadQueue,
      isMinimized,
      activeUploads,
      completedUploads,
      failedUploads,
      hasActiveUploads,
      cancelUpload,
      cancelAll,
      retryUpload,
      clearCompleted,
      removeItem,
      toggleMinimize,
      formatFileSize,
      formatTime
    };
  }
};
</script>

<style scoped>
/* Smooth transitions for list items */
.upload-list-enter-active,
.upload-list-leave-active {
  transition: all 0.3s ease;
}

.upload-list-enter-from {
  opacity: 0;
  transform: translateX(30px);
}

.upload-list-leave-to {
  opacity: 0;
  transform: translateX(-30px);
}

.upload-list-move {
  transition: transform 0.3s ease;
}
</style>
