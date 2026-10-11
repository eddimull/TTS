<template>
  <div class="space-y-4">
    <!-- Headline -->
    <div v-if="result.estimate !== null">
      <div class="text-sm text-gray-500 dark:text-gray-400">
        Suggested price
      </div>
      <div
        class="font-bold text-gray-900 dark:text-gray-50"
        :class="compact ? 'text-2xl' : 'text-4xl'"
        data-test="estimate-amount"
      >
        {{ formatMoney(result.estimate) }}
      </div>
      <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
        <span>Range {{ formatMoney(result.low) }} – {{ formatMoney(result.high) }}</span>
        <span
          class="px-2 py-0.5 rounded-full text-xs font-medium capitalize"
          :class="confidenceClass"
        >{{ result.confidence }} confidence</span>
      </div>
      <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        {{ sourceLabel }}
      </div>
    </div>

    <!-- Warnings -->
    <ul
      v-if="result.warnings?.length"
      class="space-y-1"
    >
      <li
        v-for="warning in result.warnings"
        :key="warning.code"
        class="text-sm p-2 rounded border text-yellow-700 dark:text-yellow-300 bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800"
      >
        <i class="pi pi-exclamation-triangle mr-1" />
        {{ warning.message }}
      </li>
    </ul>

    <!-- Breakdown -->
    <div v-if="result.breakdown?.length">
      <h4
        v-if="!compact"
        class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2"
      >
        Breakdown
      </h4>
      <table class="w-full text-sm">
        <tbody>
          <tr
            v-for="line in result.breakdown"
            :key="line.key"
            class="border-b last:border-b-0 border-gray-100 dark:border-gray-700"
          >
            <td class="py-1 pr-2 text-gray-700 dark:text-gray-200">
              {{ line.label }}
              <span
                v-if="line.type === 'multiplier'"
                class="text-xs text-gray-400"
              >×{{ line.factor }}</span>
            </td>
            <td class="py-1 text-right tabular-nums text-gray-900 dark:text-gray-50 whitespace-nowrap">
              {{ line.type === 'base' ? formatMoney(line.amount) : formatSignedMoney(line.amount) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Comparables -->
    <div v-if="!compact && result.comparables?.length">
      <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
        Similar past bookings
      </h4>
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500 dark:text-gray-400">
            <th class="py-1 pr-2 font-medium">
              Date
            </th>
            <th class="py-1 pr-2 font-medium">
              Booking
            </th>
            <th class="py-1 pr-2 font-medium hidden sm:table-cell">
              Venue
            </th>
            <th class="py-1 text-right font-medium">
              Price
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="comp in result.comparables"
            :key="comp.booking_id"
            class="border-t border-gray-100 dark:border-gray-700"
          >
            <td class="py-1 pr-2 whitespace-nowrap text-gray-700 dark:text-gray-200">
              {{ formatDate(comp.date) }}
            </td>
            <td class="py-1 pr-2">
              <a
                :href="`/bands/${bandId}/booking/${comp.booking_id}`"
                class="text-indigo-600 dark:text-indigo-300 hover:underline"
              >{{ comp.name }}</a>
            </td>
            <td class="py-1 pr-2 hidden sm:table-cell text-gray-600 dark:text-gray-300">
              {{ comp.venue_name }}
            </td>
            <td class="py-1 text-right tabular-nums text-gray-900 dark:text-gray-50">
              {{ formatMoney(comp.price) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { DateTime } from 'luxon';
import { formatMoney, formatSignedMoney } from './usePriceEstimate';

const props = defineProps({
    result: {
        type: Object,
        required: true,
    },
    bandId: {
        type: [Number, String],
        required: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const confidenceClass = computed(() => ({
    high: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    medium: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    low: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200',
}[props.result.confidence] ?? 'bg-gray-100 text-gray-700'));

const sourceLabel = computed(() => {
    switch (props.result.baseline_source) {
        case 'manual':
            return 'Based on your manual base rate';
        case 'history':
            return `Based on ${props.result.sample_size} past bookings of this type`;
        case 'band_average':
            return `Based on your band-wide average (${props.result.sample_size} bookings)`;
        default:
            return '';
    }
});

function formatDate(date) {
    return DateTime.fromISO(date).toFormat('LLL d, yyyy');
}
</script>
