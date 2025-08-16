<template>
  <Container>
    <div class="max-w-md mx-auto">
      <div class="componentPanel rounded-lg shadow-sm p-6">
        <div class="text-center mb-6">
          <svg
            class="mx-auto h-12 w-12 text-blue-600 dark:text-blue-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1"
              d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"
            />
          </svg>
          <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
            Create Your Band
          </h3>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Set up your band to start managing events, bookings, and more.
          </p>
        </div>

        <!-- Error Display -->
        <div
          v-if="errors && Object.keys(errors).length > 0"
          class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4"
        >
          <div class="flex">
            <svg
              class="w-5 h-5 text-red-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                Please fix the following errors:
              </h3>
              <ul class="mt-2 text-sm text-red-700 dark:text-red-300 list-disc list-inside">
                <li
                  v-for="(error, prop) in errors"
                  :key="prop"
                >
                  {{ error }}
                </li>
              </ul>
            </div>
          </div>
        </div>

        <form
          class="space-y-6"
          @submit.prevent="createBand"
        >
          <!-- Band Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Band Name <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
              placeholder="Enter your band name"
              required
            >
          </div>

          <!-- Site Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Page Name (URL) <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.site_name"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
              placeholder="band_name"
              required
            >
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              This will be used in your band's URL. Only letters, numbers, underscores, and hyphens allowed.
            </p>
          </div>

          <!-- Submit Button -->
          <div class="pt-4">
            <Button
              type="submit"
              label="Create Band"
              icon="pi pi-plus"
              :loading="loading"
              class="w-full"
            />
          </div>
        </form>
      </div>
    </div>
  </Container>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'

export default {
  components: {

  },
  layout: BreezeAuthenticatedLayout,
  pageTitle: 'Create Band',

  props: ['errors'],
  data() {
    return {
      loading: false,
      form: {
        name: '',
        site_name: ''
      }
    }
  },
  methods: {
    createBand() {
      this.loading = true
      this.$inertia.post('/bands', this.form, {
        onFinish: () => {
          this.loading = false
        }
      })
    }
  }
}
</script>