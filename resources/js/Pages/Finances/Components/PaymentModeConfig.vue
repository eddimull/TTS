<template>
  <div class="space-y-4">
    <InfoAlert variant="info">
      <label class="flex items-center text-sm font-medium mb-1">
        <Checkbox
          :model-value="config.use_payment_groups"
          :binary="true"
          class="mr-2"
          @update:model-value="$emit('update', { use_payment_groups: $event })"
        />
        Use Payment Groups
      </label>
      <div class="text-xs">
        When enabled, payouts are calculated based on configured payment groups instead of individual members
      </div>
    </InfoAlert>

    <div v-if="config.use_payment_groups && band.payment_groups && band.payment_groups.length > 0">
      <SectionHeader
        title="Payment Group Allocations"
        icon="pi-users"
      />
      <InfoAlert
        variant="info"
        class="my-4"
      >
        <strong>Sequential Allocation:</strong> Groups are allocated in order based on display order. 
        Each group takes from the <em>remaining</em> amount after previous groups.
      </InfoAlert>
      
      <div class="space-y-3">
        <div
          v-for="(group, index) in band.payment_groups"
          :key="group.id"
          class="bg-white dark:bg-gray-700 p-4 rounded-lg border-l-4"
          :class="getBorderClass(index)"
        >
          <div class="grid grid-cols-12 gap-4 items-end">
            <div class="col-span-4">
              <label class="text-xs text-gray-600 dark:text-gray-400">
                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-600 text-xs font-bold mr-2">
                  {{ index + 1 }}
                </span>
                Group Name
              </label>
              <div class="font-medium">
                {{ group.name }}
                <span class="text-xs text-gray-500">({{ group.users?.length || 0 }} members)</span>
              </div>
            </div>
            <div class="col-span-3">
              <label class="text-xs text-gray-600 dark:text-gray-400">Allocation Type</label>
              <Select
                :model-value="getGroupConfig(group.id).allocation_type"
                :options="[{label: 'Percentage', value: 'percentage'}, {label: 'Fixed', value: 'fixed'}]"
                option-label="label"
                option-value="value"
                class="w-full"
                @update:model-value="updateGroupConfig(group.id, 'allocation_type', $event)"
              />
            </div>
            <div class="col-span-4">
              <label class="text-xs text-gray-600 dark:text-gray-400">Allocation Value</label>
              <InputNumber
                v-if="getGroupConfig(group.id).allocation_type === 'percentage'"
                :model-value="getGroupConfig(group.id).allocation_value"
                mode="decimal"
                suffix="%"
                locale="en-US"
                class="w-full"
                :min="0"
                :max="100"
                @update:model-value="updateGroupConfig(group.id, 'allocation_value', $event)"
              />
              <InputNumber
                v-else
                :model-value="getGroupConfig(group.id).allocation_value"
                mode="currency"
                currency="USD"
                locale="en-US"
                class="w-full"
                :min="0"
                @update:model-value="updateGroupConfig(group.id, 'allocation_value', $event)"
              />
            </div>
            <div class="col-span-1 text-center">
              <i
                v-if="group.is_active"
                class="pi pi-check-circle text-green-500"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <InfoAlert
      v-if="config.use_payment_groups && (!band.payment_groups || band.payment_groups.length === 0)"
      variant="warning"
    >
      No payment groups exist for this band. Create payment groups above first.
    </InfoAlert>
  </div>
</template>

<script setup>
import InfoAlert from '@/Components/InfoAlert.vue'
import SectionHeader from '@/Components/SectionHeader.vue'
import Checkbox from 'primevue/checkbox'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'

const props = defineProps({
  config: {
    type: Object,
    required: true
  },
  band: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['update'])

const getBorderClass = (index) => {
  const classes = ['border-blue-500', 'border-green-500', 'border-orange-500', 'border-purple-500']
  return index < 3 ? classes[index] : classes[3]
}

const getGroupConfig = (groupId) => {
  if (!props.config.payment_group_config) return { allocation_type: 'percentage', allocation_value: 0 }
  const groupConfig = props.config.payment_group_config.find(g => g.group_id === groupId)
  return groupConfig || { group_id: groupId, allocation_type: 'percentage', allocation_value: 0 }
}

const updateGroupConfig = (groupId, field, value) => {
  const groupConfigs = [...(props.config.payment_group_config || [])]
  const index = groupConfigs.findIndex(g => g.group_id === groupId)
  
  if (index >= 0) {
    groupConfigs[index] = { ...groupConfigs[index], [field]: value }
  } else {
    groupConfigs.push({ group_id: groupId, allocation_type: 'percentage', allocation_value: 0, [field]: value })
  }
  
  emit('update', { payment_group_config: groupConfigs })
}
</script>
