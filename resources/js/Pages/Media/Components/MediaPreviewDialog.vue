<template>
  <Dialog
    v-model:visible="isVisible"
    :header="media?.title || 'Preview'"
    :style="{ width: '90vw', maxWidth: '1200px' }"
    :modal="true"
    :maximizable="true"
  >
    <div v-if="media" class="media-preview-container">
      <!-- Image Preview -->
      <div v-if="media.media_type === 'image'" class="flex justify-center items-center bg-black rounded">
        <img
          :src="previewUrl"
          :alt="media.title"
          class="max-w-full max-h-[70vh] object-contain"
        />
      </div>

      <!-- Video Preview -->
      <div v-else-if="media.media_type === 'video'" class="flex justify-center items-center bg-black rounded">
        <video
          :src="previewUrl"
          controls
          class="max-w-full max-h-[70vh]"
          preload="metadata"
        >
          Your browser does not support the video tag.
        </video>
      </div>

      <!-- Audio Preview -->
      <div v-else-if="media.media_type === 'audio'" class="flex flex-col items-center justify-center p-8 space-y-4">
        <i class="pi pi-volume-up text-6xl text-gray-400" />
        <div class="text-lg font-semibold">{{ media.filename }}</div>
        <audio
          :src="previewUrl"
          controls
          class="w-full max-w-lg"
          preload="metadata"
        >
          Your browser does not support the audio tag.
        </audio>
      </div>

      <!-- PDF Preview -->
      <div v-else-if="media.mime_type === 'application/pdf'" class="h-[70vh]">
        <iframe
          :src="previewUrl"
          class="w-full h-full border-0 rounded"
          title="PDF Preview"
        />
      </div>

      <!-- Document/Other - No preview available -->
      <div v-else class="flex flex-col items-center justify-center p-12 space-y-4">
        <i :class="getMediaIcon(media.media_type)" class="text-6xl text-gray-400" />
        <div class="text-lg font-semibold">{{ media.filename }}</div>
        <div class="text-sm text-gray-500">Preview not available for this file type</div>
        <Button
          label="Download File"
          icon="pi pi-download"
          @click="$emit('download', media.id)"
        />
      </div>

      <!-- File Info Footer -->
      <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <div class="text-gray-500 dark:text-gray-400">Type</div>
            <div class="font-medium">{{ media.media_type }}</div>
          </div>
          <div>
            <div class="text-gray-500 dark:text-gray-400">Size</div>
            <div class="font-medium">{{ media.formatted_size }}</div>
          </div>
          <div>
            <div class="text-gray-500 dark:text-gray-400">Uploaded</div>
            <div class="font-medium">{{ formatDate(media.created_at) }}</div>
          </div>
          <div v-if="media.uploader">
            <div class="text-gray-500 dark:text-gray-400">Uploaded by</div>
            <div class="font-medium">{{ media.uploader.name }}</div>
          </div>
        </div>

        <!-- Tags -->
        <div v-if="media.tags && media.tags.length > 0" class="mt-4">
          <div class="text-gray-500 dark:text-gray-400 text-sm mb-2">Tags</div>
          <div class="flex gap-2 flex-wrap">
            <Tag
              v-for="tag in media.tags"
              :key="tag.id"
              :value="tag.name"
              :style="tag.color ? { backgroundColor: tag.color } : {}"
            />
          </div>
        </div>

        <!-- Folder Path -->
        <div v-if="media.folder_path" class="mt-4">
          <div class="text-gray-500 dark:text-gray-400 text-sm mb-2">Location</div>
          <div class="flex items-center gap-2">
            <i class="pi pi-folder text-sm" />
            <span class="text-sm">{{ media.folder_path }}</span>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-between items-center w-full">
        <div class="flex gap-2">
          <Button
            v-if="canEdit"
            label="Edit"
            icon="pi pi-pencil"
            severity="secondary"
            @click="$emit('edit', media)"
          />
          <Button
            label="Download"
            icon="pi pi-download"
            severity="secondary"
            @click="$emit('download', media.id)"
          />
        </div>
        <Button
          label="Close"
          icon="pi pi-times"
          @click="isVisible = false"
        />
      </div>
    </template>
  </Dialog>
</template>

<script>
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import Tag from 'primevue/tag';

export default {
  name: 'MediaPreviewDialog',
  components: {
    Dialog,
    Button,
    Tag
  },
  props: {
    visible: {
      type: Boolean,
      required: true
    },
    media: {
      type: Object,
      default: null
    },
    canEdit: {
      type: Boolean,
      default: false
    }
  },
  emits: ['update:visible', 'edit', 'download'],
  computed: {
    isVisible: {
      get() {
        return this.visible;
      },
      set(value) {
        this.$emit('update:visible', value);
      }
    },
    previewUrl() {
      if (!this.media) return '';
      // For system files (charts, contracts, etc.), use the direct URL
      if (this.media.is_system_file && this.media.url) {
        return this.media.url;
      }
      // For regular media files, use the media.serve route
      return this.route('media.serve', this.media.id);
    }
  },
  methods: {
    getMediaIcon(type) {
      const icons = {
        image: 'pi pi-image',
        video: 'pi pi-video',
        audio: 'pi pi-volume-up',
        document: 'pi pi-file-pdf',
        other: 'pi pi-file'
      };
      return icons[type] || 'pi pi-file';
    },
    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString();
    }
  }
};
</script>

<style scoped>
.media-preview-container {
  min-height: 300px;
}
</style>
