<template>
  <PrimeCard>
    <template #title>
      <div class="flex items-center justify-between">
        <span class="text-base">Folders</span>
        <Button
          v-if="canWrite"
          icon="pi pi-plus"
          size="small"
          text
          rounded
          @click="$emit('create-folder')"
          v-tooltip.right="'Create Folder'"
        />
      </div>
    </template>
    <template #content>
      <div class="space-y-1">
        <!-- Root folder -->
        <div
          :class="[
            'flex items-center justify-between px-3 py-2 rounded cursor-pointer transition-colors',
            'hover:bg-gray-100 dark:hover:bg-gray-700',
            !currentFolder ? 'bg-blue-50 dark:bg-blue-900 font-semibold' : '',
            dragOverFolder === null && dragType !== 'folder' ? 'ring-2 ring-blue-500 bg-blue-100 dark:bg-blue-800' : ''
          ]"
          @click="$emit('navigate', null)"
          @contextmenu.prevent="showFolderMenu($event, null)"
          @dragover.prevent="handleFileDragOver($event, null)"
          @dragleave="handleDragLeave"
          @drop.prevent="handleDrop($event, null)"
        >
          <div class="flex items-center gap-2">
            <i class="pi pi-home text-sm" />
            <span class="text-sm">All Files</span>
          </div>
        </div>

        <!-- Folder list with hierarchy -->
        <div
          v-for="(folder, index) in folders"
          :key="folder.path"
          class="relative"
        >
          <!-- Drop line indicator above -->
          <div
            v-if="dropLinePosition === 'before-' + folder.path"
            class="absolute -top-0.5 left-0 right-0 h-0.5 bg-blue-500 z-10"
            :style="{ marginLeft: (folder.depth * 16) + 'px' }"
          />
          
          <div
            :class="[
              'flex items-center justify-between px-3 py-2 rounded cursor-pointer transition-colors',
              'hover:bg-gray-100 dark:hover:bg-gray-700',
              currentFolder === folder.path ? 'bg-blue-50 dark:bg-blue-900 font-semibold' : '',
              dragOverFolder === folder.path && dragType !== 'folder' ? 'ring-2 ring-blue-500 bg-blue-100 dark:bg-blue-800' : '',
              draggedFolder === folder.path ? 'opacity-50' : '',
              dropLinePosition === 'into-' + folder.path ? 'ring-2 ring-blue-500 bg-blue-50 dark:bg-blue-900' : ''
            ]"
            :style="{ paddingLeft: (12 + folder.depth * 16) + 'px' }"
            :draggable="canWrite && !folder.is_system"
            @click="$emit('navigate', folder.path)"
            @contextmenu.prevent="showFolderMenu($event, folder)"
            @dragstart="handleFolderDragStart($event, folder)"
            @dragend="handleFolderDragEnd"
            @dragover.prevent="handleFolderDragOver($event, folder, index)"
            @dragleave="handleDragLeave"
            @drop.prevent="handleFolderDrop($event, folder)"
          >
            <div class="flex items-center gap-2 flex-1 min-w-0">
              <i :class="folder.has_children ? 'pi pi-folder-open' : 'pi pi-folder'" class="text-sm flex-shrink-0" />
              <span class="text-sm truncate">{{ folder.name }}</span>
              <i v-if="folder.is_system" class="pi pi-lock text-xs text-gray-400 flex-shrink-0" v-tooltip.right="'System folder'" />
            </div>
            <div class="flex items-center gap-2 ml-2 flex-shrink-0">
              <i v-if="dropLinePosition === 'into-' + folder.path" 
                 class="pi pi-folder-open text-sm text-blue-500" 
                 v-tooltip.right="'Drop to make subfolder'" />
              <span class="text-xs text-gray-500">{{ folder.file_count }}</span>
            </div>
          </div>
          
          <!-- Drop line indicator below (for last item) -->
          <div
            v-if="dropLinePosition === 'after-' + folder.path"
            class="absolute -bottom-0.5 left-0 right-0 h-0.5 bg-blue-500 z-10"
            :style="{ marginLeft: (folder.depth * 16) + 'px' }"
          />
        </div>
      </div>
    </template>
  </PrimeCard>

  <!-- Context Menu -->
  <ContextMenu ref="folderMenu" :model="folderMenuItems" />

  <!-- Rename Dialog -->
  <Dialog
    v-model:visible="showRenameDialog"
    :style="{ width: '450px' }"
    header="Rename Folder"
    :modal="true"
  >
    <div class="space-y-4">
      <div>
        <label class="block mb-2 font-medium text-sm">Current Path</label>
        <div class="px-3 py-2 bg-gray-50 dark:bg-gray-800 rounded text-sm">
          {{ selectedFolder?.path }}
        </div>
      </div>
      <div>
        <label class="block mb-2 font-medium text-sm">New Name</label>
        <InputText
          v-model="newFolderName"
          class="w-full"
          placeholder="Enter new folder name"
          @keyup.enter="renameFolder"
        />
        <small class="text-gray-500">This will rename the folder and all subfolders</small>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Cancel"
          severity="secondary"
          @click="showRenameDialog = false"
        />
        <Button
          label="Rename"
          :disabled="!newFolderName || newFolderName === selectedFolder?.name"
          @click="renameFolder"
        />
      </div>
    </template>
  </Dialog>

  <!-- Create Subfolder Dialog -->
  <Dialog
    v-model:visible="showCreateSubfolderDialog"
    :style="{ width: '450px' }"
    header="Create Subfolder"
    :modal="true"
  >
    <div class="space-y-4">
      <div>
        <label class="block mb-2 font-medium text-sm">Parent Folder</label>
        <div class="px-3 py-2 bg-gray-50 dark:bg-gray-800 rounded text-sm">
          {{ selectedFolder?.path || '/' }}
        </div>
      </div>
      <div>
        <label class="block mb-2 font-medium text-sm">Subfolder Name</label>
        <InputText
          v-model="subfolderName"
          class="w-full"
          placeholder="Enter subfolder name"
          @keyup.enter="createSubfolder"
        />
        <small class="text-gray-500">
          Full path will be: {{ selectedFolder?.path ? selectedFolder.path + '/' : '' }}{{ subfolderName || '...' }}
        </small>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Cancel"
          severity="secondary"
          @click="showCreateSubfolderDialog = false"
        />
        <Button
          label="Create"
          icon="pi pi-folder-plus"
          :disabled="!subfolderName"
          @click="createSubfolder"
        />
      </div>
    </template>
  </Dialog>
