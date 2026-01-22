<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="'Create Payment Group'"
    :style="{ width: '600px' }"
    @update:visible="handleClose"
  >
    <div class="space-y-4">
      <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
        Create a new payment group to organize member payouts.
      </p>

      <!-- Group Name -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Group Name <span class="text-red-500">*</span>
        </label>
        <InputText
          v-model="form.name"
          class="w-full"
          placeholder="e.g., Players, Production Team"
        />
        <small v-if="errors.name" class="text-red-500">{{ errors.name }}</small>
      </div>

      <!-- Description -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Description
        </label>
        <Textarea
          v-model="form.description"
          class="w-full"
          rows="2"
          placeholder="Optional description"
        />
      </div>

      <!-- Default Payout Type -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Default Payout Type <span class="text-red-500">*</span>
        </label>
        <Select
          v-model="form.default_payout_type"
          :options="payoutTypeOptions"
          option-label="label"
          option-value="value"
          class="w-full"
          placeholder="Select payout type"
        />
        <small class="text-gray-500 dark:text-gray-400">
          This will be the default for members added to this group
        </small>
      </div>

      <!-- Default Payout Value -->
      <div v-if="form.default_payout_type && form.default_payout_type !== 'equal_split'">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Default {{ form.default_payout_type === 'percentage' ? 'Percentage' : 'Amount' }}
        </label>
        <InputNumber
          v-model="form.default_payout_value"
          :mode="form.default_payout_type === 'percentage' ? 'decimal' : 'currency'"
          :suffix="form.default_payout_type === 'percentage' ? '%' : ''"
          :currency="form.default_payout_type === 'fixed' ? 'USD' : undefined"
          :min="0"
          :max="form.default_payout_type === 'percentage' ? 100 : undefined"
          class="w-full"
        />
      </div>

      <!-- Members Selection -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Add Members
        </label>
        <MultiSelect
          v-model="form.members"
          :options="availableMembers"
          option-label="name"
          option-value="id"
          placeholder="Select members (optional)"
          class="w-full"
          display="chip"
        />
        <small class="text-gray-500 dark:text-gray-400">
          You can add members now or later
        </small>
      </div>
    </div>

    <template #footer>
      <div class="flex gap-2 justify-end">
        <Button label="Cancel" severity="secondary" @click="handleClose" outlined />
        <Button label="Create Group" :loading="saving" @click="handleSave" />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import Textarea from 'primevue/textarea'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  bandId: {
    type: Number,
    required: true
  },
  availableMembers: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue', 'created'])

const visible = ref(props.modelValue)
const saving = ref(false)
const errors = ref({})

const payoutTypeOptions = [
  { label: 'Equal Split', value: 'equal_split' },
  { label: 'Percentage', value: 'percentage' },
  { label: 'Fixed Amount', value: 'fixed' }
]

const form = ref({
  name: '',
  description: '',
  default_payout_type: 'equal_split',
  default_payout_value: null,
  members: []
})

watch(() => props.modelValue, (newVal) => {
  visible.value = newVal
  if (newVal) {
    // Reset form when dialog opens
    form.value = {
      name: '',
      description: '',
      default_payout_type: 'equal_split',
      default_payout_value: null,
      members: []
    }
    errors.value = {}
  }
}, { immediate: true })

watch(visible, (newVal) => {
  emit('update:modelValue', newVal)
})

const handleSave = async () => {
  errors.value = {}

  // Validation
  if (!form.value.name) {
    errors.value.name = 'Group name is required'
    return
  }

  if (!form.value.default_payout_type) {
    errors.value.default_payout_type = 'Default payout type is required'
    return
  }

  saving.value = true

  router.post(
    route('finances.paymentGroup.store', { bandId: props.bandId }),
    form.value,
    {
      preserveScroll: true,
      onSuccess: (page) => {
        // Get the newly created group from the response
        // Note: The controller needs to return the group data
        visible.value = false
        emit('created')
      },
      onError: (pageErrors) => {
        errors.value = pageErrors
      },
      onFinish: () => {
        saving.value = false
      }
    }
  )
}

const handleClose = () => {
  visible.value = false
  errors.value = {}
}
</script>
