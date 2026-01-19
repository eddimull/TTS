<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
      <div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-50">
          Band Roles / Instruments
        </h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
          Manage consistent role names used across rosters, events, and call lists
        </p>
      </div>
      <button
        @click="openCreateModal"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium transition-colors"
      >
        <i class="pi pi-plus mr-2" />
        Add Role
      </button>
    </div>

    <!-- Empty State -->
    <div
      v-if="!roles || roles.length === 0"
      class="text-center py-12 bg-gray-50 dark:bg-slate-800 rounded-lg"
    >
      <i class="pi pi-briefcase text-4xl text-gray-400 dark:text-gray-600 mb-4" />
      <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
        No roles
      </h3>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Get started by adding a role/instrument for your band.
      </p>
      <div class="mt-6">
        <button
          @click="openCreateModal"
          class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium transition-colors"
        >
          <i class="pi pi-plus mr-2" />
          Add Role
        </button>
      </div>
    </div>

    <!-- Roles List -->
    <div v-else class="space-y-2">
      <div
        v-for="role in roles"
        :key="role.id"
        class="flex items-center justify-between p-4 bg-white dark:bg-slate-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:shadow-md transition-shadow"
      >
        <div class="flex items-center gap-4 flex-1">
          <!-- Drag Handle -->
          <div class="cursor-move text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
              <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
            </svg>
          </div>

          <!-- Role Info -->
          <div class="flex-1">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
              {{ role.name }}
            </h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-0.5">
              <span v-if="role.roster_members_count > 0">{{ role.roster_members_count }} roster members</span>
              <span v-if="role.event_members_count > 0">
                <span v-if="role.roster_members_count > 0"> • </span>
                {{ role.event_members_count }} event members
              </span>
              <span v-if="role.substitute_call_lists_count > 0">
                <span v-if="role.roster_members_count > 0 || role.event_members_count > 0"> • </span>
                {{ role.substitute_call_lists_count }} in call lists
              </span>
              <span v-if="!role.roster_members_count && !role.event_members_count && !role.substitute_call_lists_count">
                Not in use
              </span>
            </p>
          </div>

          <!-- Inactive Badge -->
          <span
            v-if="!role.is_active"
            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300"
          >
            Inactive
          </span>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2">
          <button
            @click="openEditModal(role)"
            class="p-2 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400"
            title="Edit role"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
          </button>
          <button
            v-if="role.is_active"
            @click="deactivateRole(role)"
            class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
            title="Deactivate role"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
          </button>
          <button
            v-else
            @click="reactivateRole(role)"
            class="p-2 text-gray-400 hover:text-green-600 dark:hover:text-green-400"
            title="Reactivate role"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Role Modal (Create/Edit) -->
    <RoleModal
      v-if="showRoleModal"
      :role="selectedRole"
      :band-id="band.id"
      @close="closeRoleModal"
      @saved="handleRoleSaved"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import RoleModal from './RoleModal.vue';

const props = defineProps({
  band: {
    type: Object,
    required: true,
  },
  roles: {
    type: Array,
    default: () => [],
  },
});

const showRoleModal = ref(false);
const selectedRole = ref(null);

const openCreateModal = () => {
  selectedRole.value = null;
  showRoleModal.value = true;
};

const openEditModal = (role) => {
  selectedRole.value = role;
  showRoleModal.value = true;
};

const closeRoleModal = () => {
  showRoleModal.value = false;
  selectedRole.value = null;
};

const handleRoleSaved = () => {
  closeRoleModal();
  router.reload({ only: ['roles'] });
};

const deactivateRole = (role) => {
  if (confirm(`Deactivate "${role.name}"? It will no longer appear in dropdowns but existing assignments will remain.`)) {
    router.delete(
      route('bands.roles.destroy', { band: props.band.id, role: role.id }),
      {
        onSuccess: () => {
          router.reload({ only: ['roles'] });
        },
      }
    );
  }
};

const reactivateRole = (role) => {
  router.patch(
    route('bands.roles.update', { band: props.band.id, role: role.id }),
    { is_active: true },
    {
      onSuccess: () => {
        router.reload({ only: ['roles'] });
      },
    }
  );
};
</script>
