<template>
  <BaseFlowNode
    :id="id"
    :data="data"
    border-color="border-blue-500"
    icon-color="text-blue-500"
    :icon="sourceTypeIcon"
    :title="nodeTitle"
    width="min-w-[240px]"
    header-justify="justify-between"
    :handles="handles"
    :calculated-values="calculatedValues"
    @update="(...args) => emit('update', ...args)"
    @settings="() => showDialog = true"
    @delete="(...args) => emit('delete', ...args)"
    @duplicate="(...args) => emit('duplicate', ...args)"
    @rename="(...args) => emit('rename', ...args)"
  >
    <template #header-actions>
      <Button
        icon="pi pi-cog"
        text
        rounded
        size="small"
        @click="showDialog = true"
        v-tooltip.left="'Configure payout group'"
      />
    </template>

    <template #content>
      <div class="space-y-2">
        <!-- Source Type Badge -->
        <div class="flex items-center gap-2">
          <i :class="`pi ${sourceTypeConfig.icon} text-blue-600 dark:text-blue-400`" />
          <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">
            {{ sourceTypeConfig.label }}
          </span>
        </div>

        <!-- Distribution Mode -->
        <div class="bg-blue-50 dark:bg-blue-900/20 p-2 rounded border border-blue-200 dark:border-blue-700">
          <div class="text-xs text-blue-800 dark:text-blue-300 font-medium">
            {{ distributionModeLabel }}
          </div>
        </div>

        <!-- Allocation Type -->
        <div class="text-xs text-gray-600 dark:text-gray-400">
          <i class="pi pi-arrow-down mr-1" />
          {{ allocationSummary }}
        </div>

        <!-- Member Count (if available) -->
        <div v-if="memberCount" class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
          <i class="pi pi-users" />
          {{ memberCount }} {{ memberCount === 1 ? 'member' : 'members' }}
        </div>
      </div>
    </template>
  </BaseFlowNode>

  <!-- Configuration Dialog -->
  <Dialog
    v-model:visible="showDialog"
    :header="`Configure Payout Group`"
    :modal="true"
    :closable="true"
    :style="{ width: '700px', maxHeight: '80vh' }"
    :contentStyle="{ overflow: 'auto' }"
  >
    <!-- Label Input at Top -->
    <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        <i class="pi pi-tag mr-2" />
        Group Name/Label
      </label>
      <InputText
        v-model="localLabel"
        placeholder="e.g., Production Team, Regular Members, Management"
        class="w-full"
      />
      <small class="text-gray-500 dark:text-gray-400 block mt-1">
        This name will appear on the node and in reports
      </small>
    </div>

    <TabView>
      <!-- Tab 1: Recipients -->
      <TabPanel header="Recipients">
        <div class="space-y-4">
          <!-- Source Type Selection -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Source Type
            </label>
            <div class="grid grid-cols-1 gap-2">
              <div
                v-for="source in SOURCE_TYPES"
                :key="source.value"
                @click="localSourceType = source.value"
                :class="[
                  'p-3 rounded-lg border-2 cursor-pointer transition-all',
                  localSourceType === source.value
                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                ]"
              >
                <div class="flex items-center gap-3">
                  <i :class="`pi ${source.icon} text-2xl`" :style="{ color: localSourceType === source.value ? '#3b82f6' : '#6b7280' }" />
                  <div class="flex-1">
                    <div class="font-medium text-gray-800 dark:text-gray-200">{{ source.label }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ source.description }}</div>
                  </div>
                  <i v-if="localSourceType === source.value" class="pi pi-check-circle text-blue-500" />
                </div>
              </div>
            </div>
          </div>

          <!-- Roster Configuration -->
          <div v-if="localSourceType === 'roster'" class="space-y-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="flex items-center gap-2">
              <Checkbox v-model="localRosterConfig.useAttendanceWeighting" binary input-id="attendance-weight" />
              <label for="attendance-weight" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                Weight payouts by attendance
              </label>
            </div>

            <div>
              <label class="block text-sm text-gray-700 dark:text-gray-300 mb-2">Member Type Filter</label>
              <div class="space-y-2">
                <div class="flex items-center gap-2">
                  <RadioButton
                    v-model="localRosterConfig.memberTypeFilter"
                    inputId="member-type-all"
                    value="all"
                  />
                  <label for="member-type-all" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    Include both members and substitutes
                  </label>
                </div>
                <div class="flex items-center gap-2">
                  <RadioButton
                    v-model="localRosterConfig.memberTypeFilter"
                    inputId="member-type-members"
                    value="members_only"
                  />
                  <label for="member-type-members" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    Members only (exclude substitutes)
                  </label>
                </div>
                <div class="flex items-center gap-2">
                  <RadioButton
                    v-model="localRosterConfig.memberTypeFilter"
                    inputId="member-type-subs"
                    value="substitutes_only"
                  />
                  <label for="member-type-subs" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    Substitutes only (exclude members)
                  </label>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-sm text-gray-700 dark:text-gray-300 mb-1">Filter by roles (optional)</label>
              <MultiSelect
                v-model="localRosterConfig.filterByRoleId"
                :options="availableRoles"
                optionLabel="name"
                optionValue="id"
                placeholder="Select roles to include"
                class="w-full"
                :showClear="true"
                display="chip"
              >
                <template #empty>
                  <div class="p-3 text-center text-sm text-gray-500">
                    No roles found. Roles can be managed in your band settings.
                  </div>
                </template>
              </MultiSelect>
              <small class="text-gray-500">Leave empty to include all roles</small>
            </div>

            <div>
              <label class="block text-sm text-gray-700 dark:text-gray-300 mb-1">Minimum events to qualify</label>
              <InputNumber v-model="localRosterConfig.minEventsToQualify" :min="1" class="w-full" />
            </div>
          </div>

          <!-- Payment Group Selection -->
          <div v-if="localSourceType === 'paymentGroup'" class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Select Payment Group
            </label>
            <Select
              v-model="localPaymentGroupId"
              :options="availablePaymentGroups"
              option-label="name"
              option-value="id"
              placeholder="Choose a payment group"
              class="w-full"
            >
              <template #option="slotProps">
                <div>
                  <div class="font-medium">{{ slotProps.option.name }}</div>
                  <div class="text-xs text-gray-500">{{ slotProps.option.members?.length || 0 }} members</div>
                </div>
              </template>
            </Select>
          </div>

          <!-- All Members Configuration -->
          <div v-if="localSourceType === 'allMembers'" class="space-y-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="flex items-center gap-2">
              <Checkbox v-model="localAllMembersConfig.includeOwners" binary input-id="include-owners" />
              <label for="include-owners" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                Include band owners
              </label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="localAllMembersConfig.includeMembers" binary input-id="include-members" />
              <label for="include-members" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                Include band members
              </label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox v-model="localAllMembersConfig.includeProduction" binary input-id="include-production" />
              <label for="include-production" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                Include production members
              </label>
            </div>

            <div v-if="localAllMembersConfig.includeProduction">
              <label class="block text-sm text-gray-700 dark:text-gray-300 mb-1">Production member count</label>
              <InputNumber v-model="localAllMembersConfig.productionCount" :min="0" class="w-full" />
            </div>
          </div>
        </div>
      </TabPanel>

      <!-- Tab 2: Distribution -->
      <TabPanel header="Distribution">
        <div class="space-y-4">
          <!-- Distribution Mode Selection -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Distribution Mode
            </label>
            <div class="grid grid-cols-1 gap-2">
              <div
                v-for="mode in DISTRIBUTION_MODES"
                :key="mode.value"
                @click="localDistributionMode = mode.value"
                :class="[
                  'p-3 rounded-lg border-2 cursor-pointer transition-all',
                  localDistributionMode === mode.value
                    ? 'border-green-500 bg-green-50 dark:bg-green-900/20'
                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                ]"
              >
                <div class="flex items-center gap-3">
                  <i :class="`pi ${mode.icon} text-xl`" :style="{ color: localDistributionMode === mode.value ? '#10b981' : '#6b7280' }" />
                  <div class="flex-1">
                    <div class="font-medium text-gray-800 dark:text-gray-200">{{ mode.label }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ mode.description }}</div>
                  </div>
                  <i v-if="localDistributionMode === mode.value" class="pi pi-check-circle text-green-500" />
                </div>
              </div>
            </div>
          </div>

          <!-- Tiered Configuration -->
          <div v-if="localDistributionMode === 'tiered'" class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Tier Configuration</span>
              <Button
                label="Configure Tiers"
                icon="pi pi-cog"
                size="small"
                @click="emit('settings', id)"
              />
            </div>
            <small class="text-gray-500">Click to configure tiered distribution settings</small>
          </div>

          <!-- Fixed Amount Per Member -->
          <div v-if="localDistributionMode === 'fixed'" class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Fixed Amount Per Member
            </label>
            <InputNumber
              v-model="localFixedAmountPerMember"
              mode="currency"
              currency="USD"
              :min="0"
              class="w-full"
            />
            <small class="text-gray-500 block mt-2">
              Each qualifying member will receive this amount. Total allocation will be calculated automatically.
            </small>
          </div>

          <!-- Additional Options -->
          <div class="space-y-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="flex items-center gap-2">
              <Checkbox v-model="localRespectCustomPayouts" binary input-id="respect-custom" />
              <label for="respect-custom" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                Respect custom payout overrides
              </label>
            </div>
            <small class="text-gray-500 block">
              If enabled, members with custom EventMember.payout_amount will bypass this calculation
            </small>

            <div>
              <label class="block text-sm text-gray-700 dark:text-gray-300 mb-1">Minimum payout per member</label>
              <InputNumber v-model="localMinimumPayout" mode="currency" currency="USD" :min="0" class="w-full" />
            </div>
          </div>
        </div>
      </TabPanel>

      <!-- Tab 3: Allocation -->
      <TabPanel header="Allocation">
        <div class="space-y-4">
          <!-- Incoming Allocation Type -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Incoming Allocation Type
            </label>
            <div class="grid grid-cols-1 gap-2">
              <div
                v-for="allocType in allocationTypes"
                :key="allocType.value"
                @click="localIncomingAllocationType = allocType.value"
                :class="[
                  'p-3 rounded-lg border-2 cursor-pointer transition-all',
                  localIncomingAllocationType === allocType.value
                    ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20'
                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                ]"
              >
                <div class="flex items-center gap-3">
                  <i :class="`pi ${allocType.icon} text-xl`" :style="{ color: localIncomingAllocationType === allocType.value ? '#a855f7' : '#6b7280' }" />
                  <div class="flex-1">
                    <div class="font-medium text-gray-800 dark:text-gray-200">{{ allocType.label }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ allocType.description }}</div>
                  </div>
                  <i v-if="localIncomingAllocationType === allocType.value" class="pi pi-check-circle text-purple-500" />
                </div>
              </div>
            </div>
          </div>

          <!-- Allocation Value -->
          <div v-if="localIncomingAllocationType !== 'remainder'">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              {{ localIncomingAllocationType === 'percentage' ? 'Percentage' : 'Fixed Amount' }}
            </label>
            <InputNumber
              v-model="localIncomingAllocationValue"
              :mode="localIncomingAllocationType === 'percentage' ? 'decimal' : 'currency'"
              :suffix="localIncomingAllocationType === 'percentage' ? '%' : ''"
              :currency="localIncomingAllocationType === 'fixed' ? 'USD' : undefined"
              :min="0"
              :max="localIncomingAllocationType === 'percentage' ? 100 : undefined"
              class="w-full"
            />
          </div>

          <!-- Info about sequential execution -->
          <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
            <div class="flex items-start gap-2">
              <i class="pi pi-info-circle text-blue-600 dark:text-blue-400 mt-0.5" />
              <div class="text-sm text-blue-800 dark:text-blue-200">
                <strong>Sequential Execution:</strong> Nodes execute in the order they appear in the flow. Money flows from top to bottom, with each node receiving what's left from the previous node.
              </div>
            </div>
          </div>
        </div>
      </TabPanel>
    </TabView>

    <template #footer>
      <Button label="Cancel" text @click="cancelDialog" />
      <Button label="Save" @click="saveConfiguration" />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Position } from '@vue-flow/core'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Checkbox from 'primevue/checkbox'
