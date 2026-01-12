<template>
  <div
    v-if="results"
    class="mt-6 space-y-4"
  >
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <StatCard
        label="Band's Cut"
        :value="moneyFormat(results.band_cut)"
        value-color-class="text-blue-600 dark:text-blue-400"
      />
      <StatCard
        label="Total Member Payout"
        :value="moneyFormat(results.total_member_payout)"
        value-color-class="text-green-600 dark:text-green-400"
      />
      <StatCard
        label="Per Member"
        :value="results.member_payouts.length > 0 ? moneyFormat(results.member_payouts[0].amount) : '$0.00'"
        value-color-class="text-purple-600 dark:text-purple-400"
      />
    </div>

    <!-- Payment Group Breakdown -->
    <div
      v-if="results.payment_group_payouts && results.payment_group_payouts.length > 0"
      class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow"
    >
      <SectionHeader
        title="Payment Group Breakdown (Sequential)"
        icon="pi-users"
        class="mb-3 font-semibold text-gray-800 dark:text-gray-100"
      />
      <div class="space-y-3">
        <div
          v-for="(groupPayout, index) in results.payment_group_payouts"
          :key="groupPayout.group_id"
          class="border dark:border-gray-700 rounded-lg p-3"
        >
          <div class="flex justify-between items-center mb-2 pb-2 border-b dark:border-gray-700">
            <div>
              <span class="font-semibold text-blue-600 dark:text-blue-400">
                {{ index + 1 }}. {{ groupPayout.group_name }}
              </span>
              <span class="text-xs text-gray-500 ml-2">
                ({{ groupPayout.member_count }} members)
              </span>
            </div>
            <span class="text-sm font-bold">
              {{ moneyFormat(groupPayout.total) }}
            </span>
          </div>
          <div class="space-y-1 ml-4">
            <div
              v-for="(memberPayout, idx) in groupPayout.payouts"
              :key="idx"
              class="flex justify-between items-center text-sm p-1"
            >
              <span class="text-gray-600 dark:text-gray-400">
                {{ memberPayout.user_name }}
                <span v-if="memberPayout.role" class="text-xs text-gray-500 ml-1">- {{ memberPayout.role }}</span>
                <span class="text-xs text-gray-500 ml-1">({{ memberPayout.payout_type }})</span>
              </span>
              <span class="font-medium">{{ moneyFormat(memberPayout.amount) }}</span>
            </div>
          </div>
        </div>
        <InfoAlert
          v-if="results.remaining > 0"
          variant="warning"
        >
          <div class="flex justify-between items-center">
            <span>Remaining (unallocated)</span>
            <span class="font-bold text-yellow-700 dark:text-yellow-400">
              {{ moneyFormat(results.remaining) }}
            </span>
          </div>
        </InfoAlert>
      </div>
    </div>

    <!-- Member Breakdown -->
    <div
      v-else
      class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow"
    >
      <SectionHeader
        title="Member Breakdown"
        class="mb-3 font-semibold text-gray-800 dark:text-gray-100"
      />
      <div class="space-y-2 max-h-64 overflow-y-auto">
        <div
          v-for="(payout, index) in results.member_payouts"
          :key="index"
          class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-700 rounded"
        >
          <span class="text-sm">
            <span
              :class="{
                'text-blue-600 dark:text-blue-400': payout.type === 'owner',
                'text-green-600 dark:text-green-400': payout.type === 'member',
                'text-orange-600 dark:text-orange-400': payout.type === 'production'
              }"
              class="font-medium"
            >
              {{ payout.type === 'owner' ? 'Owner' : payout.type === 'member' ? 'Member' : 'Production' }}
            </span>
            {{ payout.name || (index + 1) }}
            <span v-if="payout.role" class="text-xs text-gray-500 ml-1">- {{ payout.role }}</span>
          </span>
          <span class="font-semibold">{{ moneyFormat(payout.amount) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import StatCard from '@/Components/StatCard.vue'
import SectionHeader from '@/Components/SectionHeader.vue'
import InfoAlert from '@/Components/InfoAlert.vue'

defineProps({
  results: {
    type: Object,
    default: null
  }
})

const moneyFormat = (number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(number)
}
</script>
