<template>
  <ContactLayout>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
      <!-- Header -->
      <header class="bg-white dark:bg-gray-800 shadow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
              Event Media
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              View and download photos and videos from your events
            </p>
          </div>
          <Link
            :href="route('portal.dashboard')"
            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
          >
            Back to Dashboard
          </Link>
        </div>
      </header>

      <!-- Main Content -->
      <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
          <!-- Folder Grid View -->
          <div v-if="!selectedFolder">
            <!-- Empty State -->
            <div
              v-if="folders.length === 0"
              class="bg-white dark:bg-gray-800 shadow rounded-lg p-12 text-center"
            >
              <svg
                class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                />
              </svg>
              <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                No media available
              </h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Your event media will appear here once uploaded by your band
              </p>
            </div>

            <!-- Folders Grid -->
            <div
              v-else
              class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
            >
              <div
                v-for="folder in folders"
                :key="folder.path"
                @click="selectFolder(folder)"
                class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg hover:shadow-lg transition-shadow cursor-pointer"
              >
                <div class="aspect-video bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-900/30 dark:to-purple-900/30 flex items-center justify-center relative overflow-hidden">
                  <!-- Folder Thumbnail or Icon -->
                  <img
                    v-if="folder.thumbnail_url"
                    :src="folder.thumbnail_url"
                    :alt="folder.name"
                    class="w-full h-full object-cover"
                  />
                  <svg
                    v-else
                    class="h-16 w-16 text-indigo-400 dark:text-indigo-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                    />
                  </svg>

                  <!-- File Count Badge -->
                  <div class="absolute top-2 right-2 bg-white dark:bg-gray-800 rounded-full px-2 py-1 text-xs font-medium text-gray-700 dark:text-gray-200 shadow">
                    {{ folder.file_count }} {{ folder.file_count === 1 ? 'file' : 'files' }}
                  </div>
                </div>
                <div class="p-5">
                  <h3 class="text-lg font-medium text-gray-900 dark:text-white truncate">
                    {{ folder.name }}
                  </h3>
                  <p
                    v-if="folder.event_date"
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                  >
                    {{ folder.event_date }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Media Viewer for Selected Folder -->
          <div v-else>
            <!-- Back to Folders Button -->
            <button
              @click="selectedFolder = null"
              class="mb-6 inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700"
            >
              <svg
                class="mr-2 h-5 w-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M10 19l-7-7m0 0l7-7m-7 7h18"
                />
              </svg>
              Back to Folders
            </button>

            <!-- Folder Header -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
              <div class="flex items-center justify-between">
                <div>
                  <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ selectedFolder.name }}
                  </h2>
                  <p
                    v-if="selectedFolder.event_date"
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                  >
                    {{ selectedFolder.event_date }}
                  </p>
                </div>
                <div class="text-right">
                  <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ selectedFolder.file_count }} {{ selectedFolder.file_count === 1 ? 'file' : 'files' }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Media Grid -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
              <div
                v-for="(file, index) in selectedFolder.files"
                :key="file.id"
                class="relative group bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden hover:shadow-lg transition-shadow cursor-pointer"
                @click="openGallery(index)"
              >
                <!-- Media Preview -->
                <div class="aspect-square bg-gray-100 dark:bg-gray-700 relative overflow-hidden">
                  <!-- Image Preview -->
                  <img
                    v-if="file.is_image"
                    :src="file.thumbnail_url || file.url"
                    :alt="file.filename"
                    class="w-full h-full object-cover"
                  />

                  <!-- Video Preview -->
                  <div
                    v-else-if="file.is_video"
                    class="w-full h-full flex items-center justify-center bg-gradient-to-br from-purple-100 to-pink-100 dark:from-purple-900/30 dark:to-pink-900/30"
                  >
                    <img
                      v-if="file.thumbnail_url"
                      :src="file.thumbnail_url"
                      :alt="file.filename"
                      class="w-full h-full object-cover"
                    />
                    <svg
                      v-else
                      class="h-12 w-12 text-purple-600 dark:text-purple-400"
                      fill="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path d="M8 5v14l11-7z" />
                    </svg>
                  </div>

                  <!-- Other File Type -->
                  <div
                    v-else
                    class="w-full h-full flex items-center justify-center bg-gray-200 dark:bg-gray-700"
                  >
                    <svg
                      class="h-12 w-12 text-gray-400 dark:text-gray-500"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                      />
                    </svg>
                  </div>

                  <!-- Hover Overlay -->
                  <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-opacity flex items-center justify-center">
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                      <svg
                        v-if="file.is_image || file.is_video"
                        class="h-12 w-12 text-white"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"
                        />
                      </svg>
                    </div>
                  </div>
                </div>

                <!-- File Info -->
                <div class="p-3">
                  <p class="text-xs text-gray-900 dark:text-white truncate" :title="file.filename">
                    {{ file.filename }}
                  </p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ formatFileSize(file.file_size) }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Empty State for Folder -->
            <div
              v-if="selectedFolder.files.length === 0"
              class="bg-white dark:bg-gray-800 shadow rounded-lg p-12 text-center"
            >
              <svg
                class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                />
              </svg>
              <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                No files in this folder
              </h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Files will appear here once uploaded by your band
              </p>
            </div>
          </div>
        </div>
      </main>

      <!-- Gallery Lightbox -->
      <Teleport to="body">
        <div
          v-if="galleryOpen"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-95"
          @click="closeGallery"
        >
          <!-- Close Button -->
          <button
            class="absolute top-4 right-4 text-white hover:text-gray-300 transition-colors z-10"
            @click="closeGallery"
          >
            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <!-- Previous Button -->
          <button
            v-if="canGoPrevious"
            class="absolute left-4 text-white hover:text-gray-300 transition-colors z-10"
            @click.stop="previousMedia"
          >
            <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>

          <!-- Next Button -->
          <button
            v-if="canGoNext"
            class="absolute right-4 text-white hover:text-gray-300 transition-colors z-10"
            @click.stop="nextMedia"
          >
            <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>

          <!-- Media Content -->
          <div
            v-if="currentGalleryFile"
            class="max-w-7xl max-h-screen w-full h-full flex flex-col items-center justify-center p-4"
            @click.stop
          >
            <!-- Image -->
            <img
              v-if="currentGalleryFile.is_image"
              :src="currentGalleryFile.url"
              :alt="currentGalleryFile.filename"
              class="max-w-full max-h-[calc(100vh-200px)] object-contain"
            />

            <!-- Video -->
            <video
              v-else-if="currentGalleryFile.is_video"
              :src="currentGalleryFile.url"
              controls
              class="max-w-full max-h-[calc(100vh-200px)]"
            >
              Your browser does not support the video tag.
            </video>

            <!-- File Info Bar -->
            <div class="mt-4 bg-black bg-opacity-50 rounded-lg p-4 max-w-2xl w-full">
              <div class="flex items-center justify-between text-white">
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium truncate">
                    {{ currentGalleryFile.filename }}
                  </p>
                  <p class="text-xs text-gray-300">
                    {{ formatFileSize(currentGalleryFile.file_size) }} •
                    {{ currentGalleryIndex + 1 }} / {{ selectedFolder.files.length }}
                  </p>
                </div>
                <a
                  :href="currentGalleryFile.download_url"
                  class="ml-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 transition-colors"
                  download
                  @click.stop
                >
                  <svg
                    class="mr-2 h-5 w-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                    />
                  </svg>
                  Download
                </a>
              </div>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </ContactLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import ContactLayout from '@/Layouts/ContactLayout.vue';