import RadioButton from 'primevue/radiobutton'
import MultiSelect from 'primevue/multiselect'
import BaseFlowNode from '../BaseFlowNode.vue'
import { useFlowNode } from '../useFlowNode'
import { SOURCE_TYPES, DISTRIBUTION_MODES } from '@/composables/useFlowNodeSchemas'

const props = defineProps({
  id: {
    type: String,
    required: true
  },
  data: {
    type: Object,
    required: true
  },
  availablePaymentGroups: {
    type: Array,
    default: () => []
  },
  availableRoles: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update', 'settings', 'delete', 'duplicate', 'rename'])

const { useSyncedRef, emitUpdate } = useFlowNode(props, emit)

const showDialog = ref(false)

// Local state for dialog
const localLabel = useSyncedRef('label', 'Payout Group')
const localSourceType = useSyncedRef('sourceType', 'roster')
// Initialize roster config with backwards compatibility
const initRosterConfig = () => {
  const defaultConfig = {
    useAttendanceWeighting: true,
    filterByRole: null, // Legacy text-based filter (keep for backward compatibility)
    filterByRoleId: null, // New ID-based filter
    excludeRoles: [],
    minEventsToQualify: 1,
    memberTypeFilter: 'all'
  }

  const config = { ...defaultConfig, ...props.data.rosterConfig }

  // Backwards compatibility: convert old includeSubstitutes to new memberTypeFilter
  if ('includeSubstitutes' in config && !('memberTypeFilter' in props.data.rosterConfig)) {
    config.memberTypeFilter = config.includeSubstitutes ? 'all' : 'members_only'
    delete config.includeSubstitutes
  }

  return config
}

const localRosterConfig = ref(initRosterConfig())
const localPaymentGroupId = useSyncedRef('paymentGroupId', null)
const localAllMembersConfig = ref({ ...props.data.allMembersConfig || {
  includeOwners: true,
  includeMembers: true,
  includeProduction: false,
  productionCount: 0
}})
const localDistributionMode = useSyncedRef('distributionMode', 'equal_split')
const localFixedAmountPerMember = useSyncedRef('fixedAmountPerMember', 0, true)
const localRespectCustomPayouts = useSyncedRef('respectCustomPayouts', true)
const localMinimumPayout = useSyncedRef('minimumPayout', 0, true)
const localIncomingAllocationType = useSyncedRef('incomingAllocationType', 'remainder')
const localIncomingAllocationValue = useSyncedRef('incomingAllocationValue', 0, true)

// Computed properties for display
const nodeTitle = computed(() => {
  // Prioritize user-set label, then fall back to customLabel or default
  return props.data.label || props.data.customLabel || 'Payout Group'
})

const sourceTypeConfig = computed(() => {
  return SOURCE_TYPES.find(s => s.value === props.data.sourceType) || SOURCE_TYPES[0]
})

const sourceTypeIcon = computed(() => {
  return sourceTypeConfig.value.icon || 'pi-users'
})

const distributionModeLabel = computed(() => {
  const mode = DISTRIBUTION_MODES.find(m => m.value === props.data.distributionMode)
  return mode ? mode.label : 'Equal Split'
})

const allocationSummary = computed(() => {
  const type = props.data.incomingAllocationType
  const value = props.data.incomingAllocationValue

  if (type === 'remainder') {
    return 'Takes remaining amount'
  } else if (type === 'percentage') {
    return `Takes ${value}%`
  } else if (type === 'fixed') {
    return `Takes $${value}`
  }

  return 'Unknown allocation'
})

const memberCount = computed(() => {
  if (props.data._visualizationData?.actualMembers) {
    return props.data._visualizationData.actualMembers.length
  }
  return null
})

// Allocation type options
const allocationTypes = [
  {
    value: 'remainder',
    label: 'Remainder',
    description: 'Takes whatever is left from input',
    icon: 'pi-arrow-right'
  },
  {
    value: 'percentage',
    label: 'Percentage',
    description: 'Takes a percentage of input',
    icon: 'pi-percentage'
  },
  {
    value: 'fixed',
    label: 'Fixed Amount',
    description: 'Takes a fixed dollar amount',
    icon: 'pi-dollar'
  }
]

// Handles
const handles = [
  { type: 'target', position: Position.Left, id: 'payoutgroup-in' },
  { type: 'source', position: Position.Right, id: 'payoutgroup-out' }
]

// Calculated values for visualization
const calculatedValues = computed(() => {
  if (!props.data.input) return []

  const result = []

  result.push({
    label: 'Input',
    value: props.data.input,
    format: 'money',
    class: 'font-medium'
  })

  if (props.data.allocated !== undefined) {
    result.push({
      label: 'Allocated',
      value: props.data.allocated,
      format: 'money',
      class: 'font-bold text-blue-600'
    })
  }
  
  if (props.data.allocated !== undefined) {
    result.push({
      label: 'Per Member',
      value: props.data.allocated/(props.data._visualizationData?.actualMembers?.length || 1),
      format: 'money',
      class: 'font-bold text-green-600'
    })
  }


  if (props.data.output !== undefined) {
    result.push({
      label: 'Remaining',
      value: props.data.output,
      format: 'money',
      class: 'font-medium text-gray-600'
    })
  }

  return result
})

// Cancel dialog
const cancelDialog = () => {
  // Reset local state to current props
  localLabel.value = props.data.label
  localSourceType.value = props.data.sourceType
  localRosterConfig.value = { ...props.data.rosterConfig }
  localPaymentGroupId.value = props.data.paymentGroupId
  localAllMembersConfig.value = { ...props.data.allMembersConfig }
  localDistributionMode.value = props.data.distributionMode
  localFixedAmountPerMember.value = props.data.fixedAmountPerMember
  localRespectCustomPayouts.value = props.data.respectCustomPayouts
  localMinimumPayout.value = props.data.minimumPayout
  localIncomingAllocationType.value = props.data.incomingAllocationType
  localIncomingAllocationValue.value = props.data.incomingAllocationValue

  showDialog.value = false
}

// Save configuration
const saveConfiguration = () => {
  emitUpdate({
    label: localLabel.value,
    sourceType: localSourceType.value,
    rosterConfig: { ...localRosterConfig.value },
    paymentGroupId: localPaymentGroupId.value,
    allMembersConfig: { ...localAllMembersConfig.value },
    distributionMode: localDistributionMode.value,
    fixedAmountPerMember: localFixedAmountPerMember.value,
    respectCustomPayouts: localRespectCustomPayouts.value,
    minimumPayout: localMinimumPayout.value,
    incomingAllocationType: localIncomingAllocationType.value,
    incomingAllocationValue: localIncomingAllocationValue.value
  })

  showDialog.value = false
}
</script>

<style scoped>
:deep(.vue-flow__handle) {
  background: #3b82f6;
  border: 2px solid #2563eb;
}

:deep(.vue-flow__handle:hover) {
  background: #2563eb;
}
</style>
