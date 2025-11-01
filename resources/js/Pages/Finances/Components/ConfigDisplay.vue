<template>
  <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Configuration Name
        </p>
        <p class="font-medium">
          {{ config.name }}
        </p>
      </div>
      <div>
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Band's Cut
        </p>
        <p class="font-medium">
          <span v-if="config.band_cut_type === 'percentage'">
            {{ config.band_cut_value }}%
          </span>
          <span v-else-if="config.band_cut_type === 'fixed'">
            {{ moneyFormat(config.band_cut_value) }}
          </span>
          <span v-else-if="config.band_cut_type === 'tiered'">
            Tiered ({{ config.band_cut_tier_config?.length || 0 }} tiers)
          </span>
          <span v-else>
            None
          </span>
          <span class="text-gray-500 text-sm ml-1">({{ config.band_cut_type }})</span>
        </p>
      </div>
      <div v-if="config.use_payment_groups">
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Payment Mode
        </p>
        <p class="font-medium text-blue-600 dark:text-blue-400">
          <i class="pi pi-users mr-1" />
          Using Payment Groups
        </p>
      </div>
      <div v-else>
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Member Payout Type
        </p>
        <p class="font-medium capitalize">
          {{ config.member_payout_type.replace('_', ' ') }}
        </p>
      </div>
      <div v-if="!config.use_payment_groups">
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Member Counts
        </p>
        <p class="font-medium">
          {{ ownerCount }} Owners, {{ memberCount }} Members
          <span v-if="config.production_member_count > 0">
            , {{ config.production_member_count }} Production
          </span>
        </p>
      </div>
      <div v-else>
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Configured Groups
        </p>
        <p class="font-medium">
          {{ config.payment_group_config?.length || 0 }} Groups
        </p>
      </div>
    </div>
    <div
      v-if="config.notes"
      class="mt-4 pt-4 border-t dark:border-gray-700"
    >
      <p class="text-sm text-gray-600 dark:text-gray-400">
        Notes
      </p>
      <p class="text-sm mt-1">
        {{ config.notes }}
      </p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  config: {
    type: Object,
    required: true
  },
  ownerCount: {
    type: Number,
    default: 0
  },
  memberCount: {
    type: Number,
    default: 0
  }
})

const moneyFormat = (number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(number)
}
</script>
