<template>
  <Container class="p-4">
    <div class="space-y-4">
      <!-- Header -->
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4">
        <div class="flex justify-between items-start">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-50">
              Payout Breakdown
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
              {{ booking.name }} • {{ formatDate(booking.date) }}
            </p>
          </div>
          <div class="text-right">
            <div class="text-sm text-gray-500 dark:text-gray-400">
              Base Price
            </div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">
              ${{ formatPrice(booking.price) }}
            </div>
            <div
              v-if="totalAdjustments !== 0"
              class="text-sm mt-1"
              :class="totalAdjustments > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
            >
              {{ totalAdjustments > 0 ? '+' : '' }}${{ formatPrice(Math.abs(totalAdjustments)) }} adjustments
            </div>
          </div>
        </div>
      </div>

      <!-- Configuration Selector -->
      <div
        v-if="availableConfigs && availableConfigs.length > 0"
        class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3 flex-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
              Payout Configuration:
            </label>
            <Dropdown
              v-model="selectedConfigId"
              :options="availableConfigs"
              optionLabel="name"
              optionValue="id"
              placeholder="Select a configuration"
              class="w-full max-w-md"
              @change="handleConfigurationChange"
            >
              <template #value="slotProps">
                <div v-if="slotProps.value" class="flex items-center gap-2">
                  <span>{{ getConfigName(slotProps.value) }}</span>
                  <span
                    v-if="isActiveConfig(slotProps.value)"
                    class="text-xs px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded"
                  >
                    Active
                  </span>
                </div>
                <span v-else>{{ slotProps.placeholder }}</span>
              </template>
              <template #option="slotProps">
                <div class="flex items-center justify-between w-full">
                  <span>{{ slotProps.option.name }}</span>
                  <span
                    v-if="slotProps.option.is_active"
                    class="text-xs px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded"
                  >
                    Active
                  </span>
                </div>
              </template>
            </Dropdown>
          </div>
          <Link
            :href="route('finances.payoutFlow.edit', band.id)"
            class="inline-flex items-center px-3 py-2 text-sm text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-md transition-colors"
          >
            <i class="pi pi-cog mr-2" />
            Edit
          </Link>
        </div>
      </div>

      <!-- Adjustments Section -->
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-50 flex items-center">
            <i class="pi pi-calculator mr-2" />
            Payout Adjustments
          </h2>
          <Button
            label="Add Adjustment"
            icon="pi pi-plus"
            size="small"
            @click="openAdjustmentDialog"
          />
        </div>

        <div v-if="adjustments.length === 0">
          <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
            No adjustments have been added. Click "Add Adjustment" to account for extra expenses or member absences.
          </p>
        </div>

        <div
          v-else
          class="space-y-3"
        >
          <div
            v-for="adjustment in adjustments"
            :key="adjustment.id"
            class="flex items-start justify-between p-3 border border-gray-200 dark:border-gray-700 rounded-lg"
            :class="parseFloat(adjustment.amount) < 0 ? 'bg-red-50 dark:bg-red-900/10' : 'bg-green-50 dark:bg-green-900/10'"
          >
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <span
                  class="font-semibold"
                  :class="parseFloat(adjustment.amount) < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
                >
                  {{ parseFloat(adjustment.amount) > 0 ? '+' : '' }}${{ formatPrice(Math.abs(parseFloat(adjustment.amount))) }}
                </span>
                <span class="text-gray-900 dark:text-gray-50">
                  {{ adjustment.description }}
                </span>
              </div>
              <div
                v-if="adjustment.notes"
                class="text-sm text-gray-600 dark:text-gray-400 mt-1"
              >
                {{ adjustment.notes }}
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                Added by {{ adjustment.creator?.name }} on {{ formatDateTime(adjustment.created_at) }}
              </div>
            </div>
            <Button
              icon="pi pi-trash"
              severity="danger"
              text
              size="small"
              @click="deleteAdjustment(adjustment.id)"
            />
          </div>

          <div class="border-t border-gray-200 dark:border-gray-700 pt-3 mt-3">
            <div class="flex justify-between items-center text-lg font-semibold">
              <span class="text-gray-700 dark:text-gray-300">Adjusted Total:</span>
              <span class="text-green-600 dark:text-green-400">${{ formatPrice(adjustedTotal) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- No Configuration Message -->
      <div
        v-if="!payoutConfig"
        class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-6 text-center"
      >
        <i class="pi pi-exclamation-triangle text-yellow-600 dark:text-yellow-400 text-3xl mb-3" />
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-50 mb-2">
          No Payout Configuration Set
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
          This band doesn't have an active payout configuration. Set one up to see how payments will be distributed.
        </p>
        <Link
          :href="route('Payout Calculator')"
          class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium transition-colors"
        >
          <i class="pi pi-cog mr-2" />
          Configure Payouts
        </Link>
      </div>

      <!-- Payout Summary -->
      <div
        v-else-if="payoutResult"
        class="space-y-4"
      >
        <!-- Top-level Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
              Total Booking Amount
            </div>
            <div class="text-3xl font-bold text-gray-900 dark:text-gray-50">
              ${{ formatPrice(payoutResult.total_amount) }}
            </div>
          </div>
          
          <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
              Band Cut
            </div>
            <div class="text-3xl font-bold text-amber-600 dark:text-amber-400">
              ${{ formatPrice(payoutResult.band_cut) }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
              {{ getBandCutDescription() }}
            </div>
          </div>
          
          <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4">
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
              Distributable to Members
            </div>
            <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">
              ${{ formatPrice(payoutResult.distributable_amount) }}
            </div>
          </div>
        </div>

        <!-- Payment Groups Breakdown -->
        <div
          v-if="payoutConfig.use_payment_groups && payoutResult.payment_group_payouts?.length > 0"
          class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6"
        >
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-50 mb-4 flex items-center">
            <i class="pi pi-users mr-2" />
            Payment Groups Distribution
          </h2>
          
          <div class="space-y-6">
            <div
              v-for="(group, index) in payoutResult.payment_group_payouts"
              :key="group.group_id"
              class="border-l-4 pl-4 py-3"
              :class="[
                index === 0 ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/10' : 
                index === 1 ? 'border-green-500 bg-green-50 dark:bg-green-900/10' : 
                index === 2 ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/10' : 
                'border-purple-500 bg-purple-50 dark:bg-purple-900/10'
              ]"
            >
              <div class="flex items-center justify-between mb-3">
                <div>
                  <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-50">
                    {{ group.group_name }}
                  </h3>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ group.member_count }} {{ group.member_count === 1 ? 'member' : 'members' }}
                  </p>
                </div>
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                  ${{ formatPrice(group.total) }}
                </div>
              </div>
              
              <!-- Member Payouts Table -->
              <div
                v-if="group.payouts && group.payouts.length > 0"
                class="bg-white dark:bg-slate-700 rounded-lg overflow-hidden"
              >
                <table class="w-full">
                  <thead class="bg-gray-50 dark:bg-slate-600">
                    <tr>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                        Member
                      </th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                        Role
                      </th>
                      <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                        Type
                      </th>
                      <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                        Attendance
                      </th>
                      <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                        Amount
                      </th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200 dark:divide-slate-600">
                    <tr
                      v-for="payout in group.payouts"
                      :key="payout.user_id"
                      :class="{ 'bg-green-50 dark:bg-green-900/20': isCurrentUser(payout.user_id) }"
                    >
                      <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-50">
                        {{ payout.user_name }}
                        <i
                          v-if="isCurrentUser(payout.user_id)"
                          class="pi pi-user text-green-600 dark:text-green-400 ml-2"
                          title="You"
                        />
                      </td>
                      <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                        {{ payout.role || '-' }}
                      </td>
                      <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                        {{ formatPayoutType(payout.payout_type) }}
                      </td>
                      <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 text-center">
                        <span v-if="payout.events_attended !== undefined && payout.total_events !== undefined">
                          {{ payout.events_attended }}/{{ payout.total_events }}
                          <span class="text-xs text-gray-500 dark:text-gray-500">
                            ({{ Math.round((payout.weight || 0) * 100) }}%)
                          </span>
                        </span>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                      <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-50 text-right">
                        ${{ formatPrice(payout.amount) }}
                      </td>
                    </tr>
                  </tbody>
                </table>
                
              </div>
            </div>
          </div>
        </div>

        <!-- Legacy Member Payouts -->
        <div
          v-else-if="payoutResult.member_payouts?.length > 0"
          class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6"
        >
          <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-50 mb-4">
            Member Payouts
          </h2>
          
          <div class="bg-white dark:bg-slate-700 rounded-lg overflow-hidden">
            <table class="w-full">
              <thead class="bg-gray-50 dark:bg-slate-600">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                    Member
                  </th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                    Role
                  </th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                    Type
                  </th>
                  <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                    Attendance
                  </th>
                  <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                    Amount
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-slate-600">
                <tr
                  v-for="(payout, index) in payoutResult.member_payouts"
                  :key="index"
                  :class="{ 'bg-green-50 dark:bg-green-900/20': isCurrentUser(payout.user_id) }"
                >
                  <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-50">
                    {{ payout.name }}
                    <i
                      v-if="isCurrentUser(payout.user_id)"
                      class="pi pi-user text-green-600 dark:text-green-400 ml-2"
                      title="You"
                    />
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                    {{ payout.role || '-' }}
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                    {{ payout.type || 'N/A' }}
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 text-center">
                    <span v-if="payout.events_attended !== undefined && payout.total_events !== undefined">
                      {{ payout.events_attended }}/{{ payout.total_events }}
                    </span>
                    <span v-else class="text-gray-400">-</span>
                  </td>
                  <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-50 text-right">
                    ${{ formatPrice(payout.amount) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>

    <!-- Add Adjustment Dialog -->
    <Dialog
      v-model:visible="showAdjustmentDialog"
      modal
      header="Add Payout Adjustment"
      :style="{ width: '500px' }"
    >
      <form
        class="space-y-4"
        @submit.prevent="submitAdjustment"
      >
        <div>
          <label
            for="amount"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            Amount <span class="text-red-500">*</span>
          </label>
          <InputNumber
            id="amount"
            v-model="adjustmentForm.amount"
            mode="currency"
            currency="USD"
            locale="en-US"
            class="w-full"
            placeholder="Enter positive or negative amount"
          />
          <small class="text-gray-500 dark:text-gray-400 block mt-1">
            Use negative values for expenses/deductions, positive for bonuses
          </small>
          <div
            v-if="adjustmentForm.errors.amount"
            class="text-red-500 text-sm mt-1"
          >
            {{ adjustmentForm.errors.amount }}
          </div>
        </div>

        <div>
          <label
            for="description"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            Description <span class="text-red-500">*</span>
          </label>
          <InputText
            id="description"
            v-model="adjustmentForm.description"
            class="w-full"
            placeholder="e.g., Member absent, Extra expenses, Tip"
          />
          <div
            v-if="adjustmentForm.errors.description"
            class="text-red-500 text-sm mt-1"
          >
            {{ adjustmentForm.errors.description }}
          </div>
        </div>

        <div>
          <label
            for="notes"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            Notes (Optional)
          </label>
          <Textarea
            id="notes"
            v-model="adjustmentForm.notes"
            rows="3"
            class="w-full"
            placeholder="Additional details about this adjustment..."
          />
          <div
            v-if="adjustmentForm.errors.notes"
            class="text-red-500 text-sm mt-1"
          >
            {{ adjustmentForm.errors.notes }}
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-4">
          <Button
            label="Cancel"
            severity="secondary"
            @click="showAdjustmentDialog = false"
          />
          <Button
            type="submit"
            label="Add Adjustment"
            icon="pi pi-check"
            :loading="adjustmentForm.processing"
          />
        </div>
      </form>
    </Dialog>
  </Container>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, useForm, router } from '@inertiajs/vue3'
import Container from '@/Components/Container.vue'
import BookingLayout from './Layout/BookingLayout.vue'
import { DateTime } from 'luxon'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'

defineOptions({
  layout: BookingLayout,
})

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
  },
  adjustments: {
    type: Array,
    default: () => []
  },
  adjustedTotal: {
    type: Number,
    default: 0
  },
  availableConfigs: {
    type: Array,
    default: () => []
  }
})

