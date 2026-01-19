<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
      <div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-50">
          Rosters
        </h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
          Manage your band's rosters and member lineups for different events
        </p>
      </div>
      <button
        @click="openCreateModal"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium transition-colors"
      >
        <i class="pi pi-plus mr-2" />
        Create Roster
      </button>
    </div>

    <!-- Empty State -->
    <div
      v-if="!rosters || rosters.length === 0"
      class="text-center py-12 bg-gray-50 dark:bg-slate-800 rounded-lg"
    >
      <i class="pi pi-users text-4xl text-gray-400 dark:text-gray-600 mb-4" />
      <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
        No rosters
      </h3>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Get started by creating a new roster for your band.
      </p>
      <div class="mt-6">
        <button
          @click="openCreateModal"
          class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium transition-colors"
        >
          <i class="pi pi-plus mr-2" />
          Create Roster
        </button>
      </div>
    </div>

    <!-- Rosters Grid -->
    <div
      v-else
      class="grid gap-6 md:grid-cols-2 lg:grid-cols-3"
    >
      <div
        v-for="roster in rosters"
        :key="roster.id"
        class="bg-white dark:bg-slate-800 rounded-lg shadow-md hover:shadow-lg transition-shadow cursor-pointer"
        @click="openEditModal(roster)"
      >
        <div class="p-6">
          <!-- Header with default badge -->
          <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ roster.name }}
              </h3>
              <span
                v-if="roster.is_default"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 mt-1"
              >
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path
                    fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                    clip-rule="evenodd"
                  />
                </svg>
                Default
              </span>
            </div>
            <span
              v-if="!roster.is_active"
              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300"
            >
              Inactive
            </span>
          </div>

          <!-- Description -->
          <p
            v-if="roster.description"
            class="text-sm text-gray-600 dark:text-gray-400 mb-4"
          >
            {{ roster.description }}
          </p>

          <!-- Members count -->
          <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
            <i class="pi pi-users mr-2" />
            {{ roster.members_count || 0 }} {{ roster.members_count === 1 ? 'member' : 'members' }}
          </div>

          <!-- Action buttons -->
          <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex gap-2">
            <button
              v-if="!roster.is_default"
              @click.stop="setAsDefault(roster)"
              class="flex-1 px-3 py-2 text-sm bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 rounded hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors"
            >
              Set as Default
            </button>
            <button
              @click.stop="openMembersModal(roster)"
              class="flex-1 px-3 py-2 text-sm bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
            >
              Manage Members
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Roster Modal (Create/Edit) -->
    <RosterModal
      v-if="showRosterModal"
      :roster="selectedRoster"
      :band-id="band.id"
      @close="closeRosterModal"
      @saved="handleRosterSaved"
    />

    <!-- Roster Members Modal -->
    <RosterMembersModal
      v-if="showMembersModal"
      :roster="selectedRoster"
      :band-id="band.id"
      @close="closeMembersModal"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import RosterModal from './RosterModal.vue';
import RosterMembersModal from './RosterMembersModal.vue';

const props = defineProps({
  band: {
    type: Object,
    required: true,
  },
  rosters: {
    type: Array,
    default: () => [],
  },
});

const showRosterModal = ref(false);
const showMembersModal = ref(false);
const selectedRoster = ref(null);

const openCreateModal = () => {
  selectedRoster.value = null;
  showRosterModal.value = true;
};

const openEditModal = (roster) => {
  selectedRoster.value = roster;
  showRosterModal.value = true;
};

const closeRosterModal = () => {
  showRosterModal.value = false;
  selectedRoster.value = null;
};

const handleRosterSaved = () => {
  closeRosterModal();
  router.reload({ only: ['rosters'] });
};

const openMembersModal = (roster) => {
  console.log(roster);
  selectedRoster.value = roster;
  showMembersModal.value = true;
};

const closeMembersModal = () => {
  showMembersModal.value = false;
  selectedRoster.value = null;
  router.reload({ only: ['rosters'] });
};

const setAsDefault = (roster) => {
  if (confirm(`Set "${roster.name}" as the default roster?`)) {
    router.post(
      route('rosters.setDefault', { band: props.band.id, roster: roster.id }),
      {},
      {
        onSuccess: () => {
          router.reload({ only: ['rosters'] });
        },
      }
    );
  }
};
</script>
