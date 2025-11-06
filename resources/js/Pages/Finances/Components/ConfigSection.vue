<template>
  <div class="space-y-4">
    <div class="flex justify-between items-center">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
        <i class="pi pi-cog mr-2" />
        Payout Configuration
      </h3>
      <Button
        v-if="!isEditing"
        label="Edit Configuration"
        icon="pi pi-pencil"
        severity="secondary"
        size="small"
        @click="$emit('edit')"
      />
      <Button
        v-else
        label="Cancel"
        icon="pi pi-times"
        severity="danger"
        size="small"
        text
        @click="$emit('cancel')"
      />
    </div>

    <ConfigDisplay
      v-if="!isEditing && config"
      :config="config"
      :owner-count="ownerCount"
      :member-count="memberCount"
    />

    <PayoutConfigForm
      v-if="isEditing"
      :model-value="editingConfig"
      :band="band"
      :saving="saving"
      @update:model-value="$emit('update:editingConfig', $event)"
      @save="$emit('save')"
      @cancel="$emit('cancel')"
    />

    <InfoAlert
      v-if="!isEditing && !config"
      variant="warning"
    >
      <div class="text-center">
        <i class="pi pi-info-circle text-yellow-600 dark:text-yellow-400 text-2xl mb-2 block" />
        <p class="text-gray-700 dark:text-gray-300">
          No payout configuration set for this band yet.
        </p>
        <Button
          label="Create Configuration"
          icon="pi pi-plus"
          severity="secondary"
          size="small"
          class="mt-2"
          @click="$emit('edit')"
        />
      </div>
    </InfoAlert>
  </div>
</template>

<script setup>
import ConfigDisplay from './ConfigDisplay.vue'
import PayoutConfigForm from './PayoutConfigForm.vue'
import InfoAlert from '@/Components/InfoAlert.vue'
import Button from 'primevue/button'

defineProps({
  config: {
    type: Object,
    default: null
  },
  editingConfig: {
    type: Object,
    required: true
  },
  band: {
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
  },
  isEditing: {
    type: Boolean,
    default: false
  },
  saving: {
    type: Boolean,
    default: false
  }
})

defineEmits(['edit', 'cancel', 'save', 'update:editingConfig'])
</script>
