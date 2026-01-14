<template>
  <div v-if="results" class="bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 p-4">
    <div class="flex items-center justify-between mb-3">
      <div class="flex items-center gap-2">
        <i class="pi pi-chart-line text-blue-500 text-lg" />
        <h3 class="font-semibold text-gray-900 dark:text-white">Calculation Preview</h3>
      </div>
      <Button
        icon="pi pi-times"
        text
        rounded
        size="small"
        @click="emit('close')"
        v-tooltip.left="'Close preview'"
      />
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-4 gap-3 mb-4">
      <!-- Total Amount -->
      <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
        <div class="text-xs text-blue-600 dark:text-blue-400 font-medium mb-1">Total Income</div>
        <div class="text-xl font-bold text-blue-700 dark:text-blue-300">
          {{ moneyFormat(results.total_amount) }}
        </div>
      </div>

      <!-- Band Cut -->
      <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-3">
        <div class="text-xs text-purple-600 dark:text-purple-400 font-medium mb-1">Band Cut</div>
        <div class="text-xl font-bold text-purple-700 dark:text-purple-300">
          {{ moneyFormat(results.band_cut) }}
        </div>
      </div>

      <!-- Distributable -->
      <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
        <div class="text-xs text-green-600 dark:text-green-400 font-medium mb-1">Distributable</div>
        <div class="text-xl font-bold text-green-700 dark:text-green-300">
          {{ moneyFormat(results.distributable_amount) }}
        </div>
      </div>

      <!-- Remaining -->
      <div class="rounded-lg p-3"
           :class="results.remaining > 0 ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-gray-50 dark:bg-gray-700'">
        <div class="text-xs font-medium mb-1"
             :class="results.remaining > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-600 dark:text-gray-400'">
          Remaining
        </div>
        <div class="text-xl font-bold"
             :class="results.remaining > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-gray-700 dark:text-gray-300'">
          {{ moneyFormat(results.remaining) }}
        </div>
      </div>
    </div>

    <!-- Payment Groups Breakdown -->
    <div v-if="results.payment_group_payouts && results.payment_group_payouts.length > 0" class="mb-4">
      <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Payment Groups</h4>
      <div class="space-y-2">
        <div
          v-for="(group, index) in results.payment_group_payouts"
          :key="group.group_id || index"
          class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded"
        >
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-600 text-xs font-bold">
              {{ index + 1 }}
            </span>
            <div>
              <div class="font-medium text-gray-800 dark:text-gray-200">{{ group.group_name }}</div>
              <div class="text-xs text-gray-500 dark:text-gray-400">{{ group.member_count }} members</div>
            </div>
          </div>
          <div class="text-right">
            <div class="font-bold text-green-600">{{ moneyFormat(group.total) }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
              {{ ((group.total / results.distributable_amount) * 100).toFixed(1) }}%
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Member Payouts Summary -->
    <div v-if="results.member_payouts && results.member_payouts.length > 0" class="mb-4">
      <div class="flex items-center justify-between mb-2">
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
          Member Payouts ({{ results.member_payouts.length }})
        </h4>
        <Button
          :label="showAllMembers ? 'Show Less' : 'Show All'"
          text
          size="small"
          @click="showAllMembers = !showAllMembers"
        />
      </div>
      <div class="space-y-1 max-h-48 overflow-y-auto">
        <div
          v-for="(member, index) in displayedMembers"
          :key="`${member.user_id || member.name}-${index}`"
          class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded text-sm"
        >
          <div class="flex-1 truncate">
            <span class="font-medium text-gray-800 dark:text-gray-200">
              {{ member.user_name || member.name }}
            </span>
            <span v-if="member.member_type" class="ml-2 text-xs text-gray-500 dark:text-gray-400">
              ({{ member.member_type }})
            </span>
          </div>
          <div class="font-bold text-green-600">{{ moneyFormat(member.amount) }}</div>
        </div>
      </div>
    </div>

    <!-- Total Payout -->
    <div class="border-t dark:border-gray-700 pt-3">
      <div class="flex justify-between items-center">
        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Total Member Payout:</span>
        <span class="text-lg font-bold text-green-600">{{ moneyFormat(results.total_member_payout) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import Button from 'primevue/button'

const props = defineProps({
  results: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close'])

const showAllMembers = ref(false)

const displayedMembers = computed(() => {
  if (!props.results?.member_payouts) return []
  return showAllMembers.value
    ? props.results.member_payouts
    : props.results.member_payouts.slice(0, 5)
})

const moneyFormat = (num) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(num || 0)
}
</script>
