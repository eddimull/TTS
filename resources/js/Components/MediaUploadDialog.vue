<template>
  <Dialog
    :visible="visible"
    :style="{ width: '700px' }"
    header="Upload Media Files"
    :modal="true"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="space-y-4">
      <!-- File Upload Component -->
      <div>
        <label class="block mb-2 font-medium">Select Files</label>
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
          Max file size: {{ Math.floor(maxFileSize / 1024 / 1024) }}MB
          (files over 100MB will use chunked upload)
        </small>
      </div>

      <!-- Folder Path -->
      <div v-if="!hideFolder">
        <label class="block mb-2 font-medium">Folder (Optional)</label>
        <Dropdown
          v-model="form.folder_path"
          :options="folderOptions"
          option-label="label"
          option-value="value"
          placeholder="Select or type folder path"
          editable
          show-clear
          class="w-full"
        />
        <small class="text-gray-500">Leave empty to upload to root folder</small>
      </div>

      <!-- Associate with Booking/Event -->
      <div v-if="!hideAssociations" class="grid grid-cols-2 gap-4">
        <div>
          <label class="block mb-2 font-medium">Link to Booking (Optional)</label>
          <Dropdown
            v-model="form.booking_id"
            :options="bookings"
            option-label="name"
            option-value="id"
            placeholder="Select booking"
            show-clear
            class="w-full"
            :disabled="!!defaultBookingId"
          >
            <template #value="slotProps">
              <div v-if="slotProps.value">
                {{ bookings.find(b => b.id === slotProps.value)?.name }}
              </div>
            </template>
          </Dropdown>
        </div>

        <div>
          <label class="block mb-2 font-medium">Link to Event (Optional)</label>
          <Dropdown
            v-model="form.event_id"
            :options="events"
            option-label="name"
            option-value="id"
            placeholder="Select event"
            show-clear
            class="w-full"
            :disabled="!!defaultEventId"
          >
            <template #value="slotProps">
              <div v-if="slotProps.value">
                {{ events.find(e => e.id === slotProps.value)?.name }}
              </div>
            </template>
          </Dropdown>
        </div>
      </div>

      <!-- Upload Progress -->
      <div v-if="uploading" class="space-y-2">
        <div class="flex justify-between text-sm">
          <span>Uploading...</span>
          <span>{{ uploadProgress }}%</span>
        </div>
        <ProgressBar :value="uploadProgress" />
        <div v-if="isChunkedUpload" class="text-xs text-gray-500">
          Chunk {{ currentChunk }} of {{ totalChunks }}
        </div>
      </div>

      <!-- Upload Error -->
      <Message v-if="uploadError" severity="error" :closable="false">
        {{ uploadError }}
      </Message>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Cancel"
          icon="pi pi-times"
          severity="secondary"
          :disabled="uploading"
          @click="handleCancel"
        />
        <Button
          label="Upload"
          icon="pi pi-upload"
          :disabled="!hasFiles || uploading"
          :loading="uploading"
          @click="handleUpload"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import FileUpload from 'primevue/fileupload'
import Dropdown from 'primevue/dropdown'
import Button from 'primevue/button'
import ProgressBar from 'primevue/progressbar'
import Message from 'primevue/message'
import ChunkedUploadService from '@/services/ChunkedUploadService'

const props = defineProps({
  visible: Boolean,
  bandId: {
    type: Number,
    required: true
  },
  folders: {
    type: Array,
    default: () => []
  },
  bookings: {
    type: Array,
    default: () => []
  },
  events: {
    type: Array,
    default: () => []
  },
  defaultFolder: {
    type: String,
    default: null
  },
  defaultBookingId: {
    type: Number,
    default: null
  },
  defaultEventId: {
    type: Number,
    default: null
  },
  hideFolder: {
    type: Boolean,
    default: false
  },
  hideAssociations: {
    type: Boolean,
    default: false
  },
  maxFileSize: {
    type: Number,
    default: 524288000 // 500MB
  }
})

const emit = defineEmits(['update:visible', 'upload-complete', 'upload-error'])

