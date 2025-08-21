<template>
  <form
    :action="'/bands/' + band.id"
    method="PATCH"
    @submit.prevent="updateBand"
  >
    <!-- Band Name -->
    <div class="mb-6">
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Band Name
      </label>
      <input
        :value="form.name"
        type="text"
        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
        placeholder="Enter band name"
        required
        @input="updateName"
      >
    </div>

    <!-- Site Name -->
    <div class="mb-6">
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Page Name (URL)
      </label>
      <input
        :value="form.site_name"
        type="text"
        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
        placeholder="band_name"
        pattern="([a-zA-z0-9\-_]+)"
        @input="updateSiteName"
      >
      <p
        v-if="urlWarn"
        class="mt-1 text-sm text-red-600 dark:text-red-400"
      >
        Only letters, numbers, underscores, and hyphens are allowed
      </p>
    </div>

    <!-- Logo -->
    <div class="mb-6">
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Band Logo
      </label>
      <div class="space-y-3">
        <div
          v-if="band.logo"
          class="flex items-center gap-4"
        >
          <img
            :src="band.logo"
            alt="Current logo"
            class="w-16 h-16 object-cover rounded-lg border border-gray-200 dark:border-gray-600"
          >
          <span class="text-sm text-gray-600 dark:text-gray-400">Current logo</span>
        </div>
        <FileUpload
          ref="fileUpload"
          mode="basic"
          name="logo"
          accept="image/*"
          :auto="true"
          :custom-upload="true"
          choose-label="Upload New Logo"
          class="w-full"
          @uploader="uploadLogo"
        />
      </div>
    </div>

    <!-- Stripe Setup -->
    <div class="border-t border-gray-200 dark:border-gray-600 pt-6 mb-6">
      <div
        v-if="!band.stripe_accounts"
        class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4"
      >
        <div class="flex items-center gap-3">
          <svg
            class="w-6 h-6 text-yellow-600 dark:text-yellow-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"
            />
          </svg>
          <div>
            <h3 class="font-medium text-yellow-800 dark:text-yellow-200">
              Stripe Payment Setup Required
            </h3>
            <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
              Set up Stripe to accept payments for your band.
            </p>
            <a
              :href="'/bands/' + band.id + '/setupStripe'"
              class="inline-block mt-3"
            >
              <Button
                severity="warning"
                size="small"
              >Setup Stripe</Button>
            </a>
          </div>
        </div>
      </div>
      <div
        v-else
        class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4"
      >
        <div class="flex items-center gap-2">
          <svg
            class="w-5 h-5 text-green-600 dark:text-green-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M5 13l4 4L19 7"
            />
          </svg>
          <span class="text-green-800 dark:text-green-200 font-medium">Stripe account configured</span>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-600">
      <Button
        type="submit"
        label="Update Band"
        icon="pi pi-save"
        :loading="loading"
      />
    </div>
  </form>
</template>

<script>
import FileUpload from 'primevue/fileupload';

export default {
  name: 'EditDetails',
  components: {
    FileUpload,
  },
  props: {
    band: {
      type: Object,
      required: true
    },
    form: {
      type: Object,
      required: true
    },
    loading: {
      type: Boolean,
      default: false
    },
    urlWarn: {
      type: Boolean,
      default: false
    }
  },
  emits: ['update-band', 'update-form', 'upload-logo',],
  methods: {
    updateBand() {
      this.$emit('update-band');
    },
    updateName(event) {
      this.$emit('update-form', 'name', event.target.value);
    },
    updateSiteName(event) {
      this.$emit('update-form', 'site_name', event.target.value);
    },
    uploadLogo(event) {
      this.$emit('upload-logo', event);
    }
  }
}
</script>
