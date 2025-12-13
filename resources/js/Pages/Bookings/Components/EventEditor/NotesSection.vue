<template>
  <div class="flex flex-col h-full">
    <!-- Notes textarea - prominent and spacious, grows to fill space -->
    <div class="flex-1 flex flex-col mb-4">
      <textarea
        v-model="modelValue.notes"
        class="w-full h-full p-4 text-base border-2 border-gray-300 dark:border-gray-600 rounded-lg 
               focus:ring-2 focus:ring-blue-500 focus:border-blue-500 
               dark:bg-slate-800 dark:text-gray-50 resize-none"
        placeholder="Add your notes here..."
      />
    </div>

    <!-- Attachments drawer - slides up from bottom -->
    <div class="border-t border-gray-200 dark:border-gray-700">
      <button
        type="button"
        class="flex items-center justify-between w-full py-3 px-2 text-left hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors"
        @click="showAttachments = !showAttachments"
      >
        <div class="flex items-center gap-2">
          <i class="pi pi-paperclip text-gray-600 dark:text-gray-400" />
          <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Attachments ({{ existingAttachments.length }})
          </span>
        </div>
        <i 
          :class="showAttachments ? 'pi pi-chevron-down' : 'pi pi-chevron-up'"
          class="text-gray-500 dark:text-gray-400 text-sm"
        />
      </button>

      <!-- Slide-up drawer content -->
      <transition
        enter-active-class="transition-all duration-300 ease-out"
        leave-active-class="transition-all duration-300 ease-in"
        enter-from-class="max-h-0 opacity-0"
        enter-to-class="max-h-96 opacity-100"
        leave-from-class="max-h-96 opacity-100"
        leave-to-class="max-h-0 opacity-0"
      >
        <div
          v-show="showAttachments"
          class="overflow-hidden"
        >
          <div class="px-2 pb-3">
            <!-- File picker -->
            <div class="mb-3">
              <input
                ref="fileInput"
                type="file"
                multiple
                class="hidden"
                @change="handleFileSelect"
              >
              <button
                type="button"
                class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 
                   rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 
                   bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600"
                @click="$refs.fileInput.click()"
              >
                <i class="pi pi-plus mr-2" />
                Add Files
              </button>
            </div>

            <!-- Attachment list -->
            <div
              v-if="existingAttachments.length > 0 || selectedFiles.length > 0"
              class="max-h-64 overflow-y-auto space-y-2"
            >
              <!-- Selected files to upload (not yet saved) -->
              <div
                v-for="(file, index) in selectedFiles"
                :key="'new-' + index"
                class="flex items-center justify-between p-2 bg-blue-50 dark:bg-blue-900/20 
                   border border-blue-200 dark:border-blue-800 rounded"
              >
                <div class="flex items-center space-x-2 min-w-0 flex-1">
                  <i
                    :class="getFileIcon(file)"
                    class="text-blue-600 dark:text-blue-400 flex-shrink-0"
                  />
                  <span class="text-sm truncate">{{ file.name }}</span>
                </div>
                <button
                  type="button"
                  class="ml-2 text-red-600 hover:text-red-800 dark:text-red-400"
                  @click="removeSelectedFile(index)"
                >
                  <i class="pi pi-times text-xs" />
                </button>
              </div>

              <!-- Existing attachments - compact list view -->
              <div
                v-for="attachment in existingAttachments"
                :key="attachment.id"
                class="flex items-center justify-between p-2 bg-gray-50 dark:bg-slate-700 
                   border border-gray-200 dark:border-gray-600 rounded group"
              >
                <div class="flex items-center space-x-2 min-w-0 flex-1">
                  <i
                    :class="getAttachmentIcon(attachment)"
                    class="text-gray-600 dark:text-gray-400 flex-shrink-0"
                  />
                  <span class="text-sm truncate">{{ attachment.filename }}</span>
                  <span class="text-xs text-gray-500 dark:text-gray-400">
                    ({{ attachment.formatted_size }})
                  </span>
                </div>
                <div class="flex items-center gap-1 ml-2 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button
                    type="button"
                    class="p-1 text-gray-600 hover:text-gray-800 dark:text-gray-400"
                    @click="previewAttachment(attachment)"
                  >
                    <i class="pi pi-eye text-xs" />
                  </button>
                  <a
                    :href="getDownloadUrl(attachment.id)"
                    class="p-1 text-blue-600 hover:text-blue-800 dark:text-blue-400"
                    download
                  >
                    <i class="pi pi-download text-xs" />
                  </a>
                  <button
                    type="button"
                    class="p-1 text-red-600 hover:text-red-800 dark:text-red-400"
                    @click="deleteAttachment(attachment.id)"
                  >
                    <i class="pi pi-trash text-xs" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Empty state -->
            <div
              v-if="existingAttachments.length === 0 && selectedFiles.length === 0"
              class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm"
            >
              No attachments yet. Click "Add Files" to upload.
            </div>
          </div>
        </div>
      </transition>
    </div>

    <!-- Preview Modal -->
    <Dialog
      v-model:visible="showPreviewModal"
      modal
      :dismissable-mask="true"
      class="w-full max-w-4xl"
      :style="{ width: '90vw', maxHeight: '90vh' }"
      :content-style="{ overflow: 'hidden', display: 'flex', flexDirection: 'column' }"
    >
      <template #header>
        <div class="flex items-center justify-between w-full">
          <h3 class="text-lg font-semibold truncate">
            {{ currentPreview?.filename }}
          </h3>
        </div>
      </template>
      <div
        v-if="currentPreview"
        class="flex justify-center items-center overflow-auto"
        style="max-height: calc(90vh - 120px);"
      >
        <img
          v-if="currentPreview.mime_type.startsWith('image/')"
          :src="getShowUrl(currentPreview.id)"
          :alt="currentPreview.filename"
          class="max-w-full max-h-[70vh] object-contain"
        >
        <iframe
          v-else-if="currentPreview.mime_type === 'application/pdf'"
          :src="getShowUrl(currentPreview.id)"
          class="w-full h-[70vh]"
        />
        <div
          v-else
          class="text-center text-gray-500 dark:text-gray-400 p-8"
        >
          <i class="pi pi-file text-6xl mb-4" />
          <p>Preview not available for this file type</p>
          <a
            :href="getDownloadUrl(currentPreview.id)"
            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 mt-4 inline-block"
            download
          >
            Download to view
          </a>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Dialog from 'primevue/dialog';

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['attachmentsChanged']);

