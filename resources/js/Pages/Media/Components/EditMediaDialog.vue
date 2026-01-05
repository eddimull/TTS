<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    :header="'Edit ' + (localMedia?.title || 'Media')"
    :style="{ width: '50rem' }"
    @hide="handleClose"
  >
    <div v-if="localMedia" class="space-y-4">
      <!-- Title -->
      <div class="field">
        <label for="title" class="block text-sm font-medium mb-2">Title</label>
        <InputText
          id="title"
          v-model="localMedia.title"
          class="w-full"
          placeholder="Enter title"
        />
      </div>

      <!-- Description -->
      <div class="field">
        <label for="description" class="block text-sm font-medium mb-2">Description</label>
        <Textarea
          id="description"
          v-model="localMedia.description"
          class="w-full"
          rows="3"
          placeholder="Enter description"
        />
      </div>

      <!-- Folder Path -->
      <div class="field">
        <label for="folder" class="block text-sm font-medium mb-2">Folder</label>
        <Dropdown
          id="folder"
          v-model="localMedia.folder_path"
          :options="folderOptions"
          option-label="label"
          option-value="value"
          placeholder="Select folder or leave in root"
          class="w-full"
          show-clear
        />
      </div>

      <!-- Tags -->
      <div class="field">
        <label for="tags" class="block text-sm font-medium mb-2">Tags</label>
        <div class="space-y-2">
          <!-- Selected Tags -->
          <div class="flex flex-wrap gap-2 min-h-[2.5rem] p-2 border rounded">
            <Tag
              v-for="tagId in selectedTags"
              :key="tagId"
              :value="getTagName(tagId)"
              :style="getTagStyle(tagId)"
              class="cursor-pointer"
            >
              <span class="mr-1">{{ getTagName(tagId) }}</span>
              <i class="pi pi-times text-xs" @click="removeTag(tagId)" />
            </Tag>
            <span v-if="!selectedTags.length" class="text-gray-400 text-sm">No tags selected</span>
          </div>

          <!-- Tag Input -->
          <div class="flex gap-2">
            <AutoComplete
              v-model="tagInput"
              :suggestions="filteredTags"
              option-label="name"
              placeholder="Type to search or create new tag..."
              class="flex-1"
              @complete="searchTags"
              @item-select="selectExistingTag"
              @keyup.enter="handleTagEnter"
            >
              <template #option="{ option }">
                <div class="flex items-center gap-2">
                  <div
                    class="w-4 h-4 rounded"
                    :style="{ backgroundColor: option.color || '#3B82F6' }"
                  />
                  <span>{{ option.name }}</span>
                </div>
              </template>
            </AutoComplete>
          </div>
          <div class="text-xs text-gray-500">
            Type and press Enter to create a new tag, or select from existing tags
          </div>
        </div>
      </div>

      <!-- Associations -->
      <div class="field">
        <label class="block text-sm font-medium mb-2">Associations</label>

        <div class="space-y-2">
          <!-- Bookings -->
          <div v-if="localMedia.associations?.some(a => a.associable_type === 'App\\Models\\Bookings')" class="bg-gray-50 dark:bg-gray-800 p-3 rounded">
            <div class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Linked Bookings</div>
            <div
              v-for="assoc in localMedia.associations.filter(a => a.associable_type === 'App\\Models\\Bookings')"
              :key="assoc.id"
              class="flex items-center gap-2"
            >
              <i class="pi pi-calendar text-xs" />
              <span class="text-sm">{{ assoc.associable?.name || 'Booking #' + assoc.associable_id }}</span>
            </div>
          </div>

          <!-- Events -->
          <div v-if="localMedia.associations?.some(a => a.associable_type === 'App\\Models\\Events')" class="bg-gray-50 dark:bg-gray-800 p-3 rounded">
            <div class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Linked Events</div>
            <div
              v-for="assoc in localMedia.associations.filter(a => a.associable_type === 'App\\Models\\Events')"
              :key="assoc.id"
              class="flex items-center gap-2"
            >
              <i class="pi pi-star text-xs" />
              <span class="text-sm">{{ assoc.associable?.title || 'Event #' + assoc.associable_id }}</span>
            </div>
          </div>

          <!-- No associations -->
          <div v-if="!localMedia.associations?.length" class="text-sm text-gray-500 italic">
            No bookings or events linked
          </div>
        </div>
      </div>

      <!-- File Info (Read-only) -->
      <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded space-y-1">
        <div class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">File Information</div>
        <div class="text-sm"><strong>Filename:</strong> {{ localMedia.filename }}</div>
        <div class="text-sm"><strong>Type:</strong> {{ localMedia.media_type }}</div>
        <div class="text-sm"><strong>Size:</strong> {{ localMedia.formatted_size }}</div>
        <div class="text-sm"><strong>Uploaded:</strong> {{ formatDate(localMedia.created_at) }}</div>
        <div v-if="localMedia.uploader" class="text-sm"><strong>Uploader:</strong> {{ localMedia.uploader.name }}</div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Cancel"
          severity="secondary"
          @click="handleClose"
        />
        <Button
          label="Save Changes"
          icon="pi pi-check"
          @click="handleSave"
        />
      </div>
    </template>
  </Dialog>
