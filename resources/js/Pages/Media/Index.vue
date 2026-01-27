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
            :disabled="isInSyncedFolder"
            @click="showUploadDialog = true"
            v-tooltip.bottom="isInSyncedFolder ? 'Cannot upload to Google Drive synced folders (one-way sync)' : 'Upload files to media library'"
          />
        </div>
        
        <!-- Mobile Upload Button -->
        <Button
          v-if="$page.props.auth.user.navigation && $page.props.auth.user.navigation.Media && $page.props.auth.user.navigation.Media.write"
          label="Upload"
          icon="pi pi-upload"
          size="small"
          class="block md:!hidden w-full"
          :disabled="isInSyncedFolder"
          @click="showUploadDialog = true"
          v-tooltip.bottom="isInSyncedFolder ? 'Cannot upload to Google Drive synced folders' : ''"
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
            :can-write="canUploadOrCreate"
            :band-id="currentBandId"
            :is-synced-folder="isInSyncedFolder"
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
            :can-write="canUploadOrCreate"
            :band-id="currentBandId"
            :is-synced-folder="isInSyncedFolder"
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
              <div class="flex flex-col gap-3 w-full">
                <!-- Folder Breadcrumbs -->
                <div v-if="currentFolder" class="w-full">
                  <FolderBreadcrumbs
                    :current-folder="currentFolder"
                    @navigate="navigateToFolder"
                  />
                </div>

                <!-- Filters Row -->
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
              </div>
            </template>

            <template #end>
              <div class="flex items-center gap-2">
                <!-- Google Drive Button -->
                <Button
                  icon="pi pi-google"
                  severity="secondary"
                  outlined
                  @click="showDriveDialog = true"
                  v-tooltip.bottom="'Manage Google Drive connections'"
                  class="hidden sm:flex"
                  label="Google Drive"
                >
                </Button>

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
            v-if="canUploadOrCreate"
            :selected-count="selectedMediaIds.length"
            :selected-ids="selectedMediaIds"
            :folders="folders"
            :current-band-id="currentBandId"
            @select-all="selectAllMedia"
            @deselect-all="deselectAllMedia"
            @deleted="handleBulkDeleted"
          />

          <!-- Google Drive Sync Info Banner -->
          <div
            v-if="isInSyncedFolder"
            class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg flex items-start gap-3"
          >
            <i class="pi pi-google text-blue-500 text-xl flex-shrink-0 mt-0.5" />
            <div class="flex-1">
              <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-1">
                Google Drive Synced Folder (Read-Only)
              </h4>
              <p class="text-sm text-blue-800 dark:text-blue-200">
                This folder is automatically synced from Google Drive. Files are synced one-way from Google Drive to your media library.
                Upload and folder management are disabled in this directory.
              </p>
            </div>
          </div>

          <!-- Grid View -->
          <div v-if="viewMode === 'grid'" class="mt-6">
            <div
              v-if="media.data.length === 0 && (!subfolders || subfolders.length === 0)"
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
                v-if="canUploadOrCreate && currentFolder"
                label="Upload Files Here"
                icon="pi pi-upload"
                @click="openUploadToCurrentFolder"
              />
            </div>

            <div
              v-else
              class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
            >
              <!-- Folder Cards -->
              <div
                v-for="folder in subfolders"
                :key="'folder-' + folder.path"
                :class="[
                  'bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-xl transition-shadow duration-200 cursor-pointer border-2 relative',
                  dragOverFolderCard === folder.path ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-transparent hover:border-primary-500',
                  folder.is_drive_synced ? 'cursor-not-allowed' : ''
                ]"
                @click="navigateToFolder(folder.path)"
                @dragover.prevent="handleFolderCardDragOver($event, folder)"
                @dragleave="handleFolderCardDragLeave"
                @drop.prevent="handleFolderCardDrop($event, folder)"
              >
                <div class="p-6 flex flex-col items-center justify-center h-full">
                  <div class="relative">
                    <i class="pi pi-folder text-6xl text-yellow-500 mb-4"></i>
                    <i
                      v-if="folder.is_drive_synced"
                      class="pi pi-google text-xl text-blue-500 absolute -top-1 -right-1 bg-white dark:bg-gray-800 rounded-full p-1"
                      v-tooltip.top="`Synced from Google Drive: ${folder.drive_folder_name}`"
                    ></i>
                  </div>
                  <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2 text-center break-words">
                    {{ folder.name }}
                  </h3>
                  <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ folder.file_count }} {{ folder.file_count === 1 ? 'file' : 'files' }}
                  </p>
                </div>
              </div>

              <!-- File Cards -->
              <MediaCard
                v-for="item in media.data"
                :key="item.id"
                :media="item"
                :is-selected="selectedMediaIds.includes(item.id)"
                :can-write="canUploadOrCreate"
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
              :value="tableViewData"
              striped-rows
              :paginator="false"
              responsive-layout="scroll"
              :row-class="getRowClass"
              @row-click="handleRowClick"
            >
            <Column header="Preview" style="width: 100px">
              <template #body="slotProps">
                <div
                  v-if="slotProps.data.is_folder"
                  class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded flex items-center justify-center relative cursor-pointer"
                  @click.stop="navigateToFolder(slotProps.data.path)"
                >
                  <i class="pi pi-folder text-3xl text-yellow-500" />
                  <i
                    v-if="slotProps.data.is_drive_synced"
                    class="pi pi-google text-sm text-blue-500 absolute top-0 right-0 bg-white dark:bg-gray-800 rounded-full p-0.5"
                  />
                </div>
                <!-- Use thumbnail for images and videos -->
                <img
                  v-else-if="slotProps.data.media_type === 'image' || slotProps.data.media_type === 'video'"
                  :src="slotProps.data.thumbnail_url || slotProps.data.url"
                  class="w-16 h-16 object-cover rounded cursor-pointer"
                />
                <div
                  v-else
                  class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded flex items-center justify-center cursor-pointer"
                >
                  <i :class="getMediaIcon(slotProps.data.media_type)" class="text-2xl text-gray-400" />
                </div>
              </template>
            </Column>

            <Column field="title" header="Name" :sortable="true">
              <template #body="slotProps">
                <div
                  v-if="slotProps.data.is_folder"
                  class="flex items-center gap-2 cursor-pointer hover:text-primary-500"
                  @click.stop="navigateToFolder(slotProps.data.path)"
                >
                  <span class="font-semibold">{{ slotProps.data.name }}</span>
                  <i
                    v-if="slotProps.data.is_drive_synced"
                    class="pi pi-google text-xs text-blue-500"
                    v-tooltip.top="`Synced from Google Drive: ${slotProps.data.drive_folder_name}`"
                  />
                </div>
                <div v-else class="cursor-pointer">
                  <div class="font-semibold">{{ slotProps.data.title }}</div>
                  <div class="text-sm text-gray-500">{{ slotProps.data.filename }}</div>
                </div>
              </template>
            </Column>

            <Column field="media_type" header="Type" :sortable="true">
              <template #body="slotProps">
                <Tag :value="slotProps.data.media_type" />
              </template>
            </Column>

            <Column field="formatted_size" header="Size" :sortable="true">
              <template #body="slotProps">
                <span v-if="slotProps.data.is_folder">
                  {{ slotProps.data.file_count }} {{ slotProps.data.file_count === 1 ? 'item' : 'items' }}
                </span>
                <span v-else>
                  {{ slotProps.data.formatted_size }}
                </span>
              </template>
            </Column>

            <Column field="created_at" header="Uploaded" :sortable="true">
              <template #body="slotProps">
                <span v-if="!slotProps.data.is_folder">
                  {{ formatDate(slotProps.data.created_at) }}
                </span>
              </template>
            </Column>

            <Column header="Actions" style="width: 150px">
              <template #body="slotProps">
                <div v-if="!slotProps.data.is_folder" class="flex gap-2">
                  <Button
                    icon="pi pi-download"
                    severity="secondary"
                    size="small"
                    @click.stop="downloadMedia(slotProps.data.id)"
                  />
                  <Button
                    v-if="canUploadOrCreate"
                    icon="pi pi-trash"
                    severity="danger"
                    size="small"
                    @click.stop="deleteMedia(slotProps.data.id)"
                  />
                </div>
                <div v-else>
                  <Button
                    icon="pi pi-folder-open"
                    severity="secondary"
                    size="small"
                    @click.stop="navigateToFolder(slotProps.data.path)"
                    label="Open"
                  />
                </div>
              </template>
            </Column>
            </DataTable>

            <!-- Pagination below table -->
            <div v-if="media.last_page > 1" class="mt-4 flex justify-center">
              <Paginator
                :rows="media.per_page"
                :total-records="media.total"
                :first="(media.current_page - 1) * media.per_page"
                @page="onPage"
              />
            </div>
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
              :max-file-size="maxFileSize"
              :show-upload-button="false"
              :show-cancel-button="false"
              @select="onFileSelect"
            >
              <template #empty>
                <p>Drag and drop files here to upload.</p>
              </template>
            </FileUpload>
            <small class="text-gray-500">
              Max file size: 5GB. Files over 100MB will use chunked upload with progress tracking.
            </small>
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
          <div v-if="uploading" class="space-y-3">
            <ProgressBar :value="Math.round(uploadProgress)" />
            <div class="flex items-center justify-between">
              <p class="text-sm text-gray-600">
                <template v-if="isChunkedUpload">
                  Uploading chunk {{ currentChunk }} of {{ totalChunks }}... ({{ Math.round(uploadProgress) }}%)
                </template>
                <template v-else>
                  Uploading files... ({{ Math.round(uploadProgress) }}%)
                </template>
              </p>
              <Button
                v-if="isChunkedUpload"
                label="Cancel"
                icon="pi pi-times"
                size="small"
                severity="danger"
                text
                @click="cancelChunkedUpload"
              />
            </div>
          </div>

          <!-- Upload error with retry -->
          <div v-if="uploadError" class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
            <div class="flex items-start gap-3">
              <i class="pi pi-exclamation-triangle text-red-600 dark:text-red-400 mt-0.5"></i>
              <div class="flex-1">
                <p class="text-sm text-red-800 dark:text-red-200 font-medium">Upload Failed</p>
                <p class="text-sm text-red-700 dark:text-red-300 mt-1">{{ uploadError }}</p>
              </div>
              <Button
                v-if="isChunkedUpload && currentUploadService"
                label="Retry"
                icon="pi pi-refresh"
                size="small"
                severity="danger"
                outlined
                @click="retryChunkedUpload"
              />
            </div>
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

      <!-- Google Drive Dialog -->
      <Dialog
        v-model:visible="showDriveDialog"
        header="Google Drive Integration"
        :style="{ width: '60rem' }"
        :modal="true"
        :dismissable-mask="true"
      >
        <DriveConnectionsPanel
          :connections="driveConnections"
          :band-id="currentBandId"
          :can-write="$page.props.auth.user.navigation && $page.props.auth.user.navigation.Media && $page.props.auth.user.navigation.Media.write"
        />
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
import DriveConnectionsPanel from './Components/DriveConnectionsPanel.vue';
import FolderBreadcrumbs from './Components/FolderBreadcrumbs.vue';
import { ChunkedUploadService } from '@/services/ChunkedUploadService';
import { useUploadQueue } from '@/composables/useUploadQueue';
import { useMediaDragDrop } from '@/composables/useMediaDragDrop';
import { usePage } from '@inertiajs/vue3';
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
    MediaPreviewDialog,
    DriveConnectionsPanel,
    FolderBreadcrumbs
  },
  props: {
    media: Object,
    tags: Array,
    folders: Array,
    subfolders: Array,
    quota: Object,
    filters: Object,
    availableBands: Array,
    currentBandId: Number,
    bookings: Array,
    events: Array,
    driveConnections: Array,
  },
  data() {
    return {
      viewMode: localStorage.getItem('mediaViewMode') || 'grid',
      showUploadDialog: false,
      showCreateFolderDialog: false,
      showEditDialog: false,
      showMoveDialog: false,
      showPreviewDialog: false,
      showDriveDialog: false,
      sidebarVisible: false,
      selectedFiles: [],
      selectedMediaIds: [],
      editingMedia: null,
      movingMedia: null,
      previewingMedia: null,
      moveDestination: null,
      uploading: false,
      uploadProgress: 0,
      uploadError: null,
      currentChunk: 0,
      totalChunks: 0,
      currentUploadService: null,
      isChunkedUpload: false,
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
      isApplyingFilters: false,
      dragOverFolderCard: null,
      maxFileSize: 5368709120, // 5 GB (supported via chunked upload for files >100MB)
      isReloadPending: false, // Flag to prevent multiple simultaneous reloads
      reloadTimeoutId: null // Store timeout ID for cleanup
    };
  },
  setup(props) {
    const { addFiles, uploadQueue } = useUploadQueue();
    const page = usePage();

    // Store band_id globally for upload queue
    if (typeof window !== 'undefined') {
      window.bandId = null; // Will be set in mounted()
    }

    // Initialize drag and drop functionality
    const dragDropState = useMediaDragDrop({
      canUpload: () => {
        // Check permissions and that we're not in a synced folder
        const user = page.props.auth?.user
        const canWrite = user?.navigation?.Media?.write || false

        // Get current folder from filters
        const currentFolder = props.filters?.folder_path || null
        const isSyncedFolder = props.folders?.some(
          f => f.path === currentFolder && f.is_drive_synced
        ) || false

        return canWrite && !isSyncedFolder
      },
      bandId: props.currentBandId,
      folderPath: () => props.filters?.folder_path || null,
      onFilesDropped: (files, folderPath) => {
        console.log(`Dropped ${files.length} file(s) into folder: ${folderPath || 'root'}`)
      }
    });

    return {
      addFilesToQueue: addFiles,
      dragDropIsDragging: dragDropState.isDraggingFiles,
      uploadQueue
    };
  },
  computed: {
    isDraggingFiles() {
      return this.dragDropIsDragging;
    },
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
      // Filter out Google Drive synced folders (one-way sync only)
      const options = this.folders
        .filter(folder => !folder.is_drive_synced)
        .map(folder => ({
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
    },
    tableViewData() {
      // Combine folders and files for table view
      const folders = (this.subfolders || []).map(folder => ({
        ...folder,
        is_folder: true,
        media_type: 'folder',
        formatted_size: '—',
      }));

      return [...folders, ...this.media.data];
    },
    isInSyncedFolder() {
      // Check if current folder or any parent folder is synced from Google Drive
      if (!this.currentFolder) return false;

      return this.folders.some(folder => {
        // Check if this folder is synced
        if (!folder.is_drive_synced) return false;

        // Check if current path matches or is a child of this synced folder
        return this.currentFolder === folder.path ||
               this.currentFolder.startsWith(folder.path + '/');
      });
    },
    canUploadOrCreate() {
      // Can't upload or create in synced folders (one-way sync only)
      return !this.isInSyncedFolder &&
             this.$page.props.auth.user.navigation &&
             this.$page.props.auth.user.navigation.Media &&
             this.$page.props.auth.user.navigation.Media.write;
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
    },
    viewMode(newMode) {
      // Persist view mode preference to localStorage
      localStorage.setItem('mediaViewMode', newMode);
    }
  },
  created() {
    this.debouncedSearch = debounce(this.applyFilters, 500);
  },
  mounted() {
    console.log('[Media] Component mounted, adding event listener');
    // Set global band ID for upload queue
    window.bandId = this.currentBandId;

    // Remove any existing listener first (in case of improper cleanup)
    window.removeEventListener('upload-completed', this.handleUploadCompleted);

    // Listen for upload completion events
    window.addEventListener('upload-completed', this.handleUploadCompleted);
  },
  beforeUnmount() {
    console.log('[Media] Component before unmount, removing event listener');
    // Clean up event listeners
    window.removeEventListener('upload-completed', this.handleUploadCompleted);
    // Clear any pending reload timeout
    if (this.reloadTimeoutId) {
      clearTimeout(this.reloadTimeoutId);
      this.reloadTimeoutId = null;
    }
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

      // Add files to upload queue
      this.addFilesToQueue(
        this.selectedFiles,
        this.uploadForm.folder_path
      );

      // Close dialog and reset
      this.resetUploadDialog();

      // Show success message
      this.$toast?.add({
        severity: 'info',
        summary: 'Upload Started',
        detail: `${this.selectedFiles.length} file(s) added to upload queue`,
        life: 3000
      });
    },

    async uploadFilesStandard() {
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
            this.resetUploadDialog();
          },
          onError: (errors) => {
            this.uploadError = Object.values(errors).join(', ');
          },
          onFinish: () => {
            this.uploading = false;
          }
        });
      } catch (error) {
        console.error('Upload error:', error);
        this.uploadError = error.message || 'Upload failed';
        this.uploading = false;
      }
    },

    async uploadFilesChunked(largeFiles) {
      this.isChunkedUpload = true;

      try {
        // Upload each large file using chunked upload
        for (const file of largeFiles) {
          // Validate file size (5GB max)
          if (file.size > 5 * 1024 * 1024 * 1024) {
            this.uploadError = `File "${file.name}" exceeds 5GB limit`;
            this.uploading = false;
            this.isChunkedUpload = false;
            return;
          }

          this.currentUploadService = new ChunkedUploadService(file, {
            folderPath: this.uploadForm.folder_path || null,
            onProgress: (progress) => {
              this.uploadProgress = progress.percentage;
              this.currentChunk = progress.uploadedChunks;
              this.totalChunks = progress.totalChunks;
            },
            onError: (error) => {
              console.error('Chunked upload error:', error);
              this.uploadError = error.response?.data?.error || error.message || 'Upload failed';
            },
            onComplete: (media) => {
              console.log('Chunked upload complete:', media);
              // Refresh the page to show new media
              this.$inertia.reload({ only: ['media', 'quota'] });
            }
          });

          await this.currentUploadService.start();
        }

        // Success - reset dialog
        this.resetUploadDialog();

      } catch (error) {
        console.error('Chunked upload error:', error);
        this.uploadError = error.message || 'Upload failed';
      } finally {
        this.uploading = false;
        this.isChunkedUpload = false;
        this.currentUploadService = null;
      }
    },

    cancelChunkedUpload() {
      if (this.currentUploadService) {
        this.currentUploadService.abort();
        this.uploading = false;
        this.isChunkedUpload = false;
        this.uploadError = 'Upload cancelled';
      }
    },

    async retryChunkedUpload() {
      if (this.currentUploadService && this.currentUploadService.uploadId) {
        this.uploadError = null;
        this.uploading = true;

        try {
          await this.currentUploadService.resume();
          this.resetUploadDialog();
        } catch (error) {
          console.error('Retry failed:', error);
          this.uploadError = error.message || 'Retry failed';
          this.uploading = false;
        }
      }
    },

    resetUploadDialog() {
      this.showUploadDialog = false;
      this.selectedFiles = [];
      this.uploadProgress = 0;
      this.uploadError = null;
      this.currentChunk = 0;
      this.totalChunks = 0;
      this.isChunkedUpload = false;
      this.currentUploadService = null;
      this.uploadForm = {
        folder_path: null,
        booking_id: null,
        event_id: null,
      };
      if (this.$refs.fileUpload) {
        this.$refs.fileUpload.clear();
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
    clearFilters() {
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
    },
    handleUploadCompleted(event) {
      console.log('[Media] Upload completed event received', event.detail);

      // Clear any existing timeout
      if (this.reloadTimeoutId) {
        clearTimeout(this.reloadTimeoutId);
      }

      // Use a shorter debounce but check if uploads are truly complete
      this.reloadTimeoutId = setTimeout(() => {
        // Check if there are any pending or uploading items
        const hasActiveUploads = this.uploadQueue?.some(
          item => item.status === 'uploading' || item.status === 'pending'
        );

        if (hasActiveUploads) {
          console.log('[Media] Uploads still in progress, delaying reload');
          // Re-schedule check
          this.handleUploadCompleted(event);
          return;
        }

        console.log('[Media] All uploads complete, executing reload');
        this.isReloadPending = false;
        this.$inertia.reload({
          only: ['media', 'quota', 'subfolders'],
          preserveScroll: true,
          preserveState: true
        });
      }, 500); // Shorter timeout since we check queue status

      this.isReloadPending = true;
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
    },
    getRowClass(data) {
      // Add cursor-pointer class to file rows (not folders, handled separately)
      return !data.is_folder ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700' : '';
    },
    handleRowClick(event) {
      // Only handle click for files (folders have their own click handlers)
      if (!event.data.is_folder) {
        this.handlePreviewMedia(event.data);
      }
    },
    handleFolderCardDragOver(event, folder) {
      // Prevent drop on synced folders
      if (folder.is_drive_synced) {
        event.dataTransfer.dropEffect = 'none';
        this.dragOverFolderCard = null;
        return;
      }

      event.dataTransfer.dropEffect = 'move';
      this.dragOverFolderCard = folder.path;
    },
    handleFolderCardDragLeave() {
      this.dragOverFolderCard = null;
    },
    handleFolderCardDrop(event, folder) {
      this.dragOverFolderCard = null;

      // Prevent drop on synced folders
      if (folder.is_drive_synced) {
        this.$toast?.add({
          severity: 'error',
          summary: 'Cannot Move',
          detail: 'Cannot move files into Google Drive synced folders (one-way sync only).',
          life: 3000
        });
        return;
      }

      try {
        const data = JSON.parse(event.dataTransfer.getData('application/json'));

        if (data.id) {
          // Moving a file to this folder
          this.handleDropFile({
            mediaId: data.id,
            folderPath: folder.path
          });
        }
      } catch (error) {
        console.error('Failed to parse drop data:', error);
      }
    }
  }
};
</script>