const showAdjustmentDialog = ref(false)
const adjustmentForm = useForm({
  amount: 0,
  description: '',
  notes: ''
})

// Configuration selector
const selectedConfigId = ref(props.payoutConfig?.id || null)

const page = usePage()

const isCurrentUser = (userId) => {
  return page.props.auth?.user?.id === userId
}

const formatPrice = (price) => {
  if (price === null || price === undefined) return '0.00'
  return parseFloat(price).toFixed(2)
}

const formatDate = (date) => {
  if (!date) return 'Not specified'
  return DateTime.fromISO(date).toFormat('EEEE, MMMM d, yyyy')
}

const formatPayoutType = (type) => {
  const types = {
    'equal_split': 'Equal Split',
    'percentage': 'Percentage',
    'fixed': 'Fixed Amount',
    'payment_group': 'Group'
  }
  return types[type] || type
}

const getBandCutDescription = () => {
  if (!props.payoutConfig) return ''
  
  if (props.payoutConfig.band_cut_type === 'percentage') {
    return `${props.payoutConfig.band_cut_value}% of total`
  } else if (props.payoutConfig.band_cut_type === 'fixed') {
    return 'Fixed amount'
  } else if (props.payoutConfig.band_cut_type === 'tiered') {
    return 'Tiered based on amount'
  }
  
  return ''
}

