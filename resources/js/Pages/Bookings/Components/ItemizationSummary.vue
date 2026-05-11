<template>
  <div
    v-if="visible"
    class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4"
  >
    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-50 mb-3">
      Itemization
    </h2>
    <p class="text-sm text-gray-700 dark:text-gray-300">
      <span class="font-medium">Total: ${{ formattedTotal }}</span>
      <span class="mx-1">=</span>
      <template
        v-for="(line, idx) in lines"
        :key="line.id"
      >
        <span>{{ line.label }} ${{ line.formattedAmount }}</span>
        <span
          v-if="idx < lines.length - 1 || unallocatedAmount !== 0"
          class="mx-1"
        >+</span>
      </template>
      <span v-if="unallocatedAmount !== 0">
        Other / Unallocated ${{ formattedUnallocated }}
      </span>
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    booking: {
        type: Object,
        required: true,
    },
});

const events = computed(() => props.booking.events ?? []);

const anyPriced = computed(() =>
    events.value.some((e) => e.price !== null && e.price !== undefined)
);

const visible = computed(() => props.booking.is_multi_event === true && anyPriced.value);

const total = computed(() => parseFloat(props.booking.price) || 0);

const allocatedTotal = computed(() =>
    events.value.reduce((sum, e) => sum + (parseFloat(e.price) || 0), 0)
);

const unallocatedAmount = computed(() => total.value - allocatedTotal.value);

const lines = computed(() =>
    [...events.value]
        .sort((a, b) => new Date(a.date) - new Date(b.date))
        .map((e) => ({
            id: e.id,
            label: shortLabel(e),
            formattedAmount: formatMoney(parseFloat(e.price) || 0),
        }))
);

const formattedTotal = computed(() => formatMoney(total.value));
const formattedUnallocated = computed(() => formatMoney(unallocatedAmount.value));

function shortLabel(event) {
    if (!event.date) return event.title ?? 'Event';
    const d = new Date(event.date);
    return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
}

function formatMoney(amount) {
    return amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
