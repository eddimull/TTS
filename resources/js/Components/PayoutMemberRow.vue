<template>
  <div
    class="grid grid-cols-12 gap-2 items-end p-3 rounded"
    :class="backgroundClass"
  >
    <div class="col-span-4">
      <label class="text-xs text-gray-600 dark:text-gray-400">{{ nameLabel }}</label>
      <InputText
        :model-value="member.name"
        class="w-full"
        :disabled="!editable"
        :placeholder="namePlaceholder"
        @update:model-value="$emit('update:member', { ...member, name: $event })"
      />
    </div>
    <div class="col-span-3">
      <label class="text-xs text-gray-600 dark:text-gray-400">Payout Type</label>
      <Select
        :model-value="member.payout_type || member.type"
        :options="payoutTypes"
        option-label="label"
        option-value="value"
        class="w-full"
        @update:model-value="$emit('update:member', { ...member, payout_type: $event, type: $event })"
      />
    </div>
    <div class="col-span-4">
      <label class="text-xs text-gray-600 dark:text-gray-400">Value</label>
      <InputNumber
        v-if="(member.payout_type || member.type) === 'percentage'"
        :model-value="member.value"
        mode="decimal"
        suffix="%"
        locale="en-US"
        class="w-full"
        :min="0"
        :max="100"
        @update:model-value="$emit('update:member', { ...member, value: $event })"
      />
      <InputNumber
        v-else-if="(member.payout_type || member.type) === 'fixed'"
        :model-value="member.value"
        mode="currency"
        currency="USD"
        locale="en-US"
        class="w-full"
        :min="0"
        @update:model-value="$emit('update:member', { ...member, value: $event })"
      />
      <span
        v-else
        class="text-sm text-gray-500 dark:text-gray-400 italic"
      >
        Equal split
      </span>
    </div>
    <div class="col-span-1">
      <Button
        v-if="removable"
        icon="pi pi-trash"
        severity="danger"
        text
        size="small"
        @click="$emit('remove')"
      />
      <i
        v-else-if="showCheckmark"
        class="pi pi-check-circle text-green-500"
      />
    </div>
  </div>
</template>

<script setup>
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Button from 'primevue/button'

defineProps({
  member: {
    type: Object,
    required: true
  },
  payoutTypes: {
    type: Array,
    required: true
  },
  backgroundClass: {
    type: String,
    default: 'bg-gray-50 dark:bg-gray-700'
  },
  nameLabel: {
    type: String,
    default: 'Name'
  },
  namePlaceholder: {
    type: String,
    default: ''
  },
  editable: {
    type: Boolean,
    default: true
  },
  removable: {
    type: Boolean,
    default: false
  },
  showCheckmark: {
    type: Boolean,
    default: false
  }
})

defineEmits(['update:member', 'remove'])
</script>
