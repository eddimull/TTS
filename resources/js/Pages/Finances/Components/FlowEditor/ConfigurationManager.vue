<template>
  <div>
    <!-- Trigger Button -->
    <Button
      :label="currentConfigName"
      icon="pi pi-cog"
      @click="showDialog = true"
      outlined
      severity="secondary"
      class="font-medium"
    >
      <template #icon>
        <i class="pi pi-cog mr-2" />
      </template>
    </Button>

    <!-- Configuration Manager Dialog -->
    <Dialog
      v-model:visible="showDialog"
      header="Payout Configurations"
      :modal="true"
      :closable="true"
      :style="{ width: '600px' }"
    >
      <div class="space-y-4">
        <!-- Current Active Configuration -->
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-3">
          <div class="flex items-center gap-2 mb-2">
            <i class="pi pi-check-circle text-green-600 dark:text-green-400" />
            <span class="text-sm font-semibold text-green-800 dark:text-green-300">Active Configuration</span>
          </div>
          <div class="font-medium text-gray-800 dark:text-gray-200">
            {{ activeConfig?.name || 'No active configuration' }}
          </div>
        </div>

        <!-- Create New Configuration -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-3">
          <div class="flex items-center gap-2 mb-3">
            <i class="pi pi-plus-circle text-blue-600 dark:text-blue-400" />
            <span class="text-sm font-semibold text-blue-800 dark:text-blue-300">Create New Configuration</span>
          </div>
          <div class="flex gap-2">
            <InputText
              v-model="newConfigName"
              placeholder="Configuration name"
              class="flex-1"
              @keyup.enter="createNewConfiguration"
            />
            <Button
              label="Create"
              icon="pi pi-plus"
              @click="createNewConfiguration"
              :disabled="!newConfigName.trim()"
            />
          </div>
        </div>

        <!-- List of All Configurations -->
        <div>
          <div class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
            All Configurations ({{ configurations.length }})
          </div>
          <div class="space-y-2 max-h-[400px] overflow-y-auto">
            <div
              v-for="config in configurations"
              :key="config.id"
              :class="[
                'border rounded-lg p-3 transition-all',
                config.is_active
                  ? 'border-green-300 dark:border-green-700 bg-green-50 dark:bg-green-900/10'
                  : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
              ]"
            >
              <div class="flex items-start justify-between gap-3">
                <!-- Config Info -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="font-medium text-gray-800 dark:text-gray-200 truncate">
                      {{ config.name }}
                    </span>
                    <span v-if="config.is_active" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                      Active
                    </span>
                  </div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">
                    <span v-if="config.updated_at">
                      Updated {{ formatDate(config.updated_at) }}
                    </span>
                  </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-1">
                  <Button
                    v-if="!config.is_active"
                    icon="pi pi-check"
                    text
                    rounded
                    size="small"
                    severity="success"
                    v-tooltip.left="'Set as active'"
                    @click="setActiveConfiguration(config.id)"
                  />
                  <Button
                    icon="pi pi-pencil"
                    text
                    rounded
                    size="small"
                    v-tooltip.left="'Edit in flow editor'"
                    @click="editConfiguration(config.id)"
                  />
                  <Button
                    icon="pi pi-copy"
                    text
                    rounded
                    size="small"
                    v-tooltip.left="'Duplicate'"
                    @click="duplicateConfiguration(config.id)"
                  />
                  <Button
                    v-if="configurations.length > 1"
                    icon="pi pi-trash"
                    text
                    rounded
                    size="small"
                    severity="danger"
                    v-tooltip.left="'Delete'"
                    @click="confirmDelete(config)"
                  />
                </div>
              </div>
            </div>

            <div v-if="configurations.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
              <i class="pi pi-inbox text-3xl mb-2 block" />
              <p>No configurations yet</p>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <Button label="Close" text @click="showDialog = false" />
      </template>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <Dialog
      v-model:visible="showDeleteDialog"
      header="Delete Configuration"
      :modal="true"
      :closable="true"
      :style="{ width: '400px' }"
    >
      <div class="space-y-3">
        <p>Are you sure you want to delete this configuration?</p>
        <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded border border-gray-200 dark:border-gray-700">
          <div class="font-medium text-gray-800 dark:text-gray-200">
            {{ configToDelete?.name }}
          </div>
        </div>
        <p class="text-sm text-red-600 dark:text-red-400">
          <i class="pi pi-exclamation-triangle mr-1" />
          This action cannot be undone.
        </p>
      </div>

      <template #footer>
        <Button label="Cancel" text @click="showDeleteDialog = false" />
        <Button label="Delete" severity="danger" @click="deleteConfiguration" :loading="deleting" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
  band: {
    type: Object,
    required: true
  },
  configurations: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['configurationChanged'])

