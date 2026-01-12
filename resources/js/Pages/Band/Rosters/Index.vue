<template>
  <Container>
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Rosters</h1>
          <p class="text-gray-600 dark:text-gray-400 mt-1">
            Manage your band's rosters and member lineups for different events
          </p>
        </div>
        <button
          v-if="activeTab === 'rosters'"
          @click="openCreateModal"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Create Roster
        </button>
      </div>

      <!-- Tab Navigation -->
      <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="-mb-px flex space-x-8">
          <button
            @click="activeTab = 'rosters'"
            :class="[
              'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
              activeTab === 'rosters'
                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
            ]"
          >
            Rosters
          </button>
          <button
            @click="activeTab = 'callLists'"
            :class="[
              'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
              activeTab === 'callLists'
                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
            ]"
          >
            Substitute Call Lists
          </button>
        </nav>
      </div>

      <!-- Rosters Tab Content -->
      <div v-if="activeTab === 'rosters'">
        <!-- Empty State -->
        <div
          v-if="!rosters || rosters.length === 0"
          class="text-center py-12 bg-white dark:bg-slate-800 rounded-lg shadow"
        >
          <svg
            class="mx-auto h-12 w-12 text-gray-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
            />
          </svg>
          <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No rosters</h3>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Get started by creating a new roster for your band.
          </p>
          <div class="mt-6">
            <button
              @click="openCreateModal"
              class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
            >
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
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
          class="bg-white dark:bg-slate-800 rounded-lg shadow hover:shadow-lg transition-shadow cursor-pointer"
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
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                />
              </svg>
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
      </div>

      <!-- Call Lists Tab Content -->
      <div v-if="activeTab === 'callLists'">
        <CallListsManager
          :band="band"
          :roster-members="rosterMembers"
        />
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
  </Container>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
import RosterModal from './Components/RosterModal.vue'
import RosterMembersModal from './Components/RosterMembersModal.vue'
import CallListsManager from './Components/CallListsManager.vue'

export default {
  components: {
    RosterModal,
    RosterMembersModal,
    CallListsManager,
  },
  layout: BreezeAuthenticatedLayout,
  pageTitle: 'Rosters',
  props: {
    band: {
      type: Object,
      required: true
    },
    rosters: {
      type: Array,
      default: () => []
    },
    rosterMembers: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      activeTab: 'rosters',
      showRosterModal: false,
      showMembersModal: false,
      selectedRoster: null,
    }
  },
  methods: {
    openCreateModal() {
      this.selectedRoster = null
      this.showRosterModal = true
    },
    openEditModal(roster) {
      this.selectedRoster = roster
      this.showRosterModal = true
    },
    closeRosterModal() {
      this.showRosterModal = false
      this.selectedRoster = null
    },
    handleRosterSaved() {
      this.closeRosterModal()
      this.$inertia.reload({ only: ['rosters'] })
    },
    openMembersModal(roster) {
      this.selectedRoster = roster
      this.showMembersModal = true
    },
    closeMembersModal() {
      this.showMembersModal = false
      this.selectedRoster = null
      this.$inertia.reload({ only: ['rosters'] })
    },
    setAsDefault(roster) {
      if (confirm(`Set "${roster.name}" as the default roster?`)) {
        this.$inertia.post(
          route('rosters.setDefault', { band: this.band.id, roster: roster.id }),
          {},
          {
            onSuccess: () => {
              this.$inertia.reload({ only: ['rosters'] })
            }
          }
        )
      }
    }
  }
}
</script>