</template>

<script>
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Dropdown from 'primevue/dropdown';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

export default {
  name: 'EditMediaDialog',
  components: {
    Dialog,
    InputText,
    Textarea,
    Dropdown,
    AutoComplete,
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
    folders: {
      type: Array,
      default: () => []
    },
    availableTags: {
      type: Array,
      default: () => []
    }
  },
  emits: ['update:visible', 'saved'],
  data() {
    return {
      localMedia: null,
      selectedTags: [],
      tagInput: '',
      filteredTags: []
    };
  },
  computed: {
    isVisible: {
      get() {
        return this.visible;
      },
      set(value) {
        this.$emit('update:visible', value);
      }
    },
    folderOptions() {
      const options = [
        { label: 'Root (No folder)', value: null }
      ];

      // Filter out Google Drive synced folders (one-way sync only)
      this.folders
        .filter(folder => !folder.is_drive_synced)
        .forEach(folder => {
          const indent = '\u00A0\u00A0'.repeat(folder.depth);
          options.push({
            label: indent + folder.name + ` (${folder.file_count})`,
            value: folder.path
          });
        });

      return options;
    }
  },
  watch: {
    media: {
      immediate: true,
      handler(newMedia) {
        if (newMedia) {
          this.localMedia = { ...newMedia };
          this.selectedTags = newMedia.tags ? newMedia.tags.map(tag => tag.id) : [];
        }
      }
    }
  },
  methods: {
    handleClose() {
      this.isVisible = false;
      this.localMedia = null;
      this.selectedTags = [];
    },
    handleSave() {
      if (!this.localMedia) return;

      const formData = {
        title: this.localMedia.title,
        description: this.localMedia.description,
        folder_path: this.localMedia.folder_path,
        tags: this.selectedTags
      };

      router.patch(route('media.update', this.localMedia.id), formData, {
        preserveScroll: true,
        onSuccess: () => {
          this.$emit('saved');
          this.handleClose();
        }
      });
    },
    getTagName(tagId) {
      const tag = this.availableTags.find(t => t.id === tagId);
      return tag ? tag.name : '';
    },
    getTagStyle(tagId) {
      const tag = this.availableTags.find(t => t.id === tagId);
      return tag && tag.color ? { backgroundColor: tag.color } : {};
    },
    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString();
    },
    searchTags(event) {
      const query = event.query.toLowerCase();
      this.filteredTags = this.availableTags.filter(tag => 
        tag.name.toLowerCase().includes(query) && 
        !this.selectedTags.includes(tag.id)
      );
    },
    selectExistingTag(event) {
      const tag = event.value;
      if (tag && !this.selectedTags.includes(tag.id)) {
        this.selectedTags.push(tag.id);
      }
      this.tagInput = '';
      this.filteredTags = [];
    },
    handleTagEnter() {
      const tagName = typeof this.tagInput === 'string' 
        ? this.tagInput.trim() 
        : this.tagInput?.name?.trim() || '';

      if (!tagName) return;

      // Check if tag already exists
      const existingTag = this.availableTags.find(
        t => t.name.toLowerCase() === tagName.toLowerCase()
      );

      if (existingTag) {
        // Add existing tag if not already selected
        if (!this.selectedTags.includes(existingTag.id)) {
          this.selectedTags.push(existingTag.id);
        }
        this.tagInput = '';
        this.filteredTags = [];
      } else {
        // Create new tag
        this.createNewTag(tagName);
      }
    },
    createNewTag(tagName) {
      const bandId = this.localMedia?.band_id || this.$page.props.auth?.user?.current_band_id;

      // Generate a random color for new tags
      const colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6'];
      const randomColor = colors[Math.floor(Math.random() * colors.length)];

      axios.post(route('media.tags.store'), {
        band_id: bandId,
        name: tagName,
        color: randomColor
      })
      .then(response => {
        const newTag = response.data;
        
        // Add new tag to availableTags array
        this.availableTags.push(newTag);
        
        // Add to selected tags
        if (!this.selectedTags.includes(newTag.id)) {
          this.selectedTags.push(newTag.id);
        }
        
        this.tagInput = '';
        this.filteredTags = [];
      })
      .catch(error => {
        console.error('Error creating tag:', error);
        alert('Failed to create tag. Please try again.');
      });
    },
    removeTag(tagId) {
      this.selectedTags = this.selectedTags.filter(id => id !== tagId);
    }
  }
};
</script>
