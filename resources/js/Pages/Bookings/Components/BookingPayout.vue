<template>
  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-50 mb-3 flex items-center">
      <i class="pi pi-wallet mr-2" />
      Estimated Payout
    </h2>

    <!-- Per-event itemization (multi-event only) -->
    <div
      v-if="booking.is_multi_event"
      class="mb-5 pb-4 border-b border-gray-200 dark:border-gray-700"
    >
      <div class="flex items-center justify-between mb-2">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
          Itemized by event
        </h3>
        <span
          v-if="itemizationDelta !== 0"
          :class="itemizationDelta > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400'"
          class="text-xs font-medium"
        >
          {{ itemizationDelta > 0 ? 'Unallocated' : 'Over-allocated' }}:
          ${{ formatMoney(Math.abs(itemizationDelta)) }}
        </span>
      </div>

      <div class="space-y-2">
        <div
          v-for="row in itemizationRows"
          :key="row.id"
          class="flex items-center gap-2 text-sm"
        >
          <span class="flex-1 text-gray-700 dark:text-gray-300 truncate">
            {{ row.label }}
          </span>
          <span class="text-gray-500 dark:text-gray-400">$</span>
          <input
            v-model="row.priceInput"
            type="number"
            step="0.01"
            min="0"
            class="w-24 rounded-md border-gray-300 dark:bg-slate-700 dark:text-gray-50 shadow-sm text-right text-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
            @change="saveRow(row)"
          >
          <span
            v-if="row.saving"
            class="text-xs text-gray-400"
          >
            <i class="pi pi-spin pi-spinner" />
          </span>
          <span
            v-else-if="row.error"
            :title="row.error"
            class="text-xs text-red-500"
          >
            <i class="pi pi-exclamation-circle" />
          </span>
          <span
            v-else-if="row.justSaved"
            class="text-xs text-green-600 dark:text-green-400"
          >
            <i class="pi pi-check" />
          </span>
        </div>
      </div>

      <div class="flex items-center justify-between mt-3 text-sm font-medium pt-2 border-t border-gray-100 dark:border-gray-800">
        <span class="text-gray-700 dark:text-gray-200">Itemized total</span>
        <span class="text-gray-900 dark:text-gray-50">${{ formatMoney(itemizedTotal) }}</span>
      </div>
      <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
        <span>Booking total</span>
        <span>${{ formatMoney(parseFloat(booking.price) || 0) }}</span>
      </div>
    </div>


    <!-- Loading/Error States -->
    <div
      v-if="!payoutConfig"
      class="text-center text-sm text-gray-500 dark:text-gray-400 py-4"
    >
      <i class="pi pi-info-circle mr-1" />
      No payout configuration set for this band
    </div>

    <!-- Payout Calculation Display -->
    <div
      v-else-if="payoutResult"
      class="space-y-4"
    >
      <!-- Summary Row -->
      <div class="grid grid-cols-3 gap-2 pb-3 border-b border-gray-200 dark:border-gray-700">
        <div class="text-center">
          <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">
            Total
          </div>
          <div class="text-lg font-bold text-gray-900 dark:text-gray-50">
            ${{ formatMoney(payoutResult.total_amount) }}
          </div>
        </div>
        <div class="text-center">
          <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">
            Band Cut
          </div>
          <div class="text-lg font-bold text-amber-600 dark:text-amber-400">
            ${{ formatMoney(payoutResult.band_cut) }}
          </div>
        </div>
        <div class="text-center">
          <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">
            Distributable
          </div>
          <div class="text-lg font-bold text-blue-600 dark:text-blue-400">
            ${{ formatMoney(payoutResult.distributable_amount) }}
          </div>
        </div>
      </div>

      <!-- Payment Groups Breakdown (if using groups) -->
      <div
        v-if="payoutConfig.use_payment_groups && payoutResult.payment_group_payouts?.length > 0"
        class="space-y-3"
      >
        <div class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center">
          <i class="pi pi-users mr-2" />
          Payment Groups
        </div>
        
        <div
          v-for="(group, index) in payoutResult.payment_group_payouts"
          :key="group.group_id"
          class="border-l-4 pl-3 py-2"
          :class="[
            index === 0 ? 'border-blue-500' : 
            index === 1 ? 'border-green-500' : 
            index === 2 ? 'border-orange-500' : 
            'border-purple-500'
          ]"
        >
          <div class="flex items-center justify-between mb-2">
            <div class="font-medium text-gray-900 dark:text-gray-50 text-sm">
              {{ group.group_name }}
              <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">
                ({{ group.member_count }} {{ group.member_count === 1 ? 'member' : 'members' }})
              </span>
            </div>
            <div class="font-bold text-green-600 dark:text-green-400">
              ${{ formatMoney(group.total) }}
            </div>
          </div>
          
          <!-- Individual Member Payouts -->
          <div
            v-if="group.payouts && group.payouts.length > 0"
            class="space-y-1 ml-2"
          >
            <div
              v-for="payout in group.payouts"
              :key="payout.user_id"
              class="flex items-center justify-between text-xs"
            >
              <span class="text-gray-600 dark:text-gray-400">
                {{ payout.user_name }}
                <span
                  v-if="payout.payout_type !== 'equal_split'"
                  class="text-gray-500 dark:text-gray-500 text-[10px] ml-1"
                >
                  ({{ formatPayoutType(payout.payout_type) }})
                </span>
              </span>
              <span class="font-medium text-gray-700 dark:text-gray-300">
                ${{ formatMoney(payout.amount) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Your Payout Highlight (if current user is in the payouts) -->
        <div
          v-if="currentUserPayout"
          class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3 mt-4"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <i class="pi pi-user text-green-600 dark:text-green-400 mr-2" />
              <span class="text-sm font-medium text-gray-900 dark:text-gray-50">
                Your Estimated Payout
              </span>
            </div>
            <div class="text-xl font-bold text-green-600 dark:text-green-400">
              ${{ formatMoney(currentUserPayout.amount) }}
            </div>
          </div>
          <div
            v-if="currentUserPayout.group_name"
            class="text-xs text-gray-600 dark:text-gray-400 mt-1"
          >
            From {{ currentUserPayout.group_name }} group
          </div>
        </div>
      </div>

      <!-- Legacy Member Payouts (if not using groups) -->
      <div
        v-else-if="payoutResult.member_payouts?.length > 0"
        class="space-y-2"
      >
        <div class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
          Member Payouts
        </div>
        
        <div
          v-for="(payout, index) in payoutResult.member_payouts"
          :key="index"
          class="flex items-center justify-between text-sm py-1 border-b border-gray-100 dark:border-gray-700 last:border-0"
        >
          <span class="text-gray-700 dark:text-gray-300">
            {{ payout.name }} - {{ payout.role }}
            <span
              v-if="payout.type"
              class="text-xs text-gray-500 dark:text-gray-500 ml-1"
            >
              ({{ payout.type }})
            </span>
          </span>
          <span class="font-medium text-gray-900 dark:text-gray-50">
            ${{ formatMoney(payout.amount) }}
          </span>
        </div>

        <!-- Your Payout Highlight -->
        <div
          v-if="currentUserPayout"
          class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3 mt-3"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <i class="pi pi-user text-green-600 dark:text-green-400 mr-2" />
              <span class="text-sm font-medium text-gray-900 dark:text-gray-50">
                Your Estimated Payout
              </span>
            </div>
            <div class="text-xl font-bold text-green-600 dark:text-green-400">
              ${{ formatMoney(currentUserPayout.amount) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Configuration Info -->
      <div class="text-xs text-gray-500 dark:text-gray-400 pt-2 border-t border-gray-200 dark:border-gray-700">
        <i class="pi pi-info-circle mr-1" />
        Using configuration: <span class="font-medium">{{ payoutConfig.name }}</span>
        <span
          v-if="payoutConfig.notes"
          class="block mt-1 text-[10px]"
        >
          {{ payoutConfig.notes }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { DateTime } from 'luxon'

const props = defineProps({
  booking: {
    type: Object,
    required: true
  },
  band: {
    type: Object,
    required: true
  },
  payoutConfig: {
    type: Object,
    default: null
  },
  payoutResult: {
    type: Object,
    default: null
  }
})

const page = usePage()

// Find current user's payout from the results
const currentUserPayout = computed(() => {
  if (!props.payoutResult || !page.props.auth?.user) return null
  
  const userId = page.props.auth.user.id
  
  // Check payment groups payouts first
  if (props.payoutResult.payment_group_payouts) {
    for (const group of props.payoutResult.payment_group_payouts) {
      if (group.payouts) {
        const userPayout = group.payouts.find(p => p.user_id === userId)
        if (userPayout) {
          return {
            amount: userPayout.amount,
            group_name: group.group_name,
            payout_type: userPayout.payout_type
          }
        }
      }
    }
  }
  
  // Check flat member payouts (legacy)
  if (props.payoutResult.member_payouts) {
    const userPayout = props.payoutResult.member_payouts.find(p => p.user_id === userId)
    if (userPayout) {
      return {
        amount: userPayout.amount,
        type: userPayout.type
      }
    }
  }
  
  return null
})

const formatMoney = (amount) => {
  if (amount === null || amount === undefined) return '0.00'
  return parseFloat(amount).toFixed(2)
}

const formatPayoutType = (type) => {
  const types = {
    'equal_split': 'Equal Split',
    'percentage': 'Percentage',
    'fixed': 'Fixed',
    'payment_group': 'Group'
  }
  return types[type] || type
}

// ── Per-event itemization ─────────────────────────────────────────────────────

function eventLabel(event) {
  if (event.date) {
    return DateTime.fromISO(event.date).toFormat('ccc M/d') + (event.title ? ` — ${event.title}` : '')
  }
  return event.title || `Event ${event.id}`
}

const itemizationRows = reactive(
  [...(props.booking.events ?? [])]
    .sort((a, b) => `${a.date ?? ''}-${a.id}`.localeCompare(`${b.date ?? ''}-${b.id}`))
    .map((e) => ({
      id: e.id,
      eventRef: e,
      label: eventLabel(e),
      priceInput: e.price ?? '',
      saving: false,
      error: null,
      justSaved: false,
    }))
)

const itemizedTotal = computed(() =>
  itemizationRows.reduce((sum, r) => sum + (parseFloat(r.priceInput) || 0), 0)
)

const itemizationDelta = computed(() => (parseFloat(props.booking.price) || 0) - itemizedTotal.value)

function saveRow(row) {
  const value = row.priceInput === '' || row.priceInput === null ? null : Number(row.priceInput)
  const prevSnapshot = row.eventRef.price
  if (value === null && (prevSnapshot === null || prevSnapshot === undefined)) {
    return
  }
  if (value !== null && parseFloat(prevSnapshot) === value) {
    return
  }

  row.saving = true
  row.error = null
  row.justSaved = false

  router.put(
    route('Update Booking Event', [props.booking.band_id, props.booking.id, row.id]),
    {
      title:          row.eventRef.title,
      date:           row.eventRef.date,
      start_time:     row.eventRef.start_time || null,
      end_time:       row.eventRef.end_time   || null,
      venue_name:     row.eventRef.venue_name || null,
      venue_address:  row.eventRef.venue_address || null,
      price:          value,
      // omit additional_data — the form request treats it as 'sometimes'
      // so partial updates don't need to round-trip the full event config
      roster_id:      row.eventRef.roster_id ?? null,
      notes:          row.eventRef.notes ?? null,
      silent:         true,
    },
    {
      preserveScroll: true,
      preserveState:  true,
      onSuccess: () => {
        row.eventRef.price = value === null ? null : value.toFixed(2)
        row.saving = false
        row.justSaved = true
        setTimeout(() => { row.justSaved = false }, 2000)
      },
      onError: (errors) => {
        row.saving = false
        const first = Object.values(errors ?? {})[0]
        row.error = Array.isArray(first) ? first[0] : (first || 'Save failed')
      },
    },
  )
}
</script>