</template>

<script>
import PrimeCard from 'primevue/card';
import ContextMenu from 'primevue/contextmenu';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

export default {
  name: 'FolderSidebar',
  components: {
    PrimeCard,
    ContextMenu,
    Dialog,
    InputText,
    Button
  },
  props: {
    folders: {
      type: Array,
      required: true
    },
    currentFolder: {
      type: String,
      default: null
    },
    canWrite: {
      type: Boolean,
      default: false
    },
    bandId: {
      type: Number,
      required: true
    }
  },
  emits: ['navigate', 'create-folder', 'folder-renamed', 'folder-deleted', 'drop-file', 'folder-created'],
  data() {
    return {
      selectedFolder: null,
      showRenameDialog: false,
      showCreateSubfolderDialog: false,
      newFolderName: '',
      subfolderName: '',
      folderMenuItems: [],
      dragOverFolder: false,
      draggedFolder: null,
      dragType: null, // 'file' or 'folder'
      dropLinePosition: null, // 'before-{path}' or 'after-{path}' or 'into-{path}'
      dropTarget: null
    };
  },
  methods: {
    showFolderMenu(event, folder) {
      if (!this.canWrite) return;

      this.selectedFolder = folder;

      if (folder) {
        // Folder context menu
        const menuItems = [
          {
            label: 'Create Subfolder',
            icon: 'pi pi-folder-plus',
            command: () => this.openCreateSubfolderDialog()
          }
        ];

        // Only allow rename/delete for non-system folders
        if (!folder.is_system) {
          menuItems.push(
            {
              separator: true
            },
            {
              label: 'Rename',
              icon: 'pi pi-pencil',
              command: () => this.openRenameDialog()
            },
            {
              label: 'Delete',
              icon: 'pi pi-trash',
              command: () => this.deleteFolder()
            }
          );
        }

        this.folderMenuItems = menuItems;
      }

      this.$refs.folderMenu.show(event);
    },
    openRenameDialog() {
      this.newFolderName = this.selectedFolder.name;
      this.showRenameDialog = true;
    },
    openCreateSubfolderDialog() {
      this.subfolderName = '';
      this.showCreateSubfolderDialog = true;
    },
    async createSubfolder() {
      if (!this.subfolderName) return;

      const parentPath = this.selectedFolder?.path || '';
      const newFolderPath = parentPath ? `${parentPath}/${this.subfolderName}` : this.subfolderName;

      // Create the folder on the backend
      try {
        await this.$inertia.post(
          this.route('media.folders.create'),
          {
            band_id: this.bandId,
            folder_path: newFolderPath
          },
          {
            preserveScroll: true,
            onSuccess: () => {
              this.showCreateSubfolderDialog = false;
              this.subfolderName = '';
              this.$emit('folder-created', newFolderPath);
            }
          }
        );
      } catch (error) {
        console.error('Failed to create subfolder:', error);
      }
    },
    async renameFolder() {
      if (!this.newFolderName || !this.selectedFolder) return;

      const oldPath = this.selectedFolder.path;
      const parentPath = oldPath.substring(0, oldPath.lastIndexOf('/'));
      const newPath = parentPath ? `${parentPath}/${this.newFolderName}` : this.newFolderName;

      try {
        this.$inertia.post(
          this.route('media.folders.rename'),
          {
            band_id: this.bandId,
            old_path: oldPath,
            new_path: newPath
          },
          {
            preserveScroll: true,
            onSuccess: () => {
              this.showRenameDialog = false;
              this.newFolderName = '';
              this.$emit('folder-renamed', { oldPath, newPath });
            }
          }
        );
      } catch (error) {
        console.error('Failed to rename folder:', error);
      }
    },
    async deleteFolder() {
      if (!this.selectedFolder) return;

      if (!confirm(`Are you sure you want to delete "${this.selectedFolder.path}"? Files will be moved to root.`)) {
        return;
      }

      try {
        await this.$inertia.delete(
          this.route('media.folders.delete'),
          {
            data: {
              band_id: this.bandId,
              folder_path: this.selectedFolder.path
            },
            preserveScroll: true,
            onSuccess: () => {
              this.$emit('folder-deleted', this.selectedFolder.path);
            }
          }
        );
      } catch (error) {
        console.error('Failed to delete folder:', error);
      }
    },
    handleFolderDragStart(event, folder) {
      if (folder.is_system) {
        event.preventDefault();
        return;
      }
      
      this.draggedFolder = folder.path;
      this.dragType = 'folder';
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('application/json', JSON.stringify({
        type: 'folder',
        path: folder.path
      }));
    },
    handleFolderDragEnd() {
      this.draggedFolder = null;
      this.dragType = null;
      this.dropLinePosition = null;
      this.dropTarget = null;
    },
    handleFileDragOver(event, folderPath) {
      // For file dragging (non-folder), use simple folder highlighting
      if (this.dragType !== 'folder') {
        event.dataTransfer.dropEffect = 'move';
        this.dragOverFolder = folderPath;
      }
    },
    handleFolderDragOver(event, folder, index) {
      if (this.dragType !== 'folder') {
        // File drag - just highlight the folder
        event.dataTransfer.dropEffect = 'move';
        this.dragOverFolder = folder.path;
        return;
      }

      // Folder drag - determine drop position
      const rect = event.currentTarget.getBoundingClientRect();
      const mouseY = event.clientY - rect.top;
      const height = rect.height;
      
      // Can't drop into itself or its descendants
      if (this.draggedFolder === folder.path || 
          (folder.path && folder.path.startsWith(this.draggedFolder + '/'))) {
        event.dataTransfer.dropEffect = 'none';
        this.dropLinePosition = null;
        this.dropTarget = null;
        return;
      }
      
      // Determine if we're in top third, middle third, or bottom third
      const third = height / 3;
      
      if (mouseY < third) {
        // Top third - show line above, drop as sibling before
        this.dropLinePosition = 'before-' + folder.path;
        this.dropTarget = { type: 'before', folder: folder };
      } else if (mouseY > height - third) {
        // Bottom third - show line below, drop as sibling after
        this.dropLinePosition = 'after-' + folder.path;
        this.dropTarget = { type: 'after', folder: folder };
      } else {
        // Middle third - drop into folder (if not system)
        if (folder.is_system) {
          event.dataTransfer.dropEffect = 'none';
          this.dropLinePosition = null;
          this.dropTarget = null;
          return;
        }
        this.dropLinePosition = 'into-' + folder.path;
        this.dropTarget = { type: 'into', folder: folder };
      }
      
      event.dataTransfer.dropEffect = 'move';
    },
    handleDragLeave() {
      this.dragOverFolder = false;
    },
    handleDrop(event, folderPath) {
      // Handle file drops (old behavior)
      this.dragOverFolder = false;
      this.dropLinePosition = null;
      this.dropTarget = null;

      try {
        const data = JSON.parse(event.dataTransfer.getData('application/json'));
        
        if (data.type !== 'folder') {
          // Moving a file
          this.$emit('drop-file', {
            mediaId: data.id,
            folderPath: folderPath
          });
        }
      } catch (error) {
        console.error('Failed to parse drop data:', error);
      }
    },
    handleFolderDrop(event, folder) {
      const target = this.dropTarget;
      this.dragOverFolder = false;
      this.dropLinePosition = null;
      this.dropTarget = null;

      try {
        const data = JSON.parse(event.dataTransfer.getData('application/json'));
        
        if (data.type === 'folder') {
          // Moving a folder based on drop target
          if (!target) return;
          
          if (target.type === 'into') {
            // Drop into the folder
            this.moveFolder(data.path, target.folder.path);
          } else if (target.type === 'before' || target.type === 'after') {
            // Drop as sibling - get parent path of target folder
            const targetPath = target.folder.path;
            const parentPath = targetPath.includes('/') 
              ? targetPath.substring(0, targetPath.lastIndexOf('/'))
              : null;
            this.moveFolder(data.path, parentPath);
          }
        } else {
          // Moving a file
          this.$emit('drop-file', {
            mediaId: data.id,
            folderPath: folder.path
          });
        }
      } catch (error) {
        console.error('Failed to parse drop data:', error);
      }
    },
    async moveFolder(sourcePath, targetPath) {
      // Calculate the new path
      const folderName = sourcePath.substring(sourcePath.lastIndexOf('/') + 1);
      const newPath = targetPath ? `${targetPath}/${folderName}` : folderName;

      // Check if a folder with this name already exists at the target
      const exists = this.folders.some(f => f.path === newPath);
      if (exists) {
        alert(`A folder named "${folderName}" already exists in the target location.`);
        return;
      }

      try {
        await this.$inertia.post(
          this.route('media.folders.rename'),
          {
            band_id: this.bandId,
            old_path: sourcePath,
            new_path: newPath
          },
          {
            preserveScroll: true,
            onSuccess: () => {
              this.$emit('folder-renamed', { oldPath: sourcePath, newPath });
            }
          }
        );
      } catch (error) {
        console.error('Failed to move folder:', error);
      }
    }
  }
};
</script>
