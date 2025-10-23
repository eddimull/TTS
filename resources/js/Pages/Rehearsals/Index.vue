<template>
  <BreezeAuthenticatedLayout>
    <Container class="dark:bg-slate-600 md:container md:mx-auto">
      <div class="py-12">
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
          <div class="componentPanel overflow-auto shadow-sm sm:rounded-lg">
            <div class="p-6">
              <!-- Multi-Band View -->
              <div v-if="!band && bands && bands.length > 0">
                <h2 class="text-3xl font-bold mb-6">
                  Rehearsal Schedules
                </h2>
                
                <div
                  v-for="b in bands"
                  :key="b.id"
                  class="mb-8 pb-8 border-b border-gray-200 dark:border-gray-600 last:border-b-0"
                >
                  <div class="flex justify-between items-center mb-4">
                    <h3 class="text-2xl font-semibold">
                      {{ b.name }}
                    </h3>
                    <Link
                      v-if="b.canWrite"
                      :href="route('rehearsal-schedules.create', { band: b.id })"
                      class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                    >
                      Create Schedule
                    </Link>
                  </div>

                  <div
                    v-if="!b.rehearsal_schedules || b.rehearsal_schedules.length === 0"
                    class="text-gray-500 dark:text-gray-300 text-center py-8 bg-gray-50 dark:bg-gray-700 rounded-lg"
                  >
                    No rehearsal schedules for this band yet.
                  </div>

                  <div
                    v-else
                    class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
                  >
                    <div
                      v-for="schedule in b.rehearsal_schedules"
                      :key="schedule.id"
                      class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow"
                    >
                      <div class="flex justify-between items-start mb-4">
                        <h4 class="text-xl font-semibold">
                          {{ schedule.name }}
                        </h4>
                        <span
                          v-if="schedule.active"
                          class="px-2 py-1 bg-green-500 text-white text-xs rounded-full"
                        >
                          Active
                        </span>
                        <span
                          v-else
                          class="px-2 py-1 bg-gray-500 text-white text-xs rounded-full"
                        >
                          Inactive
                        </span>
                      </div>

                      <p
                        v-if="schedule.description"
                        class="text-gray-600 dark:text-gray-300 mb-4 text-sm"
                      >
                        {{ schedule.description }}
                      </p>

                      <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                        <strong>Frequency:</strong> {{ schedule.frequency }}
                      </div>

                      <div class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        <strong>Rehearsals:</strong> {{ schedule.rehearsals_count || 0 }}
                      </div>

                      <div class="flex gap-2">
                        <Link
                          :href="route('rehearsal-schedules.show', { band: b.id, rehearsal_schedule: schedule.id })"
                          class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm flex-1 text-center"
                        >
                          View Details
                        </Link>
                        <Link
                          v-if="b.canWrite"
                          :href="route('rehearsal-schedules.edit', { band: b.id, rehearsal_schedule: schedule.id })"
                          class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-sm"
                        >
                          Edit
                        </Link>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Single Band View -->
              <div v-else-if="band">
                <div class="flex justify-between items-center mb-6">
                  <h2 class="text-2xl font-bold">
                    Rehearsal Schedules - {{ band.name }}
                  </h2>
                  <Link
                    v-if="canWrite"
                    :href="route('rehearsal-schedules.create', { band: band.id })"
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                  >
                    Create Rehearsal Schedule
                  </Link>
                </div>

                <div
                  v-if="schedules.length === 0"
                  class="text-gray-500 dark:text-gray-300 text-center py-8"
                >
                  No rehearsal schedules found. Create one to get started!
                </div>

                <div
                  v-else
                  class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
                >
                  <div
                    v-for="schedule in schedules"
                    :key="schedule.id"
                    class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow"
                  >
                    <div class="flex justify-between items-start mb-4">
                      <h3 class="text-xl font-semibold">
                        {{ schedule.name }}
                      </h3>
                      <span
                        v-if="schedule.active"
                        class="px-2 py-1 bg-green-500 text-white text-xs rounded-full"
                      >
                        Active
                      </span>
                      <span
                        v-else
                        class="px-2 py-1 bg-gray-500 text-white text-xs rounded-full"
                      >
                        Inactive
                      </span>
                    </div>

                    <p
                      v-if="schedule.description"
                      class="text-gray-600 dark:text-gray-300 mb-4"
                    >
                      {{ schedule.description }}
                    </p>

                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                      <strong>Frequency:</strong> {{ schedule.frequency }}
                    </div>

                    <div
                      v-if="schedule.location_name"
                      class="text-sm text-gray-500 dark:text-gray-400 mb-4"
                    >
                      <strong>Location:</strong> {{ schedule.location_name }}
                    </div>

                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                      <strong>Rehearsals:</strong> {{ schedule.rehearsals_count || 0 }}
                    </div>

                    <div class="flex gap-2">
                      <Link
                        :href="route('rehearsal-schedules.show', { band: band.id, rehearsal_schedule: schedule.id })"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm flex-1 text-center"
                      >
                        View Details
                      </Link>
                      <Link
                        v-if="canWrite"
                        :href="route('rehearsal-schedules.edit', { band: band.id, rehearsal_schedule: schedule.id })"
                        class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-sm"
                      >
                        Edit
                      </Link>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Container>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Container from '@/Components/Container.vue';

const props = defineProps({
    band: {
        type: Object,
        default: null,
    },
    bands: {
        type: Array,
        default: () => [],
    },
    schedules: {
        type: Array,
        default: () => [],
    },
    canWrite: {
        type: Boolean,
        default: false,
    },
});
</script>
