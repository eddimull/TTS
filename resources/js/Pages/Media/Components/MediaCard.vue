<template>
  <PrimeCard
    :class="[
      'relative cursor-pointer transition-all duration-200',
      isSelected ? 'ring-2 ring-blue-500' : 'hover:shadow-lg',
      isDragging ? 'opacity-50' : ''
    ]"
    :draggable="canWrite && !media.is_system_file"
    @click="handleClick"
    @contextmenu.prevent="showContextMenu"
    @dragstart="handleDragStart"
    @dragend="handleDragEnd"
  >
    <!-- Selection Checkbox -->
    <div
      v-if="canWrite && !media.is_system_file"
      class="absolute top-2 left-2 z-10"
      @click.stop
    >
      <Checkbox
        :model-value="isSelected"
        :binary="true"
        @update:model-value="toggleSelection"
      />
    </div>

    <template #header>
      <div class="relative h-48 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-800 overflow-hidden">
        <!-- Image preview -->
        <img
          v-if="media.media_type === 'image'"
          :src="media.thumbnail_url || media.url"
          :alt="media.title"
          class="w-full h-full object-cover"
        />

        <!-- PDF preview - show icon instead of loading full PDF -->
        <div
          v-else-if="isPdfFile"
          class="w-full h-full flex items-center justify-center bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900 dark:to-red-800"
        >
          <i class="pi pi-file-pdf text-6xl text-red-500" />
        </div>

        <!-- Video preview - use thumbnail instead of loading video -->
        <div
          v-else-if="media.media_type === 'video'"
          class="relative w-full h-full bg-black"
        >
          <img
            v-if="media.thumbnail_url"
            :src="media.thumbnail_url"
            :alt="media.title"
            class="w-full h-full object-cover"
          />
          <div
            v-else
            class="w-full h-full flex items-center justify-center"
          >
            <i class="pi pi-video text-6xl text-gray-400" />
          </div>
          <!-- Play icon overlay -->
          <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <div class="bg-black bg-opacity-50 rounded-full p-4">
              <i class="pi pi-play text-white text-3xl" />
            </div>
          </div>
        </div>

        <!-- Icon for other types -->
        <div
          v-else
          class="w-full h-full flex items-center justify-center"
        >
          <i :class="getMediaIcon(media.media_type)" class="text-6xl text-gray-400" />
        </div>

        <!-- Media type badge -->
        <Tag
          :value="media.media_type"
          class="absolute top-2 right-2"
          severity="info"
        />
      </div>
    </template>

    <template #title>
      <div class="text-base truncate" :title="media.title">
        {{ media.title }}
      </div>
    </template>

    <template #content>
      <div class="space-y-2">
        <!-- Folder path -->
        <div v-if="media.folder_path" class="text-xs text-gray-500 flex items-center gap-1">
          <i class="pi pi-folder text-xs" />
          <span class="truncate">{{ media.folder_path }}</span>
        </div>

        <!-- Tags -->
        <div v-if="media.tags && media.tags.length > 0" class="flex gap-1 flex-wrap">
          <Tag
            v-for="tag in media.tags.slice(0, 3)"
            :key="tag.id"
            :value="tag.name"
            size="small"
            :style="tag.color ? { backgroundColor: tag.color } : {}"
          />
          <Tag
            v-if="media.tags.length > 3"
            :value="`+${media.tags.length - 3}`"
            size="small"
            severity="secondary"
          />
        </div>

        <!-- Metadata -->
        <div class="text-xs text-gray-500 dark:text-gray-400">
          <div>{{ media.formatted_size }}</div>
          <div>{{ formatDate(media.created_at) }}</div>
        </div>

        <!-- Actions -->
        <div v-if="!hideActions" class="flex gap-2 mt-2">
          <Button
            v-if="!media.is_system_file"
            icon="pi pi-pencil"
            severity="secondary"
            size="small"
            v-tooltip.top="'Edit'"
            @click.stop="$emit('edit', media)"
          />
          <Button
            icon="pi pi-download"
            severity="secondary"
            size="small"
            v-tooltip.top="'Download'"
            @click.stop="$emit('download', media.id)"
          />
          <Button
            v-if="canWrite && !media.is_system_file"
            icon="pi pi-trash"
            severity="danger"
            size="small"
            v-tooltip.top="'Delete'"
            @click.stop="$emit('delete', media.id)"
          />
        </div>
      </div>
    </template>
  </PrimeCard>

  <!-- Context Menu -->
  <ContextMenu ref="contextMenu" :model="contextMenuItems" />
</template>

<script>
import PrimeCard from 'primevue/card';
import ContextMenu from 'primevue/contextmenu';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Checkbox from 'primevue/checkbox';

export default {
  name: 'MediaCard',
  components: {
    PrimeCard,
    ContextMenu,
    Button,
    Tag,
    Checkbox
  },
  props: {
    media: {
      type: Object,
      required: true
    },
    isSelected: {
      type: Boolean,
      default: false
    },
    canWrite: {
      type: Boolean,
      default: false
    },
    hideActions: {
      type: Boolean,
      default: false
    }
  },
  emits: ['select', 'edit', 'download', 'delete', 'move', 'preview', 'drag-start', 'drag-end'],
  data() {
    return {
      isDragging: false
    };
  },
  computed: {
    isPdfFile() {
      return this.media.mime_type && 
             (this.media.mime_type === 'application/pdf' || 
              this.media.mime_type.indexOf('pdf') !== -1);
    },
    contextMenuItems() {
      const items = [];

      // Only show edit for non-system files
      if (!this.media.is_system_file) {
        items.push({
          label: 'Edit',
          icon: 'pi pi-pencil',
          command: () => this.$emit('edit', this.media)
        });
      }

      // Download is always available
      items.push({
        label: 'Download',
        icon: 'pi pi-download',
        command: () => this.$emit('download', this.media.id)
      });

      // Only show move/delete for non-system files with write permission
      if (this.canWrite && !this.media.is_system_file) {
        items.push(
          {
            separator: true
          },
          {
            label: 'Move to Folder',
            icon: 'pi pi-folder',
            command: () => this.$emit('move', this.media)
          },
          {
            separator: true
          },
          {
            label: 'Delete',
            icon: 'pi pi-trash',
            command: () => this.$emit('delete', this.media.id)
          }
        );
      }

      return items;
    }
  },
  methods: {
    handleClick() {
      this.$emit('preview', this.media);
    },
    toggleSelection(value) {
      this.$emit('select', this.media.id);
    },
    showContextMenu(event) {
      this.$refs.contextMenu.show(event);
    },
    onVideoLoaded(event) {
      // Seek to 1 second to get a better thumbnail frame
      const video = event.target;
      if (video.duration > 1) {
        video.currentTime = 1;
      }
    },
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
    },
    handleDragStart(event) {
      this.isDragging = true;
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('application/json', JSON.stringify({
        id: this.media.id,
        title: this.media.title
      }));
      this.$emit('drag-start', this.media);
    },
    handleDragEnd() {
      this.isDragging = false;
      this.$emit('drag-end');
    }
  }
};
</script>