const toast = useToast()
const showDialog = ref(false)
const showDeleteDialog = ref(false)
const configToDelete = ref(null)
const deleting = ref(false)
const newConfigName = ref('')

const activeConfig = computed(() => {
  return props.configurations.find(c => c.is_active)
})

const currentConfigName = computed(() => {
  return activeConfig.value?.name || 'No Configuration'
})

const formatDate = (dateString) => {
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now - date
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'just now'
  if (diffMins < 60) return `${diffMins}m ago`
  if (diffHours < 24) return `${diffHours}h ago`
  if (diffDays < 7) return `${diffDays}d ago`

  return date.toLocaleDateString()
}

const createNewConfiguration = () => {
  if (!newConfigName.value.trim()) return

  router.post(
    route('finances.payoutConfig.store', props.band.id),
    {
      name: newConfigName.value.trim(),
      is_active: false,
      band_cut_type: 'none',
      band_cut_value: 0,
      use_payment_groups: false,
      member_payout_type: 'equal_split',
      include_owners: true,
      include_members: true,
      flow_diagram: {
        nodes: [],
        edges: [],
        version: '2.0'
      }
    },
    {
      onSuccess: () => {
        toast.add({
          severity: 'success',
          summary: 'Configuration Created',
          detail: `"${newConfigName.value}" has been created`,
          life: 3000
        })
        newConfigName.value = ''
        showDialog.value = false
        emit('configurationChanged')
      },
      onError: (errors) => {
        toast.add({
          severity: 'error',
          summary: 'Failed to Create',
          detail: errors.message || 'Could not create configuration',
          life: 5000
        })
      }
    }
  )
}

const setActiveConfiguration = (configId) => {
  router.post(
    route('finances.payoutConfig.setActive', [props.band.id, configId]),
    {},
    {
      onSuccess: () => {
        toast.add({
          severity: 'success',
          summary: 'Configuration Activated',
          detail: 'This configuration is now active',
          life: 3000
        })
        showDialog.value = false
        emit('configurationChanged')
      },
      onError: (errors) => {
        toast.add({
          severity: 'error',
          summary: 'Failed to Activate',
          detail: errors.message || 'Could not activate configuration',
          life: 5000
        })
      }
    }
  )
}

const editConfiguration = (configId) => {
  // Set as active first if not already
  const config = props.configurations.find(c => c.id === configId)
  if (!config.is_active) {
    setActiveConfiguration(configId)
  } else {
    showDialog.value = false
    // Already editing the active config, just close dialog
  }
}

const duplicateConfiguration = (configId) => {
  const config = props.configurations.find(c => c.id === configId)
  if (!config) return

  router.post(
    route('finances.payoutConfig.duplicate', [props.band.id, configId]),
    {},
    {
      onSuccess: () => {
        toast.add({
          severity: 'success',
          summary: 'Configuration Duplicated',
          detail: `Copy of "${config.name}" has been created`,
          life: 3000
        })
        emit('configurationChanged')
      },
      onError: (errors) => {
        toast.add({
          severity: 'error',
          summary: 'Failed to Duplicate',
          detail: errors.message || 'Could not duplicate configuration',
          life: 5000
        })
      }
    }
  )
}

const confirmDelete = (config) => {
  configToDelete.value = config
  showDeleteDialog.value = true
}

const deleteConfiguration = () => {
  if (!configToDelete.value) return

  deleting.value = true

  router.delete(
    route('finances.payoutConfig.destroy', [props.band.id, configToDelete.value.id]),
    {
      onSuccess: () => {
        toast.add({
          severity: 'success',
          summary: 'Configuration Deleted',
          detail: `"${configToDelete.value.name}" has been deleted`,
          life: 3000
        })
        showDeleteDialog.value = false
        configToDelete.value = null
        emit('configurationChanged')
      },
      onError: (errors) => {
        toast.add({
          severity: 'error',
          summary: 'Failed to Delete',
          detail: errors.message || 'Could not delete configuration',
          life: 5000
        })
      },
      onFinish: () => {
        deleting.value = false
      }
    }
  )
}
</script>
