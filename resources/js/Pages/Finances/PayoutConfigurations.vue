<template>
  <FinanceLayout>
    <Head title="Payout Configurations" />
    <Toast />

    <div class="mx-4 my-6 space-y-6">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">
            Payout Configurations
          </h1>
          <p class="text-gray-600 dark:text-gray-400 mt-1">
            Manage payout flow configurations for your bands
          </p>
        </div>
        <Button
          label="Create Configuration"
          icon="pi pi-plus"
          @click="showCreateDialog = true"
          class="bg-blue-600 hover:bg-blue-700"
        />
      </div>

      <div
        v-for="band in bands"
        :key="band.id"
        class="componentPanel shadow-lg rounded-lg p-6"
      >
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-100 mb-4">
          {{ band.name }}
        </h2>

        <DataTable
          :value="band.payout_configs"
          :rows="10"
          :paginator="band.payout_configs.length > 10"
          class="p-datatable-sm"
          stripedRows
          :rowClass="rowClass"
        >
          <Column field="name" header="Configuration Name" sortable>
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <span
                  v-if="editingConfigId !== data.id"
                  class="font-medium"
                  :class="data.is_active ? 'text-green-600 dark:text-green-400' : ''"
                >
                  {{ data.name }}
                </span>
                <InputText
                  v-else
                  v-model="editingName"
                  class="w-full max-w-md"
                  @keyup.enter="saveRename(band.id, data.id)"
                  @keyup.esc="cancelRename"
                  autofocus
                />
                <Tag
                  v-if="data.is_active"
                  value="Active"
                  severity="success"
                  class="ml-2"
                />
              </div>
            </template>
          </Column>

          <Column field="updated_at" header="Last Updated" sortable>
            <template #body="{ data }">
              <span class="text-sm text-gray-600 dark:text-gray-400">
                {{ formatDate(data.updated_at) }}
              </span>
            </template>
          </Column>

          <Column header="Actions" :style="{ width: '300px' }">
            <template #body="{ data }">
              <div class="flex gap-2">
                <Button
                  v-if="editingConfigId === data.id"
                  icon="pi pi-check"
                  size="small"
                  severity="success"
                  v-tooltip.top="'Save'"
                  @click="saveRename(band.id, data.id)"
                  text
                  rounded
                />
                <Button
                  v-if="editingConfigId === data.id"
                  icon="pi pi-times"
                  size="small"
                  severity="secondary"
                  v-tooltip.top="'Cancel'"
                  @click="cancelRename"
                  text
                  rounded
                />
                <Button
                  v-if="editingConfigId !== data.id"
                  icon="pi pi-pencil"
                  size="small"
                  v-tooltip.top="'Rename'"
                  @click="startRename(data)"
                  text
                  rounded
                />
                <Button
                  v-if="!data.is_active"
                  icon="pi pi-check-circle"
                  size="small"
                  severity="success"
                  v-tooltip.top="'Set as Active'"
                  @click="setActive(band.id, data.id)"
                  text
                  rounded
                />
                <Button
                  icon="pi pi-sitemap"
                  size="small"
                  severity="info"
                  v-tooltip.top="'Open Flow Editor'"
                  @click="openFlowEditor(band.id, data.id)"
                  text
                  rounded
                />
                <Button
                  icon="pi pi-copy"
                  size="small"
                  v-tooltip.top="'Duplicate'"
                  @click="duplicate(band.id, data.id)"
                  text
                  rounded
                />
                <Button
                  v-if="band.payout_configs.length > 1"
                  icon="pi pi-trash"
                  size="small"
                  severity="danger"
                  v-tooltip.top="'Delete'"
                  @click="confirmDelete(band, data)"
                  text
                  rounded
                />
              </div>
            </template>
          </Column>
        </DataTable>

        <div v-if="band.payout_configs.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
          <i class="pi pi-inbox text-5xl mb-4 block opacity-50" />
          <p class="text-lg mb-2">No configurations yet</p>
          <p class="text-sm">Create your first payout configuration to get started</p>
        </div>
      </div>
    </div>

    <!-- Create Configuration Dialog -->
    <Dialog
      v-model:visible="showCreateDialog"
      header="Create Payout Configuration"
      :modal="true"
      :closable="true"
      :style="{ width: '500px' }"
    >
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Band
          </label>
          <Dropdown
            v-model="newConfig.bandId"
            :options="bands"
            optionLabel="name"
            optionValue="id"
            placeholder="Select a band"
            class="w-full"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Configuration Name
          </label>
          <InputText
            v-model="newConfig.name"
            placeholder="e.g., Standard Split, Tour Rates, Festival Setup"
            class="w-full"
            @keyup.enter="createConfiguration"
          />
        </div>
      </div>

      <template #footer>
        <Button label="Cancel" text @click="showCreateDialog = false" />
        <Button
          label="Create"
          @click="createConfiguration"
          :disabled="!newConfig.bandId || !newConfig.name.trim()"
        />
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
            {{ configToDelete?.config?.name }}
          </div>
          <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            {{ configToDelete?.band?.name }}
          </div>
        </div>
        <p class="text-sm text-red-600 dark:text-red-400">
          <i class="pi pi-exclamation-triangle mr-1" />
          This action cannot be undone.
        </p>
      </div>

      <template #footer>
        <Button label="Cancel" text @click="showDeleteDialog = false" />
        <Button
          label="Delete"
          severity="danger"
          @click="deleteConfiguration"
          :loading="deleting"
        />
      </template>
    </Dialog>
  </FinanceLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import FinanceLayout from './Layout/FinanceLayout.vue'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Tag from 'primevue/tag'

