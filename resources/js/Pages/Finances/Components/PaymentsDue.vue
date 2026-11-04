<template>
  <div class="py-6 px-4 flex flex-col gap-8">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-50">
      Payments Due
    </h1>

    <p
      v-if="bands.length === 0"
      class="text-gray-600 dark:text-gray-300"
    >
      Only band owners can see payments due.
    </p>

    <section
      v-for="band in bands"
      :key="band.id"
      class="flex flex-col gap-4"
      :data-testid="`band-${band.id}`"
    >
      <h2
        v-if="bands.length > 1"
        class="text-lg font-semibold text-gray-900 dark:text-gray-50"
      >
        {{ band.name }}
      </h2>

      <p
        v-if="isEmpty(band)"
        class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 text-center text-gray-600 dark:text-gray-300"
      >
        No payments due. Every contracted booking is paid in full.
      </p>

      <template v-else>
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
          <div
            v-for="tile in tiles(band)"
            :key="tile.key"
            class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4"
          >
            <div
              class="text-sm font-semibold"
              :class="tile.key === 'overdue' && tile.count > 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-600 dark:text-gray-300'"
            >
              {{ tile.title }}
            </div>
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-50">
              {{ formatMoney(tile.total) }}
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-300">
              {{ countLabel(tile.count) }}
            </div>
          </div>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-x-auto">
          <table class="w-full min-w-[640px] text-sm text-left text-gray-900 dark:text-gray-100">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300">
                <th class="px-4 py-3 font-semibold">Booking</th>
                <th class="px-4 py-3 font-semibold">Payment</th>
                <th class="px-4 py-3 font-semibold">Event</th>
                <th class="px-4 py-3 font-semibold">Due</th>
                <th class="px-4 py-3 font-semibold text-right">Amount</th>
              </tr>
            </thead>
            <tbody
              v-for="group in visibleGroups(band)"
              :key="group.key"
              :data-testid="`group-${group.key}`"
            >
              <tr class="bg-gray-50 dark:bg-gray-900">
                <th
                  colspan="4"
                  scope="colgroup"
                  class="px-4 py-2 text-xs font-bold tracking-wide"
                  :class="group.key === 'overdue' ? 'text-red-700 dark:text-red-400' : 'text-gray-700 dark:text-gray-200'"
                >
                  <button
                    v-if="isCollapsible(group.key)"
                    type="button"
                    class="uppercase underline"
                    :aria-expanded="isOpen(band, group.key)"
                    @click="toggle(band, group.key)"
                  >
                    {{ group.title }} ({{ countLabel(group.rows.length) }}{{ group.key === 'older' ? ', shows over 90 days ago' : '' }})
                  </button>
                  <span
                    v-else
                    class="uppercase"
                  >{{ group.title }}</span>
                </th>
                <td class="px-4 py-2 text-right font-semibold">
                  {{ formatMoney(band.summary[group.key].total) }}
                </td>
              </tr>
              <template v-if="!isCollapsible(group.key) || isOpen(band, group.key)">
                <tr
                  v-for="row in group.rows"
                  :key="`${row.booking_id}-${row.type}`"
                  class="border-b border-gray-100 dark:border-gray-700"
                >
                  <td class="px-4 py-3 font-medium">
                    <a
                      :href="route('Booking Finances', { band: row.band_id, booking: row.booking_id })"
                      class="text-blue-700 dark:text-blue-300 hover:underline"
                    >{{ row.booking_name }}</a>
                  </td>
                  <td class="px-4 py-3">
                    {{ paymentLabel(row) }}
                  </td>
                  <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                    {{ formatDueDate(row.event_date) }}
                  </td>
                  <td
                    class="px-4 py-3"
                    :class="whenClass(row)"
                  >
                    {{ whenLabel(row) }}
                  </td>
                  <td class="px-4 py-3 text-right font-semibold">
                    {{ formatMoney(row.amount) }}
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </template>
    </section>
  </div>
</template>

<script setup>
import { reactive } from 'vue';
import { depositLabel, formatDueDate, formatMoney } from '@/composables/usePaymentDueText';

const props = defineProps({
  bands: {
    type: Array,
    required: true,
  },
});

const GROUP_TITLES = {
  overdue: 'Overdue',
  next_30: 'Next 30 days',
  later: 'Later',
  older: 'Older',
};

const isCollapsible = (group) => group === 'later' || group === 'older';

const open = reactive({});
const openKey = (band, group) => `${band.id}:${group}`;
const isOpen = (band, group) => Boolean(open[openKey(band, group)]);
const toggle = (band, group) => {
  open[openKey(band, group)] = !isOpen(band, group);
};

const isEmpty = (band) => Object.values(band.groups).every((rows) => rows.length === 0);

const countLabel = (count) => `${count} ${count === 1 ? 'payment' : 'payments'}`;

const tiles = (band) => ['overdue', 'next_30', 'later'].map((key) => ({
  key,
  title: GROUP_TITLES[key],
  ...band.summary[key],
}));

const visibleGroups = (band) => Object.keys(GROUP_TITLES)
  .filter((key) => band.groups[key].length > 0)
  .map((key) => ({ key, title: GROUP_TITLES[key], rows: band.groups[key] }));

const paymentLabel = (row) => {
  if (row.type !== 'deposit') return 'Balance';
  const label = depositLabel(row.deposit_type, row.deposit_value);
  return parseFloat(row.amount_paid) > 0
    ? `${label}, ${formatMoney(row.amount_paid)} of ${formatMoney(row.deposit_amount)} paid`
    : label;
};

const whenLabel = (row) => {
  if (row.status === 'overdue') {
    const days = `${row.days_late} ${row.days_late === 1 ? 'day' : 'days'} late`;
    return `${formatDueDate(row.overdue_since)} · ${days}`;
  }
  return row.status === 'due_now' ? 'Due now' : formatDueDate(row.due_date);
};

const whenClass = (row) => {
  if (row.status === 'overdue') return 'font-semibold text-red-700 dark:text-red-400';
  if (row.status === 'due_now') return 'font-semibold text-amber-800 dark:text-amber-300';
  return 'text-gray-600 dark:text-gray-300';
};
</script>
