<template>
  <breeze-authenticated-layout>
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
          <template v-if="currentFolder">
            Files will be uploaded to <strong>{{ currentFolder }}</strong>
          </template>
          <template v-else>
            Files will be uploaded to the root folder
          </template>
        </p>
      </div>
    </div>

    <template #header>
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center justify-between w-full md:w-auto">
          <div class="flex items-center gap-3">
            <!-- Mobile Menu Toggle -->
            <Button
              icon="pi pi-folder-open"
              class="md:!hidden border-2 border-gray-300 dark:border-gray-600"
              severity="secondary"
              @click="sidebarVisible = true"
            />
            <div>
              <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-50">
                Media Library
              </h2>
              <div v-if="currentFolder" class="block md:hidden text-xs text-gray-500">
                {{ currentFolder }}
              </div>
            </div>
          </div>
          <!-- Band Selector - Mobile -->
          <Dropdown
            v-if="availableBands && availableBands.length > 1"
            :model-value="selectedBand"
            :options="availableBands"
            option-label="name"
            placeholder="Select Band"
            class="block md:hidden w-auto"
            @change="changeBand"
          >
            <template #value="slotProps">
              <div v-if="slotProps.value" class="flex items-center gap-2">
                <i class="pi pi-users text-sm" />
                <span class="hidden sm:inline">{{ slotProps.value.name }}</span>
              </div>
              <span v-else>{{ slotProps.placeholder }}</span>
            </template>
            <template #option="slotProps">
              <div class="flex items-center gap-2">
                <i class="pi pi-users text-sm" />
                <span>{{ slotProps.option.name }}</span>
              </div>
            </template>
          </Dropdown>
        </div>
        
        <!-- Desktop Controls -->
        <div class="hidden md:flex items-center gap-4">
          <!-- Band Selector - Desktop -->
          <Dropdown
            v-if="availableBands && availableBands.length > 1"
            :model-value="selectedBand"
            :options="availableBands"
            option-label="name"
            placeholder="Select Band"
            class="w-64"
            @change="changeBand"
          >
            <template #value="slotProps">
              <div v-if="slotProps.value" class="flex items-center gap-2">
                <i class="pi pi-users text-sm" />
                <span>{{ slotProps.value.name }}</span>
              </div>
              <span v-else>{{ slotProps.placeholder }}</span>
            </template>
            <template #option="slotProps">
              <div class="flex items-center gap-2">
                <i class="pi pi-users text-sm" />
                <span>{{ slotProps.option.name }}</span>
              </div>
            </template>
          </Dropdown>
          
          <!-- Storage Quota Display -->
          <div class="text-sm" v-if="quota">
            <div class="flex items-center gap-2">
              <span class="text-gray-600 dark:text-gray-400 whitespace-nowrap">
                {{ quota.formatted_used }} / {{ quota.formatted_limit }}
              </span>
              <div class="w-32 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                <div
                  class="h-full transition-all duration-300"
                  :class="quota.percentage > 90 ? 'bg-red-500' : quota.percentage > 75 ? 'bg-yellow-500' : 'bg-blue-500'"
                  :style="{ width: Math.min(quota.percentage, 100) + '%' }"
                />
              </div>
            </div>
          </div>

          <Button
            v-if="$page.props.auth.user.navigation && $page.props.auth.user.navigation.Media && $page.props.auth.user.navigation.Media.write"
            label="Upload Files"
            icon="pi pi-upload"
            @click="showUploadDialog = true"
          />
        </div>
        
        <!-- Mobile Upload Button -->
        <Button
          v-if="$page.props.auth.user.navigation && $page.props.auth.user.navigation.Media && $page.props.auth.user.navigation.Media.write"
          label="Upload"
          icon="pi pi-upload"
          size="small"
          class="block md:!hidden w-full"
          @click="showUploadDialog = true"
        />
      </div>
    </template>

    <Container>
      <div class="flex gap-4">
        <!-- Mobile Sidebar Drawer -->
        <Sidebar
          v-model:visible="sidebarVisible"
          position="left"
          class="block md:hidden"
        >
          <template #header>
            <h3 class="font-semibold text-lg">Folders</h3>
          </template>
          <FolderSidebar
            :folders="folders"
            :current-folder="currentFolder"
            :can-write="$page.props.auth.user.navigation && $page.props.auth.user.navigation.Media && $page.props.auth.user.navigation.Media.write"
            :band-id="currentBandId"
            @navigate="navigateToFolder"
            @create-folder="showCreateFolderDialog = true"
            @folder-renamed="handleFolderRenamed"
            @folder-deleted="handleFolderDeleted"
            @drop-file="handleDropFile"
            @folder-created="handleFolderCreated"
          />
        </Sidebar>

        <!-- Desktop Sidebar -->
        <div class="hidden md:block w-64 flex-shrink-0">
          <FolderSidebar
            :folders="folders"
            :current-folder="currentFolder"
            :can-write="$page.props.auth.user.navigation && $page.props.auth.user.navigation.Media && $page.props.auth.user.navigation.Media.write"
            :band-id="currentBandId"
            @navigate="navigateToFolder"
            @create-folder="showCreateFolderDialog = true"
            @folder-renamed="handleFolderRenamed"
            @folder-deleted="handleFolderDeleted"
            @drop-file="handleDropFile"
            @folder-created="handleFolderCreated"
          />
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 min-w-0 overflow-hidden">
          <!-- Toolbar with filters -->
          <Toolbar class="mb-4">
            <template #start>
              <div class="flex flex-wrap items-center gap-2">
                <!-- Search -->
                <IconField class="w-full sm:w-48">
                  <InputIcon class="pi pi-search" />
                  <InputText
                    v-model="localFilters.search"
                    placeholder="Search..."
                    class="w-full"
                    @input="debouncedSearch"
                  />
                </IconField>

                <!-- Media type filter -->
                <Dropdown
                  v-model="localFilters.media_type"
                  :options="mediaTypes"
                  option-label="label"
                  option-value="value"
                  placeholder="All Types"
                  class="w-full sm:w-auto"
                  @change="applyFilters"
                />

                <!-- Tag filter -->
                <MultiSelect
                  v-model="localFilters.tags"
                  :options="tags"
                  option-label="name"
                  option-value="id"
                  placeholder="Filter by tags"
                  class="w-full sm:w-auto"
                  display="chip"
                  :show-toggle-all="false"
                  @change="applyFilters"
                >
                  <template #chip="{ value }">
                    <span class="px-2 py-1 rounded text-xs" :style="getTagStyle(value)">
                      {{ getTagName(value) }}
                    </span>
                  </template>
                  <template #option="{ option }">
                    <div class="flex items-center gap-2">
                      <div
                        class="w-3 h-3 rounded-full"
                        :style="{ backgroundColor: option.color || '#3B82F6' }"
                      />
                      <span>{{ option.name }}</span>
                    </div>
                  </template>
                </MultiSelect>

                <!-- Clear filters button -->
                <Button
                  v-if="hasActiveFilters"
                  icon="pi pi-filter-slash"
                  severity="secondary"
                  size="small"
                  @click="clearFilters"
                />
              </div>
            </template>

            <template #end>
              <div class="flex items-center gap-2">
                <!-- View mode toggle -->
                <Button
                  icon="pi pi-th-large"
                  :class="viewMode === 'grid' ? 'p-button-outlined' : 'p-button-text'"
                  @click="viewMode = 'grid'"
                />
                <Button
                  icon="pi pi-list"
                  :class="viewMode === 'table' ? 'p-button-outlined' : 'p-button-text'"
                  @click="viewMode = 'table'"
                />
              </div>
            </template>
          </Toolbar>

          <!-- Bulk Operations Toolbar -->
          <BulkOperationsToolbar
            v-if="$page.props.auth.user.navigation && $page.props.auth.user.navigation.Media && $page.props.auth.user.navigation.Media.write"
            :selected-count="selectedMediaIds.length"
            :selected-ids="selectedMediaIds"
            :folders="folders"
            :current-band-id="currentBandId"
            @select-all="selectAllMedia"
            @deselect-all="deselectAllMedia"
            @deleted="handleBulkDeleted"
          />

          <!-- Grid View -->
          <div v-if="viewMode === 'grid'" class="mt-6">
            <div
              v-if="media.data.length === 0"
              class="text-center py-8 sm:py-16 px-4"
            >
              <i class="pi pi-folder-open text-4xl sm:text-6xl text-gray-300 mb-4" />
              <p class="text-lg sm:text-xl text-gray-500 mb-2">
                {{ currentFolder ? `Folder "${currentFolder}" is empty` : 'No media found' }}
              </p>
              <p class="text-gray-400 mb-4">
                <template v-if="localFilters.search">
                  Try a different search term
                </template>
                <template v-else-if="currentFolder">
                  Upload files to this folder to get started
                </template>
                <template v-else>
                  Click "Upload Files" to add media
                </template>
              </p>
              <Button
                v-if="$page.props.auth.user.navigation && $page.props.auth.user.navigation.Media && $page.props.auth.user.navigation.Media.write && currentFolder"
                label="Upload Files Here"
                icon="pi pi-upload"
                @click="openUploadToCurrentFolder"
              />
            </div>

            <div
              v-else
              class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
            >
              <MediaCard
                v-for="item in media.data"
                :key="item.id"
                :media="item"
                :is-selected="selectedMediaIds.includes(item.id)"
                :can-write="$page.props.auth.user.navigation && $page.props.auth.user.navigation.Media && $page.props.auth.user.navigation.Media.write"
                @select="toggleMediaSelection"
                @preview="handlePreviewMedia"
                @edit="handleEditMedia"
                @download="downloadMedia"
                @delete="deleteMedia"
                @move="handleMoveMedia"
              />
            </div>

            <!-- Pagination -->
            <div v-if="media.last_page > 1" class="mt-6 flex justify-center">
              <Paginator
                :rows="media.per_page"
                :total-records="media.total"
                :first="(media.current_page - 1) * media.per_page"
                @page="onPage"
              />
            </div>
          </div>

          <!-- Table View -->
          <div v-else class="overflow-x-auto">
            <DataTable
              :value="media.data"
              striped-rows
              :paginator="media.last_page > 1"
              :rows="media.per_page"
              :total-records="media.total"
              lazy
              responsive-layout="scroll"
              @page="onPage"
            >
            <Column header="Preview" style="width: 100px">
              <template #body="slotProps">
                <img
                  v-if="slotProps.data.media_type === 'image'"
                  :src="slotProps.data.url"
                  class="w-16 h-16 object-cover rounded"
                />
                <div
                  v-else
                  class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded flex items-center justify-center"
                >
                  <i :class="getMediaIcon(slotProps.data.media_type)" class="text-2xl text-gray-400" />
                </div>
              </template>
            </Column>

            <Column field="title" header="Title" :sortable="true">
              <template #body="slotProps">
                <div class="font-semibold">{{ slotProps.data.title }}</div>
                <div class="text-sm text-gray-500">{{ slotProps.data.filename }}</div>
              </template>
            </Column>

            <Column field="media_type" header="Type" :sortable="true">
              <template #body="slotProps">
                <Tag :value="slotProps.data.media_type" />
              </template>
            </Column>

            <Column field="formatted_size" header="Size" :sortable="true" />

            <Column field="created_at" header="Uploaded" :sortable="true">
              <template #body="slotProps">
                {{ formatDate(slotProps.data.created_at) }}
              </template>
            </Column>

            <Column header="Actions" style="width: 150px">
              <template #body="slotProps">
                <div class="flex gap-2">
                  <Button
                    icon="pi pi-download"
                    severity="secondary"
                    size="small"
                    @click="downloadMedia(slotProps.data.id)"
                  />
                  <Button
                    v-if="$page.props.auth.user.navigation && $page.props.auth.user.navigation.Media && $page.props.auth.user.navigation.Media.write"
                    icon="pi pi-trash"
                    severity="danger"
                    size="small"
                    @click="deleteMedia(slotProps.data.id)"
                  />
                </div>
              </template>
            </Column>
            </DataTable>
          </div>
        </div>
      </div>

      <!-- Upload Dialog -->
      <Dialog
        v-model:visible="showUploadDialog"
        :style="{ width: '700px' }"
        header="Upload Media Files"
        :modal="true"
      >
        <div class="space-y-4">
          <!-- File upload -->
          <div>
            <label class="block mb-2 font-medium">Files</label>
            <FileUpload
              ref="fileUpload"
              mode="advanced"
              :multiple="true"
              accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx"
              :max-file-size="104857600"
              :show-upload-button="false"
              :show-cancel-button="false"
              @select="onFileSelect"
            >
              <template #empty>
                <p>Drag and drop files here to upload.</p>
              </template>
            </FileUpload>
          </div>

          <!-- Folder selection -->
          <div>
            <label class="block mb-2 font-medium">Folder (Optional)</label>
            <Dropdown
              v-model="uploadForm.folder_path"
              :options="folderOptions"
              option-label="label"
              option-value="value"
              placeholder="Select or type folder name"
              editable
              class="w-full"
            />
            <small class="text-gray-500">Create a new folder by typing a name</small>
          </div>

          <!-- Associate with Booking/Event -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block mb-2 font-medium">Link to Booking (Optional)</label>
              <Dropdown
                v-model="uploadForm.booking_id"
                :options="bookings"
                option-label="name"
                option-value="id"
                placeholder="Select booking"
                show-clear
                class="w-full"
              >
                <template #option="slotProps">
                  <div>
                    <div>{{ slotProps.option.name }}</div>
                    <small class="text-gray-500">{{ formatDate(slotProps.option.date) }}</small>
                  </div>
                </template>
              </Dropdown>
            </div>

            <div>
              <label class="block mb-2 font-medium">Link to Event (Optional)</label>
              <Dropdown
                v-model="uploadForm.event_id"
                :options="events"
                option-label="name"
                option-value="id"
                placeholder="Select event"
                show-clear
                class="w-full"
              >
                <template #option="slotProps">
                  <div>
                    <div>{{ slotProps.option.name }}</div>
                    <small class="text-gray-500">{{ formatDate(slotProps.option.date) }}</small>
                  </div>
                </template>
              </Dropdown>
            </div>
          </div>

          <!-- Upload progress -->
          <div v-if="uploading" class="space-y-2">
            <ProgressBar :value="uploadProgress" />
            <p class="text-sm text-gray-600">Uploading files...</p>
          </div>
        </div>

        <template #footer>
          <div class="flex justify-end gap-2">
            <Button
              label="Cancel"
              icon="pi pi-times"
              severity="secondary"
              :disabled="uploading"
              @click="showUploadDialog = false"
            />
            <Button
              label="Upload"
              icon="pi pi-upload"
              :disabled="!hasFiles || uploading"
              :loading="uploading"
              @click="uploadFiles"
            />
          </div>
        </template>
      </Dialog>

      <!-- Create Folder Dialog -->
      <Dialog
        v-model:visible="showCreateFolderDialog"
        :style="{ width: '450px' }"
        header="Create New Folder"
        :modal="true"
      >
        <div class="space-y-4">
          <div v-if="currentFolder">
            <label class="block mb-2 font-medium text-sm">Current Location</label>
            <div class="px-3 py-2 bg-gray-50 dark:bg-gray-800 rounded text-sm">
              {{ currentFolder }}
            </div>
          </div>
          <div>
            <label class="block mb-2 font-medium text-sm">Folder Name</label>
            <InputText
              v-model="newFolderName"
              placeholder="e.g., 2024 or Contracts"
              class="w-full"
              @keyup.enter="createFolder"
            />
            <small class="text-gray-500">
              <template v-if="currentFolder">
                Will create: {{ currentFolder }}/{{ newFolderName || '...' }}
              </template>
              <template v-else>
                Use slashes (/) for nested folders (e.g., Photos/2024)
              </template>
            </small>
          </div>
        </div>

        <template #footer>
          <div class="flex justify-end gap-2">
            <Button
              label="Cancel"
              severity="secondary"
              @click="showCreateFolderDialog = false"
            />
            <Button
              label="Create & Navigate"
              icon="pi pi-folder-plus"
              :disabled="!newFolderName"
              @click="createFolder"
            />
          </div>
        </template>
      </Dialog>

      <!-- Edit Media Dialog -->
      <EditMediaDialog
        v-model:visible="showEditDialog"
        :media="editingMedia"
        :folders="folders"
        :available-tags="tags"
        @saved="handleMediaSaved"
      />

      <!-- Preview Media Dialog -->
      <MediaPreviewDialog
        v-model:visible="showPreviewDialog"
        :media="previewingMedia"
        :can-edit="$page.props.auth.user.navigation && $page.props.auth.user.navigation.Media && $page.props.auth.user.navigation.Media.write"
        @edit="handlePreviewEdit"
        @download="downloadMedia"
      />

      <!-- Move File Dialog -->
      <Dialog
        v-model:visible="showMoveDialog"
        :style="{ width: '400px' }"
        header="Move File"
        :modal="true"
      >
        <div v-if="movingMedia" class="space-y-4">
          <p class="text-sm">
            Move <strong>{{ movingMedia.title }}</strong> to:
          </p>
          <div>
            <label class="block mb-2 font-medium">Destination Folder</label>
            <Dropdown
              v-model="moveDestination"
              :options="folderOptions"
              option-label="label"
              option-value="value"
              placeholder="Select folder"
              class="w-full"
              show-clear
            />
          </div>
        </div>

        <template #footer>
          <div class="flex justify-end gap-2">
            <Button
              label="Cancel"
              icon="pi pi-times"
              severity="secondary"
              @click="showMoveDialog = false"
            />
            <Button
              label="Move"
              icon="pi pi-folder"
              @click="confirmMoveFile"
            />
          </div>
        </template>
      </Dialog>
    </Container>
  </breeze-authenticated-layout>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import PrimeCard from 'primevue/card';