const props = defineProps({
  folders: {
    type: Array,
    default: () => [],
  },
  totalFiles: {
    type: Number,
    default: 0,
  },
});

const selectedFolder = ref(null);
const galleryOpen = ref(false);
const currentGalleryIndex = ref(0);

const selectFolder = (folder) => {
  selectedFolder.value = folder;
};

const currentGalleryFile = computed(() => {
  if (!selectedFolder.value || !galleryOpen.value) return null;
  return selectedFolder.value.files[currentGalleryIndex.value];
});

const canGoPrevious = computed(() => {
  return currentGalleryIndex.value > 0;
});

const canGoNext = computed(() => {
  if (!selectedFolder.value) return false;
  return currentGalleryIndex.value < selectedFolder.value.files.length - 1;
});

const openGallery = (index) => {
  const file = selectedFolder.value.files[index];

  // For non-image/video files, trigger download instead
  if (!file.is_image && !file.is_video) {
    window.location.href = file.download_url;
    return;
  }

  currentGalleryIndex.value = index;
  galleryOpen.value = true;
  // Prevent body scroll when gallery is open
  document.body.style.overflow = 'hidden';
};

const closeGallery = () => {
  galleryOpen.value = false;
  // Restore body scroll
  document.body.style.overflow = '';
};

const nextMedia = () => {
  if (canGoNext.value) {
    currentGalleryIndex.value++;
  }
};

const previousMedia = () => {
  if (canGoPrevious.value) {
    currentGalleryIndex.value--;
  }
};

const handleKeydown = (e) => {
  if (!galleryOpen.value) return;

  switch (e.key) {
    case 'Escape':
      closeGallery();
      break;
    case 'ArrowLeft':
      previousMedia();
      break;
    case 'ArrowRight':
      nextMedia();
      break;
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
  // Ensure body scroll is restored
  document.body.style.overflow = '';
});

const formatFileSize = (bytes) => {
  if (!bytes) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
};
</script>

<style scoped>
/* Gallery fade-in animation */
@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.fixed.inset-0 {
  animation: fadeIn 0.2s ease-out;
}

/* Smooth image loading */
img {
  transition: opacity 0.2s ease-in-out;
}

img[src] {
  opacity: 1;
}
</style>
