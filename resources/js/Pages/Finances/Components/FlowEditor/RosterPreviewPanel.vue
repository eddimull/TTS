<template>
  <div class="absolute top-4 right-4 z-[60] w-96 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700">
    <!-- Header -->
    <div class="flex items-center justify-between p-3 border-b border-gray-200 dark:border-gray-700">
      <div class="flex items-center gap-2">
        <i class="pi pi-users text-blue-500" />
        <h3 class="font-semibold text-gray-800 dark:text-gray-200">Preview Roster</h3>
      </div>
      <Button
        icon="pi pi-times"
        text
        rounded
        size="small"
        @click="emit('close')"
        v-tooltip.left="'Close panel'"
      />
    </div>

    <!-- Roster Selector -->
    <div class="p-3 border-b border-gray-200 dark:border-gray-700">
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Load from Roster
      </label>
      <div class="flex gap-2">
        <Select
          v-model="selectedRosterId"
          :options="rosters"
          option-label="name"
          option-value="id"
          placeholder="Choose a roster"
          class="flex-1"
        >
          <template #option="slotProps">
            <div class="flex items-center justify-between">
              <div>
                <div class="font-medium">{{ slotProps.option.name }}</div>
                <div class="text-xs text-gray-500">
                  {{ slotProps.option.members?.length || 0 }} members
                  <span v-if="slotProps.option.is_default" class="ml-2 text-blue-500">(Default)</span>
                </div>
              </div>
            </div>
          </template>
        </Select>
        <Button
          icon="pi pi-download"
          @click="loadFromRoster"
          :disabled="!selectedRosterId"
          v-tooltip.top="'Load roster'"
        />
      </div>
    </div>

    <!-- Role Configuration -->
    <div class="p-3 border-b border-gray-200 dark:border-gray-700">
      <div class="flex items-center justify-between mb-2">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
          Add Role
        </label>
      </div>

      <div class="flex gap-2">
        <Select
          v-model="selectedRole"
          :options="availableRoles"
          option-label="name"
          option-value="id"
          placeholder="Select role"
          class="flex-1"
        >
          <template #empty>
            <div class="p-3 text-center text-sm text-gray-500">
              No roles found. Add roles in band settings.
            </div>
          </template>
        </Select>
        <InputNumber
          v-model="newMemberCount"
          :min="1"
          :max="20"
          placeholder="#"
          class="w-20"
        />
        <Button
          icon="pi pi-plus"
          @click="addRole"
          :disabled="!selectedRole || !newMemberCount"
          v-tooltip.top="'Add members'"
        />
      </div>
    </div>

    <!-- Role Slots List -->
    <div class="p-3 max-h-96 overflow-y-auto">
      <div class="flex items-center justify-between mb-2">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
          Configured Roles ({{ totalMembers }} members)
        </label>
        <Button
          label="Clear All"
          icon="pi pi-trash"
          size="small"
          text
          severity="danger"
          @click="clearAll"
          :disabled="roleSlots.length === 0"
        />
      </div>

      <div v-if="roleSlots.length === 0" class="text-center py-6 text-gray-500 dark:text-gray-400">
        <i class="pi pi-users text-3xl mb-2 block" />
        <p class="text-sm">No roles configured</p>
        <p class="text-xs mt-1">Add roles above to preview payouts</p>
      </div>

      <div v-else class="space-y-2">
        <div
          v-for="(slot, index) in roleSlots"
          :key="index"
          class="p-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50"
        >
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="font-medium text-gray-800 dark:text-gray-200">
                {{ getRoleName(slot.band_role_id) }}
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-2">
                <span class="px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">
                  {{ slot.count }} {{ slot.count === 1 ? 'member' : 'members' }}
                </span>
                <span class="px-1.5 py-0.5 rounded" :class="slot.type === 'substitute' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400' : 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400'">
                  {{ slot.type }}
                </span>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <InputNumber
                v-model="slot.count"
                :min="1"
                :max="20"
                size="small"
                class="w-16"
                @update:model-value="handleUpdate"
              />
              <Button
                icon="pi pi-trash"
                text
                rounded
                size="small"
                severity="danger"
                @click="removeRole(index)"
              />
            </div>
          </div>

          <!-- Type toggle -->
          <div class="mt-2 flex gap-1">
            <Button
              label="Member"
              :outlined="slot.type !== 'member'"
              size="small"
              class="flex-1"
              @click="slot.type = 'member'; handleUpdate()"
            />
            <Button
              label="Sub"
              :outlined="slot.type !== 'substitute'"
              size="small"
              class="flex-1"
              @click="slot.type = 'substitute'; handleUpdate()"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="p-3 border-t border-gray-200 dark:border-gray-700 flex gap-2">
      <Button
        label="Reset"
        icon="pi pi-refresh"
        size="small"
        outlined
        class="flex-1"
        @click="resetToOriginal"
      />
      <Button
        label="Generate Preview"
        icon="pi pi-check"
        size="small"
        class="flex-1"
        @click="generatePreview"
        :disabled="roleSlots.length === 0"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import Button from 'primevue/button'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'

