<template>
  <breeze-authenticated-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
        <Link
          href="/bands"
          class="hover:text-blue-600 dark:hover:text-blue-400"
        >
          Bands
        </Link> :: 
        <Link
          :href="`/bands/${band.id}/edit`"
          class="hover:text-blue-600 dark:hover:text-blue-400"
        >
          {{ band.name }}
        </Link> :: 
        Edit Permissions
      </h2>
    </template>

    <Container>
      <div class="max-w-2xl mx-auto">
        <div class="componentPanel rounded-lg shadow-sm p-6">
          <!-- User Info Header -->
          <div class="flex items-center justify-between pb-6 border-b border-gray-200 dark:border-gray-600 mb-6">
            <div class="flex items-center">
              <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                <svg
                  class="w-6 h-6 text-blue-600 dark:text-blue-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                  />
                </svg>
              </div>
              <div class="ml-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                  {{ user.name }}
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  {{ user.email }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                  Member of {{ band.name }}
                </p>
              </div>
            </div>
            <Button
              label="Remove Member"
              icon="pi pi-trash"
              severity="danger"
              size="small"
              :loading="deleting"
              @click="deleteMember"
            />
          </div>

          <!-- Permissions Grid -->
          <div class="space-y-6">
            <div
              v-for="permission in permissionList"
              :key="permission.name"
              class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4"
            >
              <h4 class="text-base font-medium text-gray-900 dark:text-white capitalize mb-4">
                {{ permission.name }}
              </h4>
              
              <div class="grid grid-cols-2 gap-4">
                <!-- Read Permission -->
                <div class="flex items-center space-x-3">
                  <Checkbox
                    v-model="localPermissions['read_' + permission.name]"
                    :binary="true"
                    :true-value="1"
                    :false-value="0"
                    :input-id="`read_${permission.name}`"
                  />
                  <label 
                    :for="`read_${permission.name}`"
                    class="text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer"
                  >
                    View {{ permission.name }}
                  </label>
                </div>

                <!-- Write Permission -->
                <div class="flex items-center space-x-3">
                  <Checkbox
                    v-model="localPermissions['write_' + permission.name]"
                    :binary="true"
                    :true-value="1"
                    :false-value="0"
                    :input-id="`write_${permission.name}`"
                  />
                  <label 
                    :for="`write_${permission.name}`"
                    class="text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer"
                  >
                    Edit {{ permission.name }}
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-600 mt-8">
            <Link :href="`/bands/${band.id}/edit`">
              <Button
                label="Back to Band"
                icon="pi pi-arrow-left"
                severity="secondary"
                text
              />
            </Link>
            <Button
              label="Save Permissions"
              icon="pi pi-save"
              :loading="saving"
              @click="save"
            />
          </div>
        </div>
      </div>
    </Container>
  </breeze-authenticated-layout>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
import Checkbox from 'primevue/checkbox'

export default {
  components: {
    BreezeAuthenticatedLayout,
    Checkbox,
  },
  props: {
    band: {
      required: true,
      type: Object
    },
    user: {
      required: true,
      type: Object
    },
    permissions: {
      required: true,
      type: Object
    }
  },
  data() {
    return {
      saving: false,
      deleting: false,
      localPermissions: { ...this.permissions }, // Create a local copy
      permissionList: [
        { name: 'events' },
        { name: 'bookings' },
        { name: 'invoices' },
        { name: 'charts' }
      ]
    }
  },
  methods: {
    save() {
      this.saving = true
      this.$inertia.post('/permissions/' + this.band.id + '/' + this.user.id, {
        permissions: this.localPermissions
      }, {
        onFinish: () => {
          this.saving = false
        }
      })
    },
    deleteMember() {
      if (confirm(`Are you sure you want to remove ${this.user.name} from ${this.band.name}?`)) {
        this.deleting = true
        this.$inertia.delete(`/bands/${this.band.id}/members/${this.user.id}`, {
          onFinish: () => {
            this.deleting = false
          }
        })
      }
    }
  }
}
</script>