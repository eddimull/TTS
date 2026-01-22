<template>
  <Container>
    <div class="max-w-7xl mx-auto">
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
          <a
            :href="route('bands.roles.page', band.id)"
            class='py-4 px-1 border-b-2 font-medium text-sm transition-colors border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
          >
            Instruments and Roles
          </a>
        </nav>
      </div>

      <!-- Rosters Tab Content -->
      <div v-if="activeTab === 'rosters'">
        <RosterManager
          :band="band"
          :rosters="rosters"
        />
      </div>

      <!-- Call Lists Tab Content -->
      <div v-if="activeTab === 'callLists'">
        <CallListsManager
          :band="band"
          :roster-members="rosterMembers"
        />
      </div>
    </div>
  </Container>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
import RosterManager from './Components/RosterManager.vue'
import CallListsManager from './Components/CallListsManager.vue'

export default {
  components: {
    RosterManager,
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
    }
  }
}
</script>
