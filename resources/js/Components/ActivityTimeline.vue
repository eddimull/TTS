<template>
  <div class="activity-timeline">
    <div
      v-if="activities.length === 0"
      class="text-center py-12 text-gray-500 dark:text-gray-400"
    >
      <i class="pi pi-clock text-4xl mb-4 opacity-50" />
      <p class="text-lg">
        No activity history yet
      </p>
    </div>

    <div
      v-else
      class="space-y-6"
    >
      <div
        v-for="(activity, index) in activities"
        :key="activity.id"
        class="relative"
      >
        <!-- Timeline Connector -->
        <div
          v-if="index < activities.length - 1"
          class="absolute left-6 top-14 bottom-0 w-0.5 bg-gray-300 dark:bg-gray-600 -mb-6"
        />

        <!-- Activity Card -->
        <div class="flex gap-4">
          <!-- Timeline Icon -->
          <div class="flex-shrink-0 relative z-10">
            <div
              :class="getActivityIconClass(activity.event_type)"
              class="w-12 h-12 rounded-full flex items-center justify-center shadow-lg"
            >
              <i
                :class="getActivityIcon(activity.event_type)"
                class="text-xl"
              />
            </div>
          </div>

          <!-- Activity Content -->
          <div class="flex-1 bg-white dark:bg-slate-800 rounded-lg shadow-md p-6 border border-gray-200 dark:border-gray-700">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
              <div class="flex-1">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-50 mb-1">
                  {{ activity.description }}
                </h3>
                <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
                  <div
                    v-if="activity.causer"
                    class="flex items-center gap-2"
                  >
                    <i class="pi pi-user text-xs" />
                    <span class="font-medium">{{ activity.causer.name }}</span>
                  </div>
                  <div
                    v-else
                    class="flex items-center gap-2"
                  >
                    <i class="pi pi-cog text-xs" />
                    <span class="font-medium italic">System</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <i class="pi pi-clock text-xs" />
                    <span :title="activity.created_at">{{ activity.created_at_human }}</span>
                  </div>
                </div>
              </div>
              
              <!-- Event Type Badge -->
              <div
                v-if="activity.event_type"
                :class="getEventTypeBadgeClass(activity.event_type)"
                class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide"
              >
                {{ activity.event_type }}
              </div>
            </div>

            <!-- Changes Display -->
            <div
              v-if="activity.changes && activity.changes.length > 0"
              class="mt-4 space-y-3"
            >
              <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Changes:
              </div>
              
              <div
                v-for="(change, changeIndex) in activity.changes"
                :key="changeIndex"
                class="bg-gray-50 dark:bg-slate-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600"
              >
                <div class="flex items-center gap-2 mb-2">
                  <i class="pi pi-pencil text-blue-500 text-xs" />
                  <span class="font-semibold text-gray-900 dark:text-gray-50">
                    {{ change.field }}
                  </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                  <!-- Old Value -->
                  <div class="space-y-1">
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                      Previous
                    </div>
                    <div
                      class="p-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-sm"
                      :class="{ 'whitespace-pre-line': isMultiLine(change.old) }"
                    >
                      <span
                        v-if="change.old"
                        class="text-red-700 dark:text-red-300"
                        :class="{ 'font-mono': !isMultiLine(change.old) }"
                      >
                        {{ change.old }}
                      </span>
                      <span
                        v-else
                        class="text-gray-400 dark:text-gray-500 italic"
                      >
                        (empty)
                      </span>
                    </div>
                  </div>
                  
                  <!-- New Value -->
                  <div class="space-y-1">
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                      Current
                    </div>
                    <div
                      class="p-2 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded text-sm"
                      :class="{ 'whitespace-pre-line': isMultiLine(change.new) }"
                    >
                      <span
                        v-if="change.new"
                        class="text-green-700 dark:text-green-300"
                        :class="{ 'font-mono': !isMultiLine(change.new) }"
                      >
                        {{ change.new }}
                      </span>
                      <span
                        v-else
                        class="text-gray-400 dark:text-gray-500 italic"
                      >
                        (empty)
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
    activities: {
        type: Array,
        required: true,
        default: () => [],
    },
});

// Helper functions for styling
const getActivityIconClass = (eventType) => {
    switch (eventType) {
        case 'created':
            return 'bg-green-500 text-white';
        case 'updated':
            return 'bg-blue-500 text-white';
        case 'deleted':
            return 'bg-red-500 text-white';
        default:
            return 'bg-gray-500 text-white';
    }
};

const getActivityIcon = (eventType) => {
    switch (eventType) {
        case 'created':
            return 'pi pi-plus';
        case 'updated':
            return 'pi pi-pencil';
        case 'deleted':
            return 'pi pi-trash';
        default:
            return 'pi pi-circle';
    }
};

const getEventTypeBadgeClass = (eventType) => {
    switch (eventType) {
        case 'created':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'updated':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 'deleted':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
    }
};

// Helper to check if value should be displayed as multi-line
const isMultiLine = (value) => {
    return value && typeof value === 'string' && (value.includes(' | ') || value.includes('\n'));
};
</script>

<style scoped>
.activity-timeline {
    position: relative;
}
</style>
