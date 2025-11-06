<template>
  <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-6 space-y-4">
    <FormField label="Configuration Name">
      <InputText
        :model-value="modelValue.name"
        class="w-full"
        placeholder="e.g., Default Split, Wedding Rate, Festival Rate"
        @update:model-value="updateField('name', $event)"
      />
    </FormField>

    <BandCutConfig
      :config="modelValue"
      @update="updateConfig"
    />

    <PaymentModeConfig
      :config="modelValue"
      :band="band"
      @update="updateConfig"
    />

    <MemberPayoutConfig
      v-if="!modelValue.use_payment_groups"
      :config="modelValue"
      :band="band"
      @update="updateConfig"
    />

    <FormField label="Minimum Payout Per Member">
      <InputNumber
        :model-value="modelValue.minimum_payout"
        mode="currency"
        currency="USD"
        locale="en-US"
        class="w-full"
        :min="0"
        @update:model-value="updateField('minimum_payout', $event)"
      />
    </FormField>

    <FormField label="Notes">
      <Textarea
        :model-value="modelValue.notes"
        rows="3"
        class="w-full"
        placeholder="Add any notes about this configuration..."
        @update:model-value="updateField('notes', $event)"
      />
    </FormField>

    <div class="flex justify-end space-x-2">
      <Button
        label="Cancel"
        icon="pi pi-times"
        severity="secondary"
        text
        @click="$emit('cancel')"
      />
      <Button
        label="Save Configuration"
        icon="pi pi-save"
        :loading="saving"
        @click="$emit('save')"
      />
    </div>
  </div>
</template>

<script setup>
import FormField from '@/Components/FormField.vue'
import BandCutConfig from './BandCutConfig.vue'
import PaymentModeConfig from './PaymentModeConfig.vue'
import MemberPayoutConfig from './MemberPayoutConfig.vue'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'

const props = defineProps({
  modelValue: {
    type: Object,
    required: true
  },
  band: {
    type: Object,
    required: true
  },
  saving: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'save', 'cancel'])

const updateField = (field, value) => {
  emit('update:modelValue', { ...props.modelValue, [field]: value })
}

const updateConfig = (updates) => {
  emit('update:modelValue', { ...props.modelValue, ...updates })
}
</script>
