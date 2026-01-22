<template>
  <teleport to="body">
    <transition name="fade">
      <div
        class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50 flex items-center justify-center p-4"
        @click.self="$emit('close')"
      >
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-md w-full p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white">
              {{ role ? 'Edit Role' : 'Add Role' }}
            </h3>
            <button
              @click="$emit('close')"
              class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <form @submit.prevent="saveRole" class="space-y-4">
            <!-- Name -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Role / Instrument Name <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.name"
                type="text"
                required
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="e.g., Guitar, Vocals, Drums"
              />
              <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
            </div>

            <!-- Display Order -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Display Order
              </label>
              <input
                v-model.number="form.display_order"
                type="number"
                min="0"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="0"
              />
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Lower numbers appear first in lists
              </p>
            </div>

            <!-- Active Status -->
            <div v-if="role" class="flex items-center gap-2">
              <input
                v-model="form.is_active"
                type="checkbox"
                id="is-active"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <label for="is-active" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                Active (appears in dropdowns)
              </label>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
              <button
                type="button"
                @click="$emit('close')"
                class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="loading || !form.name"
                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ loading ? 'Saving...' : 'Save' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  role: {
    type: Object,
    default: null,
  },
  bandId: {
    type: Number,
    required: true,
  },
});

const emit = defineEmits(['close', 'saved']);

const form = ref({
  name: '',
  display_order: 0,
  is_active: true,
});

const loading = ref(false);
const errors = ref({});

// Initialize form when role changes
watch(() => props.role, (newRole) => {
  if (newRole) {
    form.value = {
      name: newRole.name,
      display_order: newRole.display_order ?? 0,
      is_active: newRole.is_active ?? true,
    };
  } else {
    form.value = {
      name: '',
      display_order: 0,
      is_active: true,
    };
  }
  errors.value = {};
}, { immediate: true });

const saveRole = async () => {
  loading.value = true;
  errors.value = {};

  try {
    if (props.role) {
      // Update existing role
      await axios.patch(
        route('bands.roles.update', { band: props.bandId, role: props.role.id }),
        form.value
      );
    } else {
      // Create new role
      await axios.post(
        route('bands.roles.store', { band: props.bandId }),
        form.value
      );
    }

    emit('saved');
  } catch (error) {
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors;
    } else {
      console.error('Failed to save role:', error);
      alert('Failed to save role. Please try again.');
    }
  } finally {
    loading.value = false;
  }
};
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
</style>
