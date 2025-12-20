<template>
  <Toolbar v-if="selectedCount > 0" class="mb-4 bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800">
    <template #start>
      <div class="flex items-center gap-3">
        <span class="text-sm font-semibold">
          {{ selectedCount }} {{ selectedCount === 1 ? 'file' : 'files' }} selected
        </span>
        <Button
          label="Select All"
          size="small"
          severity="secondary"
          @click="$emit('select-all')"
        />
        <Button
          label="Deselect All"
          size="small"
          severity="secondary"
          @click="$emit('deselect-all')"
        />
      </div>
    </template>

    <template #end>
      <div class="flex items-center gap-2">
        <!-- Move to Folder -->
        <Dropdown
          v-model="targetFolder"
          :options="folderOptions"
          option-label="label"
          option-value="value"
          placeholder="Move to folder..."
          class="w-64"
          show-clear
          @change="handleMoveToFolder"
        />

        <!-- Delete Selected -->
        <Button
          icon="pi pi-trash"
          label="Delete"
          severity="danger"
          size="small"
          @click="confirmDelete"
        />
      </div>
    </template>
  </Toolbar>

  <!-- Delete Confirmation Dialog -->
  <Dialog
    v-model:visible="showDeleteDialog"
    modal
    header="Confirm Deletion"
    :style="{ width: '30rem' }"
  >
    <div class="flex items-start gap-3">
      <i class="pi pi-exclamation-triangle text-3xl text-orange-500" />
      <div>
        <p class="mb-2">
          Are you sure you want to delete {{ selectedCount }} {{ selectedCount === 1 ? 'file' : 'files' }}?
        </p>
        <p class="text-sm text-gray-600 dark:text-gray-400">
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
          icon="pi pi-trash"
          severity="danger"
          @click="handleDelete"
        />
      </div>
    </template>
  </Dialog>
</template>

<script>
import Toolbar from 'primevue/toolbar';
import Button from 'primevue/button';
import Dropdown from 'primevue/dropdown';
import Dialog from 'primevue/dialog';
import { router } from '@inertiajs/vue3';

export default {
  name: 'BulkOperationsToolbar',
  components: {
    Toolbar,
    Button,
    Dropdown,
    Dialog
  },
  props: {
    selectedCount: {
      type: Number,
      required: true
    },
    selectedIds: {
      type: Array,
      required: true
    },
    folders: {
      type: Array,
      default: () => []
    },
    currentBandId: {
      type: Number,
      required: true
    }
  },
  emits: ['select-all', 'deselect-all', 'deleted'],
  data() {
    return {
      targetFolder: null,
      showDeleteDialog: false
    };
  },
  computed: {
    folderOptions() {
      const options = [
        { label: 'Move to Root (No folder)', value: null }
      ];

      this.folders.forEach(folder => {
        const indent = '\u00A0\u00A0'.repeat(folder.depth);
        options.push({
          label: indent + folder.name + ` (${folder.file_count})`,
          value: folder.path
        });
      });

      return options;
    }
  },
  methods: {
    handleMoveToFolder() {
      if (this.selectedIds.length === 0) return;

      router.post(route('media.bulk.move'), {
        band_id: this.currentBandId,
        media_ids: this.selectedIds,
        folder_path: this.targetFolder
      }, {
        preserveScroll: true,
        onSuccess: () => {
          this.targetFolder = null;
        }
      });
    },
    confirmDelete() {
      this.showDeleteDialog = true;
    },
    handleDelete() {
      if (this.selectedIds.length === 0) return;

      const deletePromises = this.selectedIds.map(id => {
        return new Promise((resolve) => {
          router.delete(route('media.destroy', id), {
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () => resolve()
          });
        });
      });

      Promise.all(deletePromises).then(() => {
        this.showDeleteDialog = false;
        this.$emit('deleted');
      });
    }
  }
};
</script>
