<template>
  <div>
    <div
      v-if="existingContract"
      class="mb-6"
    >
      <div class="p-4 border rounded-lg bg-gray-50 mb-4">
        <h2 class="text-xl font-semibold mb-2">
          Existing Contract
        </h2>
        <p class="mb-2">
          A contract is already uploaded for this booking.
        </p>
        <div class="flex space-x-4">
          <button
            v-if="!showPreview"
            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
            @click="viewExistingContract"
          >
            View Contract
          </button>
          <button
            v-if="!showUploadForm"
            class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600"
            @click="initiateReplace"
          >
            Replace Contract
          </button>
          <button
            v-if="showUploadForm"
            class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
            @click="cancelReplace"
          >
            Cancel Replace
          </button>
        </div>
      </div>
      
      <div
        v-if="showPreview"
        class="mb-4 relative"
      >
        <div class="flex justify-between items-center mb-2">
          <h2 class="text-xl font-semibold">
            Current Contract Preview
          </h2>
          <button
            class="px-2 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300"
            @click="closePreview"
          >
            Close Preview
          </button>
        </div>
        <iframe
          :src="existingContractUrl"
          width="100%"
          height="600px"
          class="border"
        />
      </div>
    </div>

    <div
      v-if="showUploadForm"
      class="mb-4"
    >
      <h2 class="text-xl font-semibold mb-2">
        {{ existingContract ? 'Upload New Contract' : 'Upload Contract' }}
      </h2>
      <input
        type="file"
        accept="application/pdf"
        class="block w-full text-sm text-gray-500
          file:mr-4 file:py-2 file:px-4
          file:rounded-full file:border-0
          file:text-sm file:font-semibold
          file:bg-blue-50 file:text-blue-700
          hover:file:bg-blue-100"
        @change="handleFileUpload"
      >
      <p
        v-if="form.errors.pdf"
        class="mt-2 text-sm text-red-600"
      >
        {{ form.errors.pdf }}
      </p>
    </div>

    <div
      v-if="showNewPreview"
      class="mb-4 relative"
    >
      <div class="flex justify-between items-center mb-2">
        <h2 class="text-xl font-semibold">
          New Contract Preview
        </h2>
        <button
          class="px-2 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300"
          @click="closeNewPreview"
        >
          Close Preview
        </button>
      </div>
      <iframe
        :src="newPreviewUrl"
        width="100%"
        height="600px"
        class="border"
      />
    </div>

    <div
      v-if="showUploadForm && selectedFile"
      class="mt-4"
    >
      <button
        class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600"
        @click="uploadFile"
      >
        {{ existingContract ? 'Upload New Version' : 'Upload Contract' }}
      </button>
    </div>

    <div
      v-if="uploadStatus"
      :class="{'text-green-600': uploadStatus === 'success', 'text-red-600': uploadStatus === 'error'}"
      class="mt-4"
    >
      {{ statusMessage }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  booking: Object,
})

// Compute the contract URL to use the Laravel route for viewing
const existingContractUrl = computed(() => {
  if (props.booking.contract && props.booking.contract.asset_url) {
    // Use Laravel route to serve the contract instead of direct S3 URL
    return route('View Booking Contract', {
      band: props.booking.band_id,
      booking: props.booking.id
    })
  }
  return null
})

const existingContract = computed(() => !!existingContractUrl.value)
const showUploadForm = ref(!existingContract.value)
const selectedFile = ref(null)
const newPreviewUrl = ref(null)
const showPreview = ref(false)
const showNewPreview = ref(false)
const uploadStatus = ref(null)
const statusMessage = ref('')

const form = useForm({
  pdf: null,
})

onMounted(() => {
  if (existingContract.value) {
    showPreview.value = true
  }
})

const viewExistingContract = () => {
  showPreview.value = true
}

const initiateReplace = () => {
  showUploadForm.value = true
  showPreview.value = false
  showNewPreview.value = false
}

const cancelReplace = () => {
  showUploadForm.value = false
  showPreview.value = true
  showNewPreview.value = false
  selectedFile.value = null
  newPreviewUrl.value = null
  form.pdf = null
}

const handleFileUpload = (event) => {
  const file = event.target.files[0]
  if (file && file.type === 'application/pdf') {
    selectedFile.value = file
    form.pdf = file
    newPreviewUrl.value = URL.createObjectURL(file)
    showNewPreview.value = true
    uploadStatus.value = null
    statusMessage.value = ''
  } else {
    selectedFile.value = null
    form.pdf = null
    newPreviewUrl.value = null
    showNewPreview.value = false
    uploadStatus.value = 'error'
    statusMessage.value = 'Please select a valid PDF file.'
  }
}

const closePreview = () => {
  showPreview.value = false
}

const closeNewPreview = () => {
  showNewPreview.value = false
}

const uploadFile = () => {
  form.post(route('Upload Booking Contract', {band: props.booking.band_id, booking: props.booking.id }), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      uploadStatus.value = 'success'
      statusMessage.value = 'PDF uploaded successfully!'
      showUploadForm.value = false
      selectedFile.value = null
      showNewPreview.value = false
      // Refresh the page or update the booking data to show the new contract
    },
    onError: (errors) => {
      uploadStatus.value = 'error'
      statusMessage.value = errors.pdf || 'Failed to upload PDF. Please try again.'
    },
  })
}
</script>