const props = defineProps({
  band: {
    type: Object,
    required: true
  },
  availableRoles: {
    type: Array,
    default: () => []
  },
  initialMembers: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'update'])

// Rosters
const rosters = computed(() => props.band.rosters || [])
const selectedRosterId = ref(null)

// Find default roster
const defaultRoster = computed(() => {
  return rosters.value.find(r => r.is_default) || rosters.value.find(r => r.is_active) || rosters.value[0]
})

// Initialize selected roster
if (defaultRoster.value) {
  selectedRosterId.value = defaultRoster.value.id
}

// Role slots state (instead of individual members)
const roleSlots = ref([])
const originalRoleSlots = ref([])
const selectedRole = ref(null)
const newMemberCount = ref(1)

// Total member count
const totalMembers = computed(() => {
  return roleSlots.value.reduce((sum, slot) => sum + slot.count, 0)
})

// Get role name by ID
const getRoleName = (roleId) => {
  const role = props.availableRoles.find(r => r.id === roleId)
  return role?.name || 'Unknown Role'
}

// Load roster configuration
const loadFromRoster = () => {
  const selectedRoster = rosters.value.find(r => r.id === selectedRosterId.value)
  if (!selectedRoster || !selectedRoster.members) return

  // Group members by role
  const roleGroups = {}
  selectedRoster.members
    .filter(m => m.is_active && m.band_role_id)
    .forEach(member => {
      const key = `${member.band_role_id}-${member.user_id ? 'member' : 'substitute'}`
      if (!roleGroups[key]) {
        roleGroups[key] = {
          band_role_id: member.band_role_id,
          role: member.role,
          type: member.user_id ? 'member' : 'substitute',
          count: 0
        }
      }
      roleGroups[key].count++
    })

  roleSlots.value = Object.values(roleGroups)
  handleUpdate()
}

// Add a role slot
const addRole = () => {
  if (!selectedRole.value || !newMemberCount.value) return

  // Check if this role already exists
  const existingSlot = roleSlots.value.find(
    slot => slot.band_role_id === selectedRole.value
  )

  if (existingSlot) {
    existingSlot.count += newMemberCount.value
  } else {
    const role = props.availableRoles.find(r => r.id === selectedRole.value)
    roleSlots.value.push({
      band_role_id: selectedRole.value,
      role: role?.name || 'Unknown',
      type: 'member',
      count: newMemberCount.value
    })
  }

  // Reset
  selectedRole.value = null
  newMemberCount.value = 1

  handleUpdate()
}

// Remove a role slot
const removeRole = (index) => {
  roleSlots.value.splice(index, 1)
  handleUpdate()
}

// Clear all
const clearAll = () => {
  roleSlots.value = []
  handleUpdate()
}

// Generate preview members from role slots
const generatePreview = () => {
  const members = []

  roleSlots.value.forEach((slot) => {
    for (let i = 0; i < slot.count; i++) {
      members.push({
        roster_member_id: null,
        user_id: null,
        name: `${slot.role} ${i + 1}`,
        role: slot.role,
        band_role_id: slot.band_role_id,
        type: slot.type,
        eventsAttended: 1,
        totalEvents: 1,
        customPayout: null
      })
    }
  })

  emit('update', members)
}

// Handle update (live preview)
const handleUpdate = () => {
  generatePreview()
}

// Reset to original
const resetToOriginal = () => {
  roleSlots.value = [...originalRoleSlots.value]
  handleUpdate()
}

// Initialize from initial members if provided
if (props.initialMembers.length > 0) {
  const roleGroups = {}
  props.initialMembers.forEach(member => {
    if (!member.band_role_id) return

    const key = `${member.band_role_id}-${member.type}`
    if (!roleGroups[key]) {
      roleGroups[key] = {
        band_role_id: member.band_role_id,
        role: member.role,
        type: member.type,
        count: 0
      }
    }
    roleGroups[key].count++
  })

  roleSlots.value = Object.values(roleGroups)
  originalRoleSlots.value = [...roleSlots.value]
}
</script>
