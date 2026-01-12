<template>
  <transition name="fade">
    <div
      class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50 flex items-center justify-center p-4"
      @click.self="$emit('close')"
    >
      <transition name="slide-down">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-2xl w-full">
          <!-- Header -->
          <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ isEditing ? 'Edit Roster' : 'Create New Roster' }}
            </h2>
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
          <div class="p-6 space-y-6">
            <!-- Name -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Roster Name <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.name"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white"
                placeholder="e.g., Full Band, Acoustic Trio"
                :class="{ 'border-red-500': errors.name }"
              />
              <p v-if="errors.name" class="mt-1 text-sm text-red-600">
                {{ errors.name }}
              </p>
            </div>

            <!-- Description -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Description
              </label>
              <textarea
                v-model="form.description"
                rows="3"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white"
                placeholder="Brief description of this roster lineup"
                :class="{ 'border-red-500': errors.description }"
              />
              <p v-if="errors.description" class="mt-1 text-sm text-red-600">
                {{ errors.description }}
              </p>
            </div>

            <!-- Options -->
            <div class="space-y-3">
              <!-- Is Active -->
              <div class="flex items-center">
                <input
                  id="is_active"
                  v-model="form.is_active"
                  type="checkbox"
                  class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                />
                <label for="is_active" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                  Active roster
                </label>
              </div>

              <!-- Is Default -->
              <div class="flex items-center">
                <input
                  id="is_default"
                  v-model="form.is_default"
                  type="checkbox"
                  class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                  :disabled="isEditing && roster.is_default"
                />
                <label for="is_default" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                  Set as default roster
                  <span v-if="isEditing && roster.is_default" class="text-gray-500 text-xs">
                    (already default)
                  </span>
                </label>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-between p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-slate-900 rounded-b-lg">
            <div>
              <button
                v-if="isEditing && !roster.is_default"
                @click="deleteRoster"
                :disabled="processing"
                class="px-4 py-2 text-sm font-medium text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50"
              >
                Delete Roster
              </button>
            </div>
            <div class="flex gap-3">
              <button
                @click="$emit('close')"
                :disabled="processing"
                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white disabled:opacity-50"
              >
                Cancel
              </button>
              <button
                @click="saveRoster"
                :disabled="processing || !form.name"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
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
                {{ isEditing ? 'Update' : 'Create' }} Roster
              </button>
            </div>
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
      default: null
    },
    bandId: {
      type: Number,
      required: true
    }
  },
  emits: ['close', 'saved'],
  data() {
    return {
      form: {
        name: '',
        description: '',
        is_active: true,
        is_default: false
      },
      errors: {},
      processing: false
    }
  },
  computed: {
    isEditing() {
      return !!this.roster
    }
  },
  mounted() {
    if (this.roster) {
      this.form = {
        name: this.roster.name,
        description: this.roster.description || '',
        is_active: this.roster.is_active,
        is_default: this.roster.is_default
      }
    }
  },
  methods: {
    saveRoster() {
      this.errors = {}
      this.processing = true

      const url = this.isEditing
        ? route('rosters.update', this.roster.id)
        : route('bands.rosters.store', this.bandId)

      const method = this.isEditing ? 'patch' : 'post'

      this.$inertia[method](url, this.form, {
        onSuccess: () => {
          this.$emit('saved')
        },
        onError: (errors) => {
          this.errors = errors
        },
        onFinish: () => {
          this.processing = false
        }
      })
    },
    deleteRoster() {
      if (!confirm(`Are you sure you want to delete "${this.roster.name}"?`)) {
        return
      }

      this.processing = true

      this.$inertia.delete(route('rosters.destroy', this.roster.id), {
        onSuccess: () => {
          this.$emit('saved')
        },
        onError: (errors) => {
          this.errors = errors
          alert(errors.message || 'Failed to delete roster')
        },
        onFinish: () => {
          this.processing = false
        }
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