const fileInput = ref(null);
const selectedFiles = ref([]);
const existingAttachments = ref([]);
const showPreviewModal = ref(false);
const currentPreview = ref(null);
const showAttachments = ref(false);

// Load existing attachments on mount
onMounted(async () => {
    if (props.modelValue.id) {
        await loadAttachments();
    }
});

const loadAttachments = async () => {
    try {
        const url = window.route('events.attachments.index', props.modelValue.id);
        const response = await axios.get(url);
        existingAttachments.value = response.data.attachments;
    } catch (error) {
        console.error('Failed to load attachments:', error);
        existingAttachments.value = [];
    }
};

const handleFileSelect = async (event) => {
    const files = Array.from(event.target.files);
    
    // Reset input so same file can be selected again if needed
    event.target.value = '';
    
    // If event doesn't have an ID yet, just add to pending list
    if (!props.modelValue.id) {
        selectedFiles.value.push(...files);
        return;
    }
    
    // Otherwise, upload immediately
    await uploadFiles(files);
};

const uploadFiles = async (files) => {
    if (!props.modelValue.id || files.length === 0) return;
    
    const formData = new FormData();
    files.forEach(file => {
        formData.append('files[]', file);
    });
    
    try {
        const url = window.route('events.attachments.upload', props.modelValue.id);
        await axios.post(url, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        
        // Reload attachments to show the newly uploaded files
        await loadAttachments();
        emit('attachmentsChanged');
    } catch (error) {
        console.error('Failed to upload attachments:', error);
        alert('Failed to upload files. Please try again.');
    }
};

const removeSelectedFile = (index) => {
    selectedFiles.value.splice(index, 1);
};

const deleteAttachment = async (attachmentId) => {
    if (!confirm('Are you sure you want to delete this attachment?')) {
        return;
    }

    try {
        const url = window.route('events.attachments.destroy', attachmentId);
        await axios.delete(url);
        await loadAttachments();
        emit('attachmentsChanged');
    } catch (error) {
        console.error('Failed to delete attachment:', error);
        alert('Failed to delete attachment. Please try again.');
    }
};

const getFileIcon = (file) => {
    const type = file.type;
    if (type.startsWith('image/')) return 'pi pi-image';
    if (type === 'application/pdf') return 'pi pi-file-pdf';
    if (type.startsWith('video/')) return 'pi pi-video';
    if (type.startsWith('audio/')) return 'pi pi-volume-up';
    return 'pi pi-file';
};

const getAttachmentIcon = (attachment) => {
    const type = attachment.mime_type;
    if (type.startsWith('image/')) return 'pi pi-image';
    if (type === 'application/pdf') return 'pi pi-file-pdf';
    if (type.startsWith('video/')) return 'pi pi-video';
    if (type.startsWith('audio/')) return 'pi pi-volume-up';
    return 'pi pi-file';
};

const formatFileSize = (bytes) => {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;
    
    while (size > 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }
    
    return `${size.toFixed(2)} ${units[unitIndex]}`;
}
const getDownloadUrl = (attachmentId) => {
    return window.route('events.attachments.download', attachmentId);
};

const getShowUrl = (attachmentId) => {
    return window.route('events.attachments.show', attachmentId);
};

const previewAttachment = (attachment) => {
    currentPreview.value = attachment;
    showPreviewModal.value = true;
};

// Expose method to get selected files for upload
const getSelectedFiles = () => selectedFiles.value;
const clearSelectedFiles = () => {
    selectedFiles.value = [];
};

defineExpose({
    getSelectedFiles,
    clearSelectedFiles,
    loadAttachments,
});
</script>
