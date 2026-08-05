<template>
  <BreezeAuthenticatedLayout>
    <Container class="dark:bg-slate-600 md:container md:mx-auto">
      <div class="py-12">
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
          <div class="componentPanel overflow-auto shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h2 class="text-3xl font-bold mb-6">
                Lodging
              </h2>

              <div
                v-if="!bands || bands.length === 0"
                class="text-gray-500 dark:text-gray-300 text-center py-8"
              >
                No bands available.
              </div>

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
                    :href="route('bands.lodgings.create', { band: b.id })"
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                  >
                    Create Lodging
                  </Link>
                </div>

                <div
                  v-if="!b.lodgings || b.lodgings.length === 0"
                  class="text-gray-500 dark:text-gray-300 text-center py-8 bg-gray-50 dark:bg-gray-700 rounded-lg"
                >
                  No lodging for this band yet.
                </div>

                <div
                  v-else
                  class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
                >
                  <Link
                    v-for="l in b.lodgings"
                    :key="l.id"
                    :href="route('lodgings.show', l.id)"
                    class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4 hover:shadow-lg transition-shadow"
                  >
                    <h4 class="text-xl font-semibold mb-2">
                      {{ l.name }}
                    </h4>

                    <p
                      v-if="l.address"
                      class="text-sm text-gray-600 dark:text-gray-300 mb-2 truncate"
                    >
                      {{ l.address }}
                    </p>

                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                      {{ formatRange(l.check_in_at, l.check_out_at) }}
                    </div>

                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                      <strong>Rooms:</strong> {{ l.room_count || 0 }}
                    </div>

                    <div class="flex flex-wrap gap-2">
                      <span
                        v-if="l.booking_id"
                        class="px-2 py-1 bg-blue-500 text-white text-xs rounded-full"
                      >
                        Linked booking
                      </span>
                      <span
                        v-if="l.event_id"
                        class="px-2 py-1 bg-purple-500 text-white text-xs rounded-full"
                      >
                        Linked event
                      </span>
                      <span
                        v-if="l.attachment_count"
                        class="px-2 py-1 bg-gray-500 text-white text-xs rounded-full"
                      >
                        {{ l.attachment_count }} file{{ l.attachment_count === 1 ? '' : 's' }}
                      </span>
                    </div>
                  </Link>
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
import { DateTime } from 'luxon';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Container from '@/Components/Container.vue';

defineProps({
  bands: {
    type: Array,
    default: () => [],
  },
});

const formatRange = (checkIn, checkOut) => {
  const inFmt = checkIn ? DateTime.fromSQL(checkIn).toFormat('EEE, MMM d h:mm a') : null;
  const outFmt = checkOut ? DateTime.fromSQL(checkOut).toFormat('EEE, MMM d h:mm a') : null;
  if (inFmt && outFmt) return `${inFmt} – ${outFmt}`;
  if (inFmt) return inFmt;
  if (outFmt) return outFmt;
  return 'No dates set';
};
</script>
