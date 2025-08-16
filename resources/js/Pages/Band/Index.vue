<template>
  <breeze-authenticated-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
        Your Bands
      </h2>
    </template>

    <Container>
      <div class="componentPanel rounded-lg shadow-sm p-6">
        <div v-if="bands.length > 0">
          <!-- Header with Create Button -->
          <div class="flex justify-between items-center mb-6">
            <Link href="/bands/create">
              <Button
                label="Create New Band"
                icon="pi pi-plus"
                size="small"
              />
            </Link>
          </div>

          <!-- Bands Grid -->
          <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="band in bands"
              :key="band.id"
              class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                  <h4 class="text-lg font-medium text-gray-900 dark:text-white truncate">
                    {{ band.name }}
                  </h4>
                  <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ band.site_name }}
                  </p>
                  <div
                    v-if="band.logo"
                    class="mt-3"
                  >
                    <img 
                      :src="band.logo" 
                      :alt="band.name + ' logo'"
                      class="w-12 h-12 object-cover rounded-lg border border-gray-200 dark:border-gray-600"
                    >
                  </div>
                </div>
                <div class="ml-4 flex-shrink-0">
                  <Link :href="`/bands/${band.id}/edit`">
                    <Button
                      v-tooltip="'Edit Band'"
                      icon="pi pi-pencil"
                      severity="secondary"
                      text
                      size="small"
                    />
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-else
          class="text-center py-12"
        >
          <div class="max-w-sm mx-auto">
            <svg
              class="mx-auto h-16 w-16 text-gray-400 dark:text-gray-600"
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
            <h3 class="mt-6 text-lg font-medium text-gray-900 dark:text-white">
              No bands yet
            </h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
              Get started by creating your first band.
            </p>
            <div class="mt-6">
              <Link href="/bands/create">
                <Button
                  label="Create Your First Band"
                  icon="pi pi-plus"
                />
              </Link>
            </div>
          </div>
        </div>
      </div>
    </Container>
  </breeze-authenticated-layout>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
import Button from 'primevue/button'
import Container from '@/Components/Container.vue'
import { Link } from '@inertiajs/vue3'

export default {
  components: {
    BreezeAuthenticatedLayout,
    Button,
    Container,
    Link
  },
  props: ['bands', 'successMessage']
}
</script>