const fileUpload = ref(null)
const selectedFiles = ref([])
const uploading = ref(false)
const uploadProgress = ref(0)
const uploadError = ref(null)
const isChunkedUpload = ref(false)
const currentChunk = ref(0)
const totalChunks = ref(0)
const currentUploadService = ref(null)

const form = ref({
  folder_path: props.defaultFolder,
  booking_id: props.defaultBookingId,
  event_id: props.defaultEventId
})

const folderOptions = computed(() => {
  return props.folders.map(f => ({
    label: f.path,
    value: f.path
  }))
})

const hasFiles = computed(() => selectedFiles.value.length > 0)

// Watch for prop changes
watch(() => props.defaultFolder, (newVal) => {
  form.value.folder_path = newVal
})

watch(() => props.defaultBookingId, (newVal) => {
  form.value.booking_id = newVal
})

watch(() => props.defaultEventId, (newVal) => {
  form.value.event_id = newVal
})

function onFileSelect(event) {
  selectedFiles.value = event.files
  uploadError.value = null
}

async function handleUpload() {
  if (!hasFiles.value) return

  uploading.value = true
  uploadError.value = null
  uploadProgress.value = 0

  const CHUNK_THRESHOLD = 100 * 1024 * 1024 // 100MB

  try {
    // Check if any file exceeds chunk threshold
    const needsChunkedUpload = selectedFiles.value.some(file => file.size > CHUNK_THRESHOLD)

    if (needsChunkedUpload && selectedFiles.value.length === 1) {
      // Use chunked upload for single large file
      await uploadChunked(selectedFiles.value[0])
    } else {
      // Use standard upload
      await uploadStandard()
    }

    emit('upload-complete')
    resetDialog()
  } catch (error) {
    uploadError.value = error.message || 'Upload failed. Please try again.'
    emit('upload-error', error)
  } finally {
    uploading.value = false
  }
}

async function uploadStandard() {
  const formData = new FormData()
  formData.append('band_id', props.bandId)

  if (form.value.folder_path) {
    formData.append('folder_path', form.value.folder_path)
  }

  if (form.value.booking_id) {
    formData.append('booking_id', form.value.booking_id)
  }

  if (form.value.event_id) {
    formData.append('event_id', form.value.event_id)
  }

  selectedFiles.value.forEach((file, index) => {
    formData.append(`files[${index}]`, file)
  })

  const xhr = new XMLHttpRequest()

  xhr.upload.addEventListener('progress', (e) => {
    if (e.lengthComputable) {
      uploadProgress.value = Math.round((e.loaded / e.total) * 100)
    }
  })

  return new Promise((resolve, reject) => {
    xhr.addEventListener('load', () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve()
      } else {
        reject(new Error(`Upload failed with status ${xhr.status}`))
      }
    })

    xhr.addEventListener('error', () => {
      reject(new Error('Network error during upload'))
    })

    xhr.open('POST', '/media/upload')
    xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content)
    xhr.send(formData)
  })
}

async function uploadChunked(file) {
  isChunkedUpload.value = true

  const service = new ChunkedUploadService(file, {
    folderPath: form.value.folder_path,
    onProgress: (progress) => {
      uploadProgress.value = Math.round(progress.percentage)
      currentChunk.value = progress.currentChunk
      totalChunks.value = progress.totalChunks
    },
    onError: (error) => {
      throw error
    },
    onComplete: () => {
      isChunkedUpload.value = false
    }
  })

  currentUploadService.value = service

  await service.upload(props.bandId, form.value.booking_id, form.value.event_id)
}

function handleCancel() {
  if (currentUploadService.value) {
    currentUploadService.value.abort()
  }
  resetDialog()
  emit('update:visible', false)
}

function resetDialog() {
  selectedFiles.value = []
  uploadProgress.value = 0
  uploadError.value = null
  isChunkedUpload.value = false
  currentChunk.value = 0
  totalChunks.value = 0
  currentUploadService.value = null

  if (fileUpload.value) {
    fileUpload.value.clear()
  }

  // Reset form to defaults
  form.value = {
    folder_path: props.defaultFolder,
    booking_id: props.defaultBookingId,
    event_id: props.defaultEventId
  }
}
</script>
