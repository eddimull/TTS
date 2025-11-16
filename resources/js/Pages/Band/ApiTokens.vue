<template>
  <Container>
    <div class="max-w-5xl mx-auto">
      <div class="componentPanel shadow-md rounded-lg p-6 mb-6">
        <!-- Panel Tabs -->
        <div class="mb-6 flex rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600">
          <button
            v-for="panel in panels"
            :key="panel.name"
            :class="[
              panel.name === 'API Tokens'
                ? 'bg-blue-600 text-white' 
                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600',
              'flex-1 py-3 px-4 font-medium transition-colors duration-200 flex items-center justify-center gap-2'
            ]"
            @click="gotoPage(panel.name.toLowerCase())"
          >
            <span
              class="w-5 h-5"
              v-html="panel.icon"
            />
            {{ panel.name }}
          </button>
        </div>

        <div class="mb-6">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            API Tokens
          </h1>
          <p class="mt-2 text-gray-600 dark:text-gray-400">
            Manage API tokens for {{ band.name }}. Use these tokens to integrate with external services like Wix.
          </p>
        </div>
      </div>

      <!-- New Token Modal -->
      <div
        v-if="newToken"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
        @click.self="dismissToken"
      >
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full mx-4 p-6">
          <div class="flex items-start justify-between mb-4">
            <div>
              <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                API Token Created Successfully
              </h3>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ newToken.name }}
              </p>
            </div>
            <button
              class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
              @click="dismissToken"
            >
              <svg
                class="w-6 h-6"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>

          <div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border-2 border-yellow-400 dark:border-yellow-600 rounded-lg">
            <div class="flex items-start gap-3">
              <svg
                class="w-6 h-6 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
              </svg>
              <div>
                <p class="font-semibold text-yellow-900 dark:text-yellow-200">
                  Copy this token now!
                </p>
                <p class="text-sm text-yellow-800 dark:text-yellow-300 mt-1">
                  You won't be able to see it again after closing this dialog. Store it somewhere safe.
                </p>
              </div>
            </div>
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              API Token
            </label>
            <div class="flex gap-2">
              <input
                type="text"
                :value="newToken.token"
                readonly
                class="flex-1 px-4 py-3 font-mono text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white"
                @click="selectAllText"
              >
              <button
                class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-medium"
                @click="copyToken(newToken.token)"
              >
                {{ copied ? '✓ Copied!' : 'Copy' }}
              </button>
            </div>
          </div>

          <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
            <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">
              API Endpoint:
            </p>
            <code class="text-sm text-gray-800 dark:text-gray-200 break-all">{{ apiEndpoint }}</code>
          </div>

          <div class="flex justify-end gap-3">
            <button
              class="px-6 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition font-medium"
              @click="dismissToken"
            >
              I've Saved the Token
            </button>
          </div>
        </div>
      </div>

      <!-- Create Token -->
      <div class="mb-6 componentPanel shadow-md rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
          Create New Token
        </h2>
        <form
          class="space-y-4"
          @submit.prevent="createToken"
        >
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Token Name
            </label>
            <input
              v-model="newTokenName"
              type="text"
              placeholder="Token name (e.g., Wix Integration)"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
              Permissions
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <label
                v-for="permission in availablePermissions"
                :key="permission.name"
                class="flex items-start space-x-3 p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition"
              >
                <input
                  v-model="selectedPermissions"
                  type="checkbox"
                  :value="permission.name"
                  class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                >
                <div class="flex-1">
                  <div class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ permission.label }}
                  </div>
                  <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ getPermissionDescription(permission.name) }}
                  </div>
                </div>
              </label>
            </div>
            <p
              v-if="selectedPermissions.length === 0"
              class="mt-2 text-sm text-amber-600 dark:text-amber-400"
            >
              ⚠️ No permissions selected. This token won't be able to access any endpoints.
            </p>
          </div>

          <div class="flex gap-3">
            <button
              type="submit"
              :disabled="creating"
              class="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded transition"
            >
              {{ creating ? 'Creating...' : 'Create Token' }}
            </button>
            <button
              type="button"
              class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded transition"
              @click="selectAllPermissions"
            >
              Select All
            </button>
            <button
              type="button"
              class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded transition"
              @click="clearPermissions"
            >
              Clear All
            </button>
          </div>
        </form>
      </div>

      <!-- Tokens List -->
      <div class="componentPanel shadow-md rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
          Existing Tokens
        </h2>

        <div
          v-if="tokens.length === 0"
          class="text-center py-8 text-gray-500 dark:text-gray-400"
        >
          No API tokens created yet.
        </div>

        <div
          v-else
          class="space-y-4"
        >
          <div
            v-for="token in tokens"
            :key="token.id"
            class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                  <h3 class="font-semibold text-gray-900 dark:text-white">
                    {{ token.name }}
                  </h3>
                  <span
                    :class="[
                      'px-2 py-1 text-xs rounded',
                      token.is_active
                        ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200'
                        : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200'
                    ]"
                  >
                    {{ token.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </div>

                <!-- Permissions -->
                <div class="mb-2">
                  <span class="text-xs font-medium text-gray-600 dark:text-gray-400 mr-2">Permissions:</span>
                  <div
                    v-if="token.permissions && token.permissions.length > 0"
                    class="inline-flex flex-wrap gap-1 mt-1"
                  >
                    <span
                      v-for="permission in token.permissions"
                      :key="permission"
                      :class="[
                        'inline-flex items-center px-2 py-1 text-xs rounded',
                        getPermissionColor(permission)
                      ]"
                    >
                      {{ formatPermissionLabel(permission) }}
                    </span>
                  </div>
                  <span
                    v-else
                    class="text-xs text-amber-600 dark:text-amber-400"
                  >
                    No permissions (token cannot access any endpoints)
                  </span>
                </div>

                <!-- Metadata -->
                <div class="text-sm text-gray-600 dark:text-gray-400">
                  <span>Created: {{ token.created_at }}</span>
                  <span
                    v-if="token.last_used_at"
                    class="ml-4"
                  >
                    Last used: {{ token.last_used_at }}
                  </span>
                  <span
                    v-else
                    class="ml-4"
                  >Never used</span>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex gap-2 ml-4">
                <button
                  class="px-3 py-2 text-sm bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded transition"
                  @click="toggleToken(token)"
                >
                  {{ token.is_active ? 'Disable' : 'Enable' }}
                </button>
                <button
                  class="px-3 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded transition"
                  @click="deleteToken(token)"
                >
                  Delete
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Container>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
import { router } from '@inertiajs/vue3'

export default {
  layout: BreezeAuthenticatedLayout,
  pageTitle: 'API Tokens',

  props: {
    band: {
      type: Object,
      required: true,
    },
    tokens: {
      type: Array,
      default: () => [],
    },
    availablePermissions: {
      type: Array,
      default: () => [],
    },
    newToken: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      newTokenName: '',
      selectedPermissions: [],
      creating: false,
      copied: false,
      panels: [
        {
          name: 'Details',
          icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>'
        }, {
          name: 'Band Members',
          icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>'
        }, {
          name: 'Calendar Access',
          icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>'
        }, {
          name: 'API Tokens',
          icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>'
        }
      ],
    }
  },

  computed: {
    apiEndpoint() {
      return window.location.origin + '/api/booked-dates'
    },
  },

  methods: {
    gotoPage(pageName) {
      // API Tokens stays on current page
      if (pageName === 'api tokens') {
        return;
      }
      // Other pages go to edit with setting
      this.$inertia.visit(`/bands/${this.band.id}/edit/${pageName}`);
    },

    createToken() {
      this.creating = true
      router.post(
        route('bands.apiTokens.store', this.band.id),
        {
          name: this.newTokenName || 'API Token',
          permissions: this.selectedPermissions,
        },
        {
          onSuccess: () => {
            this.newTokenName = ''
            this.selectedPermissions = []
            this.creating = false
          },
          onError: () => {
            this.creating = false
          },
        }
      )
    },

    selectAllPermissions() {
      this.selectedPermissions = this.availablePermissions.map(p => p.name)
    },

    clearPermissions() {
      this.selectedPermissions = []
    },

    getPermissionDescription(permissionName) {
      const descriptions = {
        'api:read-events': 'View booked dates and event details',
        'api:write-events': 'Create, update, and delete events',
        'api:read-bookings': 'View bookings and financial information',
        'api:write-bookings': 'Create, update, and delete bookings',
      }
      return descriptions[permissionName] || 'API access permission'
    },

    formatPermissionLabel(permissionName) {
      return permissionName
        .replace('api:', '')
        .split('-')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ')
    },

    getPermissionColor(permissionName) {
      if (permissionName.includes('read')) {
        return 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200'
      } else if (permissionName.includes('write')) {
        return 'bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200'
      }
      return 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200'
    },

    toggleToken(token) {
      if (confirm(`Are you sure you want to ${token.is_active ? 'disable' : 'enable'} this token?`)) {
        router.post(route('bands.apiTokens.toggle', [this.band.id, token.id]))
      }
    },

    deleteToken(token) {
      if (confirm('Are you sure you want to delete this token? This action cannot be undone.')) {
        router.delete(route('bands.apiTokens.destroy', [this.band.id, token.id]))
      }
    },

    copyToken(token) {
      navigator.clipboard.writeText(token).then(() => {
        this.copied = true
        setTimeout(() => {
          this.copied = false
        }, 2000)
      })
    },

    dismissToken() {
      router.post(route('bands.apiTokens.dismiss', this.band.id))
    },

    selectAllText(event) {
      event.target.select()
    },
  },
}
</script>