const totalAdjustments = computed(() => {
  return props.adjustments.reduce((sum, adj) => {
    const amount = typeof adj.amount === 'string' ? parseFloat(adj.amount) : adj.amount
    return sum + amount
  }, 0)
})

const openAdjustmentDialog = () => {
  adjustmentForm.reset()
  showAdjustmentDialog.value = true
}

const submitAdjustment = () => {
  adjustmentForm.post(route('booking.payout.storeAdjustment', { 
    band: props.band.id, 
    booking: props.booking.id 
  }), {
    preserveScroll: true,
    onSuccess: () => {
      showAdjustmentDialog.value = false
      adjustmentForm.reset()
    }
  })
}

const deleteAdjustment = (adjustmentId) => {
  if (confirm('Are you sure you want to delete this adjustment?')) {
    router.delete(route('booking.payout.destroyAdjustment', {
      band: props.band.id,
      booking: props.booking.id,
      adjustment: adjustmentId
    }), {
      preserveScroll: true
    })
  }
}

const formatDateTime = (dateTime) => {
  if (!dateTime) return 'N/A'
  return DateTime.fromISO(dateTime).toFormat('MMM d, yyyy h:mm a')
}

const handleConfigurationChange = () => {
  if (!selectedConfigId.value) return

  router.put(
    route('booking.payout.updateConfiguration', {
      band: props.band.id,
      booking: props.booking.id
    }),
    {
      payout_config_id: selectedConfigId.value
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        // Configuration updated successfully
      }
    }
  )
}

const getConfigName = (configId) => {
  const config = props.availableConfigs.find(c => c.id === configId)
  return config?.name || 'Unknown'
}

const isActiveConfig = (configId) => {
  const config = props.availableConfigs.find(c => c.id === configId)
  return config?.is_active || false
}
</script>
