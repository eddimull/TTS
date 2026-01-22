<template>
  <transition name="fade">
    <div
      class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50 flex items-center justify-center p-4"
      @click.self="$emit('close')"
    >
      <transition name="slide-down">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
            <div>
              <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Manage Roster Members
              </h2>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ roster.name }}
              </p>
            </div>
            <button
              @click="$emit('close')"
              class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto p-6">
            <!-- Add Member Section -->
            <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                  Add Member
                </h3>
                <button
                  v-if="showAddForm"
                  @click="cancelAdd"
                  class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                >
                  Cancel
                </button>
              </div>

              <!-- Add Button -->
              <button
                v-if="!showAddForm"
                @click="showAddForm = true"
                class="w-full px-4 py-3 border-2 border-dashed border-blue-300 dark:border-blue-700 rounded-lg text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors flex items-center justify-center gap-2"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add New Member
              </button>

              <!-- Add Form -->
              <div v-else class="space-y-4">
                <!-- Member Type Toggle -->
                <div class="flex gap-2">
                  <button
                    @click="memberForm.type = 'user'"
                    :class="[
                      'flex-1 px-4 py-2 rounded-lg font-medium transition-colors',
                      memberForm.type === 'user'
                        ? 'bg-blue-600 text-white'
                        : 'bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-600'
                    ]"
                  >
                    Band Member
                  </button>
                  <button
                    @click="memberForm.type = 'non-user'"
                    :class="[
                      'flex-1 px-4 py-2 rounded-lg font-medium transition-colors',
                      memberForm.type === 'non-user'
                        ? 'bg-blue-600 text-white'
                        : 'bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-600'
                    ]"
                  >
                    Substitute / Guest
                  </button>
                </div>

                <!-- User Selection (for band members) -->
                <div v-if="memberForm.type === 'user'">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Select Band Member <span class="text-red-500">*</span>
                  </label>
                  <select
                    v-model="memberForm.user_id"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white"
                    :class="{ 'border-red-500': memberErrors.user_id }"
                  >
                    <option value="">Choose a member...</option>
                    <option
                      v-for="user in availableBandMembers"
                      :key="user.id"
                      :value="user.id"
                    >
                      {{ user.name }} ({{ user.email }})
                    </option>
                  </select>
                  <p v-if="memberErrors.user_id" class="mt-1 text-sm text-red-600">
                    {{ memberErrors.user_id }}
                  </p>
                </div>

                <!-- Non-User Fields -->
                <div v-else class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Name <span class="text-red-500">*</span>
                    </label>
                    <input
                      v-model="memberForm.name"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white"
                      placeholder="Full name"
                      :class="{ 'border-red-500': memberErrors.name }"
                    />
                    <p v-if="memberErrors.name" class="mt-1 text-sm text-red-600">
                      {{ memberErrors.name }}
                    </p>
                  </div>

                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Email
                      </label>
                      <input
                        v-model="memberForm.email"
                        type="email"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white"
                        placeholder="email@example.com"
                        :class="{ 'border-red-500': memberErrors.email }"
                      />
                      <p v-if="memberErrors.email" class="mt-1 text-sm text-red-600">
                        {{ memberErrors.email }}
                      </p>
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Phone
                      </label>
                      <input
                        v-model="memberForm.phone"
                        type="tel"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white"
                        placeholder="(555) 123-4567"
                      />
                    </div>
                  </div>
                </div>

                <!-- Common Fields -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Role / Instrument
                  </label>
                  <select
                    v-model="memberForm.band_role_id"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white"
                  >
                    <option value="">Select a role...</option>
                    <option
                      v-for="role in bandRoles"
                      :key="role.id"
                      :value="role.id"
                    >
                      {{ role.name }}
                    </option>
                  </select>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Roles can be managed in your <a :href="route('bands.roles.page', bandId)" target="_blank" class="text-blue-600 hover:underline">band settings</a>
                  </p>
                </div>

                <!-- Notes -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Notes
                  </label>
                  <textarea
                    v-model="memberForm.notes"
                    rows="2"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white"
                    placeholder="Any additional notes..."
                  />
                </div>

                <!-- Submit Button -->
                <button
                  @click="addMember"
                  :disabled="processing"
                  class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                >
                  <svg
                    v-if="processing"
                    class="animate-spin h-4 w-4"
                    fill="none"
                    viewBox="0 0 24 24"
                  >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                  </svg>
                  Add Member
                </button>
              </div>
            </div>

            <!-- Members List -->
            <div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Current Members ({{ members.length }})
              </h3>

              <!-- Empty State -->
              <div
                v-if="members.length === 0"
                class="text-center py-8 text-gray-500 dark:text-gray-400"
              >
                <svg class="mx-auto h-12 w-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                No members in this roster yet
              </div>

              <!-- Members Grid -->
              <div v-else class="space-y-3">
                <div
                  v-for="member in members"
                  :key="member.id"
                  class="flex items-center justify-between p-4 bg-white dark:bg-slate-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:shadow-md transition-shadow"
                >
                  <div class="flex-1">
                    <div class="flex items-center gap-2">
                      <h4 class="font-semibold text-gray-900 dark:text-white">
                        {{ member.name }}
                      </h4>
                      <span
                        v-if="member.is_user"
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                      >
                        Band Member
                      </span>
                      <span
                        v-else
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200"
                      >
                        Guest
                      </span>
                      <span
                        v-if="!member.is_active"
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"
                      >
                        Inactive
                      </span>
                    </div>

                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-400 space-y-1">
                      <div v-if="member.role">
                        <span class="font-medium">Role:</span> {{ member.role }}
                      </div>
                      <div v-if="member.email">
                        <span class="font-medium">Email:</span> {{ member.email }}
                      </div>
                      <div v-if="member.phone">
                        <span class="font-medium">Phone:</span> {{ member.phone }}
                      </div>
                      <div v-if="member.notes" class="text-xs italic">
                        {{ member.notes }}
                      </div>
                    </div>
                  </div>

                  <div class="flex items-center gap-2 ml-4">
                    <button
                      @click="toggleMemberActive(member)"
                      :title="member.is_active ? 'Mark as inactive' : 'Mark as active'"
                      class="p-2 text-gray-400 hover:text-yellow-600 dark:hover:text-yellow-400"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                      </svg>
                    </button>
                    <button
                      @click="removeMember(member)"
                      class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-slate-900">
            <button
              @click="$emit('close')"
              class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
            >
              Done
            </button>
          </div>
        </div>
      </transition>
    </div>
  </transition>