import MultiSelect from 'primevue/multiselect';
import Dropdown from 'primevue/dropdown';
import Tag from 'primevue/tag';
import Sidebar from 'primevue/sidebar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import FolderSidebar from './Components/FolderSidebar.vue';
import MediaCard from './Components/MediaCard.vue';
import EditMediaDialog from './Components/EditMediaDialog.vue';
import BulkOperationsToolbar from './Components/BulkOperationsToolbar.vue';
import MediaPreviewDialog from './Components/MediaPreviewDialog.vue';
import { debounce } from 'lodash';

export default {
  components: {
    BreezeAuthenticatedLayout,
    PrimeCard,
    MultiSelect,
    Dropdown,
    Tag,
    Sidebar,
    IconField,
    InputIcon,
    FolderSidebar,
    MediaCard,
    EditMediaDialog,
    BulkOperationsToolbar,
    MediaPreviewDialog
  },
  props: {
    media: Object,
    tags: Array,
    folders: Array,
    quota: Object,
    filters: Object,
    availableBands: Array,
    currentBandId: Number,
    bookings: Array,
    events: Array,
  },
  data() {
    return {
      viewMode: 'grid',
      showUploadDialog: false,
      showCreateFolderDialog: false,
      showEditDialog: false,
      showMoveDialog: false,
      showPreviewDialog: false,
      sidebarVisible: false,
      selectedFiles: [],
      selectedMediaIds: [],
      editingMedia: null,
      movingMedia: null,
      previewingMedia: null,
      moveDestination: null,
      uploading: false,
      uploadProgress: 0,
      newFolderName: '',
      uploadForm: {
        folder_path: null,
        booking_id: null,
        event_id: null,
      },
      localFilters: { 
        ...this.filters,
        tags: this.parseTagsFilter(this.filters?.tags)
      },
      mediaTypes: [
        { label: 'All Types', value: null },
        { label: 'Images', value: 'image' },
        { label: 'Videos', value: 'video' },
        { label: 'Audio', value: 'audio' },
        { label: 'Documents', value: 'document' },
        { label: 'Other', value: 'other' }
      ],
      isDraggingFiles: false,
      dragCounter: 0,
      isApplyingFilters: false
    };
  },
  computed: {
    hasFiles() {
      return this.selectedFiles.length > 0;
    },
    currentFolder() {
      return this.localFilters.folder_path || null;
    },
    selectedBand() {
      if (!this.availableBands || !this.currentBandId) return null;
      // Handle both string and number ID comparisons
      return this.availableBands.find(b => b.id == this.currentBandId) || null;
    },
    folderOptions() {
      const options = this.folders.map(folder => ({
        label: folder.path,
        value: folder.path
      }));

      // Add "No folder" option
      options.unshift({ label: 'No folder (root)', value: null });

      return options;
    },
    hasActiveFilters() {
      return !!(this.localFilters.search || 
                this.localFilters.media_type || 
                (this.localFilters.tags && this.localFilters.tags.length > 0));
    },
    uploadButtonLabel() {
      return window.innerWidth < 640 ? 'Upload' : 'Upload Files';
    },
    clearFiltersLabel() {
      return window.innerWidth < 640 ? '' : 'Clear';
    }
  },
  watch: {
    filters: {
      handler(newFilters) {
        // Only update if we're not currently applying filters
        if (!this.isApplyingFilters) {
          this.localFilters = {
            ...newFilters,
            tags: this.parseTagsFilter(newFilters?.tags)
          };
        }
      },
      deep: true
    }
  },
  created() {
    this.debouncedSearch = debounce(this.applyFilters, 500);
  },
  mounted() {
    // Add window-level drag and drop event listeners
    window.addEventListener('dragenter', this.handleWindowDragEnter);
    window.addEventListener('dragleave', this.handleWindowDragLeave);
    window.addEventListener('dragover', this.handleWindowDragOver);
    window.addEventListener('drop', this.handleWindowDrop);
  },
  unmounted() {
    // Clean up event listeners
    window.removeEventListener('dragenter', this.handleWindowDragEnter);
    window.removeEventListener('dragleave', this.handleWindowDragLeave);
    window.removeEventListener('dragover', this.handleWindowDragOver);
    window.removeEventListener('drop', this.handleWindowDrop);
  },
  methods: {
    parseTagsFilter(tags) {
      // Handle different formats: array, object (from URL params), or null
      if (!tags) return [];
      if (Array.isArray(tags)) {
        // Convert string IDs to numbers
        return tags.map(id => typeof id === 'string' ? parseInt(id) : id);
      }
      if (typeof tags === 'object') {
        // Handle object format from URL params like {0: 4, 1: 2}
        return Object.values(tags).map(id => typeof id === 'string' ? parseInt(id) : id);
      }
      return [];
    },
    onFileSelect(event) {
      this.selectedFiles = event.files;
    },
    navigateToFolder(folderPath) {
      this.localFilters.folder_path = folderPath;
      this.sidebarVisible = false; // Close mobile sidebar on navigation
      this.applyFilters();
    },
    createFolder() {
      if (!this.newFolderName) return;

      // Build the full folder path
      const folderPath = this.currentFolder
        ? `${this.currentFolder}/${this.newFolderName}`
        : this.newFolderName;

      // Create the folder on the backend
      this.$inertia.post(route('media.folders.create'), {
        band_id: this.currentBandId,
        folder_path: folderPath
      }, {
        preserveScroll: true,
        onSuccess: () => {
          this.showCreateFolderDialog = false;
          this.newFolderName = '';
          this.navigateToFolder(folderPath);
        }
      });
    },
    async uploadFiles() {
      if (!this.hasFiles) return;

      this.uploading = true;
      this.uploadProgress = 0;

      const formData = new FormData();
      formData.append('band_id', this.currentBandId);

      // Add folder path if selected
      if (this.uploadForm.folder_path) {
        formData.append('folder_path', this.uploadForm.folder_path);
      }

      // Add booking/event associations
      if (this.uploadForm.booking_id) {
        formData.append('booking_id', this.uploadForm.booking_id);
      }

      if (this.uploadForm.event_id) {
        formData.append('event_id', this.uploadForm.event_id);
      }

      this.selectedFiles.forEach((file, index) => {
        formData.append(`files[${index}]`, file);
      });

      try {
        await this.$inertia.post(this.route('media.upload'), formData, {
          forceFormData: true,
          preserveState: true,
          preserveScroll: true,
          onProgress: (progress) => {
            this.uploadProgress = Math.round(progress.percentage);
          },
          onSuccess: () => {
            this.showUploadDialog = false;
            this.selectedFiles = [];
            this.uploadForm = {
              folder_path: null,
              booking_id: null,
              event_id: null,
            };
            if (this.$refs.fileUpload) {
              this.$refs.fileUpload.clear();
            }
          },
          onError: (errors) => {
            alert('Upload failed: ' + Object.values(errors).join(', '));
          },
          onFinish: () => {
            this.uploading = false;
          }
        });
      } catch (error) {
        console.error('Upload error:', error);
        this.uploading = false;
      }
    },
    applyFilters() {
      this.isApplyingFilters = true;
      this.$inertia.get(this.route('media.index'), {
        ...this.localFilters,
        band_id: this.currentBandId
      }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
          this.isApplyingFilters = false;
        }
      });
    },
    onPage(event) {
      this.$inertia.get(this.route('media.index'), {
        ...this.localFilters,
        page: event.page + 1,
        band_id: this.currentBandId
      }, {
        preserveState: true,
        preserveScroll: true
      });
    },
    deleteMedia(mediaId) {
      if (confirm('Are you sure you want to delete this media file?')) {
        this.$inertia.delete(this.route('media.destroy', mediaId));
      }
    },
    downloadMedia(mediaId) {
      window.open(this.route('media.download', mediaId), '_blank');
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
    toggleMediaSelection(mediaId) {
      const index = this.selectedMediaIds.indexOf(mediaId);
      if (index > -1) {
        this.selectedMediaIds.splice(index, 1);
      } else {
        this.selectedMediaIds.push(mediaId);
      }
    },
    selectAllMedia() {
      this.selectedMediaIds = this.media.data.map(item => item.id);
    },
    deselectAllMedia() {
      this.selectedMediaIds = [];
    },
    handlePreviewMedia(media) {
      this.previewingMedia = media;
      this.showPreviewDialog = true;
    },
    handlePreviewEdit(media) {
      // Close preview dialog and open edit dialog
      this.showPreviewDialog = false;
      this.previewingMedia = null;
      this.editingMedia = media;
      this.showEditDialog = true;
    },
    handleEditMedia(media) {
      this.editingMedia = media;
      this.showEditDialog = true;
    },
    handleMoveMedia(media) {
      this.movingMedia = media;
      this.moveDestination = media.folder_path;
      this.showMoveDialog = true;
    },
    confirmMoveFile() {
      if (!this.movingMedia) return;

      this.$inertia.patch(route('media.update', this.movingMedia.id), {
        title: this.movingMedia.title,
        description: this.movingMedia.description,
        folder_path: this.moveDestination,
        tags: this.movingMedia.tags ? this.movingMedia.tags.map(tag => tag.id) : []
      }, {
        preserveScroll: true,
        onSuccess: () => {
          this.showMoveDialog = false;
          this.movingMedia = null;
          this.moveDestination = null;
        }
      });
    },
    handleMediaSaved() {
      this.showEditDialog = false;
      this.editingMedia = null;
    },
    handleBulkDeleted() {
      this.selectedMediaIds = [];
    },
    handleFolderRenamed() {
      this.$inertia.reload({ preserveScroll: true });
    },
    handleFolderDeleted() {
      this.$inertia.reload({ preserveScroll: true });
    },
    handleDropFile({ mediaId, folderPath }) {
      // Find the media item to get its current data
      const mediaItem = this.media.data.find(m => m.id === mediaId);
      if (!mediaItem) return;

      // Move the file to the folder using bulk move endpoint
      this.$inertia.post(route('media.bulk.move'), {
        band_id: this.currentBandId,
        media_ids: [mediaId],
        folder_path: folderPath
      }, {
        preserveScroll: true
      });
    },
    handleFolderCreated(folderPath) {
      // Navigate to the newly created folder
      this.navigateToFolder(folderPath);
    },
    openUploadToCurrentFolder() {
      // Pre-fill the upload form with the current folder
      this.uploadForm.folder_path = this.currentFolder;
      this.showUploadDialog = true;
    },
    handleWindowDragEnter(e) {
      // Check if user has write permissions
      if (!this.$page.props.auth.user.navigation?.Media?.write) {
        return;
      }

      // Check if dragging files (not dragging media cards for reordering)
      if (e.dataTransfer.types.includes('Files')) {
        this.dragCounter++;
        this.isDraggingFiles = true;
      }
    },
    handleWindowDragLeave(e) {
      this.dragCounter--;
      if (this.dragCounter === 0) {
        this.isDraggingFiles = false;
      }
    },
    handleWindowDragOver(e) {
      // Prevent default to allow drop
      if (this.isDraggingFiles) {
        e.preventDefault();
      }
    },    clearFilters() {
      this.localFilters = {
        search: '',
        media_type: null,
        tags: [],
        folder_path: this.currentFolder
      };
      this.applyFilters();
    },
    getTagName(tagId) {
      const tag = this.tags.find(t => t.id === tagId);
      return tag ? tag.name : '';
    },
    getTagStyle(tagId) {
      const tag = this.tags.find(t => t.id === tagId);
      return tag && tag.color ? { backgroundColor: tag.color } : { backgroundColor: '#3B82F6' };
    },    async handleWindowDrop(e) {
      e.preventDefault();
      this.isDraggingFiles = false;
      this.dragCounter = 0;

      // Check if user has write permissions
      if (!this.$page.props.auth.user.navigation?.Media?.write) {
        return;
      }

      const files = Array.from(e.dataTransfer.files);
      if (files.length === 0) return;

      // Upload files to current folder
      this.uploading = true;
      this.uploadProgress = 0;

      const formData = new FormData();
      formData.append('band_id', this.currentBandId);

      // Add current folder path if exists
      if (this.currentFolder) {
        formData.append('folder_path', this.currentFolder);
      }

      files.forEach((file, index) => {
        formData.append(`files[${index}]`, file);
      });

      try {
        await this.$inertia.post(this.route('media.upload'), formData, {
          forceFormData: true,
          preserveState: true,
          preserveScroll: true,
          onProgress: (progress) => {
            this.uploadProgress = Math.round(progress.percentage);
          },
          onSuccess: () => {
            // Reload to show new files
          },
          onError: (errors) => {
            alert('Upload failed: ' + Object.values(errors).join(', '));
          },
          onFinish: () => {
            this.uploading = false;
            this.uploadProgress = 0;
          }
        });
      } catch (error) {
        console.error('Upload error:', error);
        this.uploading = false;
      }
    },
    changeBand(event) {
      // Reload the page with the new band_id
      this.$inertia.get(this.route('media.index'), {
        band_id: event.value.id
      }, {
        preserveState: false
      });
    },
    getBandName(bandId) {
      const band = this.availableBands.find(b => b.id === bandId);
      return band ? band.name : '';
    }
  }
};
</script>