const props = defineProps({
  bands: {
    type: Array,
    required: true
  }
})

const toast = useToast()
const showCreateDialog = ref(false)
const showDeleteDialog = ref(false)
const configToDelete = ref(null)
const deleting = ref(false)
const editingConfigId = ref(null)
const editingName = ref('')

const newConfig = reactive({
  bandId: props.bands[0]?.id || null,
  name: ''
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

const rowClass = (data) => {
  return data.is_active ? 'bg-green-50 dark:bg-green-900/10' : ''
}

const startRename = (config) => {
  editingConfigId.value = config.id
  editingName.value = config.name
}

const cancelRename = () => {
  editingConfigId.value = null
  editingName.value = ''
}

const saveRename = (bandId, configId) => {
  if (!editingName.value.trim()) {
    toast.add({
      severity: 'warn',
      summary: 'Invalid Name',
      detail: 'Configuration name cannot be empty',
      life: 3000
    })
    return
  }

  router.put(
    route('finances.payoutConfig.update', [bandId, configId]),
    {
      name: editingName.value.trim()
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        toast.add({
          severity: 'success',
          summary: 'Configuration Renamed',
          detail: 'Configuration name updated successfully',
          life: 3000
        })
        cancelRename()
      },
      onError: (errors) => {
        toast.add({
          severity: 'error',
          summary: 'Failed to Rename',
          detail: errors.message || 'Could not update configuration name',
          life: 5000
        })
      }
    }
  )
}

const createConfiguration = () => {
  if (!newConfig.bandId || !newConfig.name.trim()) return

  router.post(
    route('finances.payoutConfig.store', newConfig.bandId),
    {
      name: newConfig.name.trim(),
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
      preserveScroll: true,
      onSuccess: () => {
        toast.add({
          severity: 'success',
          summary: 'Configuration Created',
          detail: `"${newConfig.name}" has been created`,
          life: 3000
        })
        newConfig.name = ''
        showCreateDialog.value = false
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

const setActive = (bandId, configId) => {
  router.post(
    route('finances.payoutConfig.setActive', [bandId, configId]),
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        toast.add({
          severity: 'success',
          summary: 'Configuration Activated',
          detail: 'This configuration is now active',
          life: 3000
        })
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

const openFlowEditor = (bandId, configId) => {
  // First set as active if not already
  const band = props.bands.find(b => b.id === bandId)
  const config = band.payout_configs.find(c => c.id === configId)

  if (!config.is_active) {
    router.post(
      route('finances.payoutConfig.setActive', [bandId, configId]),
      {},
      {
        onSuccess: () => {
          router.visit(route('finances.payoutFlow.edit', bandId))
        }
      }
    )
  } else {
    router.visit(route('finances.payoutFlow.edit', bandId))
  }
}

const duplicate = (bandId, configId) => {
  router.post(
    route('finances.payoutConfig.duplicate', [bandId, configId]),
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        toast.add({
          severity: 'success',
          summary: 'Configuration Duplicated',
          detail: 'A copy has been created',
          life: 3000
        })
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

const confirmDelete = (band, config) => {
  configToDelete.value = { band, config }
  showDeleteDialog.value = true
}

const deleteConfiguration = () => {
  if (!configToDelete.value) return

  deleting.value = true

  router.delete(
    route('finances.payoutConfig.destroy', [
      configToDelete.value.band.id,
      configToDelete.value.config.id
    ]),
    {
      preserveScroll: true,
      onSuccess: () => {
        toast.add({
          severity: 'success',
          summary: 'Configuration Deleted',
          detail: `"${configToDelete.value.config.name}" has been deleted`,
          life: 3000
        })
        showDeleteDialog.value = false
        configToDelete.value = null
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