</template>

<script>
export default {
  props: {
    roster: {
      type: Object,
      required: true
    },
    bandId: {
      type: Number,
      required: true
    }
  },
  emits: ['close'],
  data() {
    return {
      members: [],
      bandMembers: [],
      bandRoles: [],
      showAddForm: false,
      memberForm: {
        type: 'user',
        user_id: '',
        name: '',
        email: '',
        phone: '',
        band_role_id: '',
        notes: ''
      },
      memberErrors: {},
      processing: false
    }
  },
  computed: {
    availableBandMembers() {
      const currentUserIds = this.members
        .filter(m => m.user_id)
        .map(m => m.user_id)

      return this.bandMembers.filter(user => !currentUserIds.includes(user.id))
    }
  },
  mounted() {
    this.loadRosterDetails()
    this.loadBandMembers()
    this.loadBandRoles()
  },
  methods: {
    loadRosterDetails() {
      axios.get(route('rosters.show', this.roster.id))
        .then(response => {
          this.members = response.data.members || []
        })
        .catch(error => {
          console.error('Failed to load roster details:', error)
        })
    },
    loadBandMembers() {
      // Load band members for the dropdown
      axios.get(`/api/bands/${this.bandId}/members`)
        .then(response => {
          this.bandMembers = response.data.members || []
        })
        .catch(error => {
          console.error('Failed to load band members:', error)
          // Fallback - will need to be implemented in backend
        })
    },
    loadBandRoles() {
      // Load band roles for the dropdown
      axios.get(route('bands.roles.index', this.bandId))
        .then(response => {
          this.bandRoles = response.data.roles || []
        })
        .catch(error => {
          console.error('Failed to load band roles:', error)
        })
    },
    cancelAdd() {
      this.showAddForm = false
      this.resetMemberForm()
      this.memberErrors = {}
    },
    resetMemberForm() {
      this.memberForm = {
        type: 'user',
        user_id: '',
        name: '',
        email: '',
        phone: '',
        band_role_id: '',
        notes: ''
      }
    },
    addMember() {
      this.memberErrors = {}
      this.processing = true

      const data = this.memberForm.type === 'user'
        ? {
            user_id: this.memberForm.user_id,
            band_role_id: this.memberForm.band_role_id,
            notes: this.memberForm.notes
          }
        : {
            name: this.memberForm.name,
            email: this.memberForm.email,
            phone: this.memberForm.phone,
            band_role_id: this.memberForm.band_role_id,
            notes: this.memberForm.notes
          }

      axios.post(route('rosters.members.store', this.roster.id), data)
        .then(() => {
          this.loadRosterDetails()
          this.cancelAdd()
        })
        .catch(error => {
          if (error.response?.data?.errors) {
            this.memberErrors = error.response.data.errors
          } else {
            alert('Failed to add member')
          }
        })
        .finally(() => {
          this.processing = false
        })
    },
    toggleMemberActive(member) {
      axios.post(route('rosters.members.toggleActive', member.id))
        .then(() => {
          this.loadRosterDetails()
        })
        .catch(error => {
          console.error('Failed to toggle member status:', error)
          alert('Failed to update member status')
        })
    },
    removeMember(member) {
      if (!confirm(`Remove ${member.name} from this roster?`)) {
        return
      }

      axios.delete(route('rosters.members.destroy', member.id))
        .then(() => {
          this.loadRosterDetails()
        })
        .catch(error => {
          console.error('Failed to remove member:', error)
          alert(error.response?.data?.message || 'Failed to remove member')
        })
    }
  }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.slide-down-enter-active {
  transition: all 0.3s ease-out;
}

.slide-down-leave-active {
  transition: all 0.2s ease-in;
}

.slide-down-enter-from {
  transform: translateY(-20px);
  opacity: 0;
}

.slide-down-leave-to {
  transform: translateY(-20px);
  opacity: 0;
}
</style>
