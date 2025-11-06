<template>
  <div class="componentPanel shadow-lg rounded-lg p-6">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
        Payment Groups for {{ band.name }}
      </h2>
      <Button 
        label="New Payment Group" 
        icon="pi pi-plus" 
        size="small"
        @click="showCreateGroupDialog = true"
      />
    </div>

    <!-- Payment Groups List -->
    <div
      v-if="band.payment_groups && band.payment_groups.length > 0"
      class="space-y-4"
    >
      <div 
        v-for="group in sortedGroups" 
        :key="group.id"
        class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
      >
        <div class="flex justify-between items-start mb-3">
          <div class="flex-1">
            <div class="flex items-center gap-3">
              <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
                {{ group.name }}
              </h3>
              <span 
                class="px-2 py-1 text-xs rounded-full"
                :class="group.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'"
              >
                {{ group.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>
            <p
              v-if="group.description"
              class="text-sm text-gray-600 dark:text-gray-400 mt-1"
            >
              {{ group.description }}
            </p>
            <div class="text-sm text-gray-500 dark:text-gray-400 mt-2">
              Default Payout: 
              <span class="font-medium">{{ getPayoutTypeLabel(group.default_payout_type) }}</span>
              <span v-if="group.default_payout_value">
                - {{ group.default_payout_type === 'percentage' ? group.default_payout_value + '%' : moneyFormat(group.default_payout_value) }}
              </span>
            </div>
          </div>
          <div class="flex gap-2">
            <Button 
              icon="pi pi-pencil" 
              text
              rounded
              severity="secondary"
              @click="editGroup(group)"
            />
            <Button 
              icon="pi pi-trash" 
              text
              rounded
              severity="danger"
              @click="confirmDeleteGroup(group)"
            />
          </div>
        </div>

        <!-- Members in Group -->
        <div class="mt-4">
          <div class="flex justify-between items-center mb-2">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
              Members ({{ group.users?.length || 0 }})
            </h4>
            <Button 
              label="Add Member" 
              icon="pi pi-user-plus" 
              size="small"
              text
              @click="showAddMemberDialog(group)"
            />
          </div>
          
          <div
            v-if="group.users && group.users.length > 0"
            class="space-y-2"
          >
            <div 
              v-for="user in group.users" 
              :key="user.id"
              class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded"
            >
              <div>
                <div class="font-medium text-gray-800 dark:text-gray-100">
                  {{ user.name }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                  {{ user.pivot.payout_type ? getPayoutTypeLabel(user.pivot.payout_type) : getPayoutTypeLabel(group.default_payout_type) }}
                  <span v-if="user.pivot.payout_value">
                    - {{ user.pivot.payout_type === 'percentage' ? user.pivot.payout_value + '%' : moneyFormat(user.pivot.payout_value) }}
                  </span>
                  <span v-else-if="group.default_payout_value && !user.pivot.payout_type">
                    - {{ group.default_payout_type === 'percentage' ? group.default_payout_value + '%' : moneyFormat(group.default_payout_value) }}
                  </span>
                </div>
                <div
                  v-if="user.pivot.notes"
                  class="text-xs text-gray-400 dark:text-gray-500 mt-1"
                >
                  {{ user.pivot.notes }}
                </div>
              </div>
              <div class="flex gap-2">
                <Button 
                  icon="pi pi-pencil" 
                  text
                  rounded
                  size="small"
                  severity="secondary"
                  @click="editMemberInGroup(group, user)"
                />
                <Button 
                  icon="pi pi-times" 
                  text
                  rounded
                  size="small"
                  severity="danger"
                  @click="removeMemberFromGroup(group, user)"
                />
              </div>
            </div>
          </div>
          <div
            v-else
            class="text-sm text-gray-500 dark:text-gray-400 italic"
          >
            No members in this group
          </div>
        </div>
      </div>
    </div>
    <div
      v-else
      class="text-center py-8 text-gray-500 dark:text-gray-400"
    >
      No payment groups configured. Create one to get started!
    </div>

    <!-- Create/Edit Group Dialog -->
    <Dialog 
      v-model:visible="showCreateGroupDialog" 
      :header="editingGroup ? 'Edit Payment Group' : 'Create Payment Group'"
      :style="{ width: '500px' }"
      modal
    >
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">Group Name</label>
          <InputText
            v-model="groupForm.name"
            class="w-full"
            placeholder="e.g., Sound Crew, Players"
          />
        </div>
        
        <div>
          <label class="block text-sm font-medium mb-2">Description</label>
          <Textarea
            v-model="groupForm.description"
            class="w-full"
            rows="2"
          />
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">Default Payout Type</label>
          <Select 
            v-model="groupForm.default_payout_type" 
            :options="payoutTypes"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>

        <div v-if="groupForm.default_payout_type !== 'equal_split'">
          <label class="block text-sm font-medium mb-2">
            Default Payout Value {{ groupForm.default_payout_type === 'percentage' ? '(%)' : '($)' }}
          </label>
          <InputNumber 
            v-model="groupForm.default_payout_value" 
            class="w-full"
            :min="0"
            :max="groupForm.default_payout_type === 'percentage' ? 100 : undefined"
            :prefix="groupForm.default_payout_type === 'fixed' ? '$' : undefined"
            :suffix="groupForm.default_payout_type === 'percentage' ? '%' : undefined"
          />
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">Display Order</label>
          <InputNumber
            v-model="groupForm.display_order"
            class="w-full"
            :min="0"
          />
        </div>

        <div class="flex items-center gap-2">
          <Checkbox
            v-model="groupForm.is_active"
            input-id="is_active"
            :binary="true"
          />
          <label
            for="is_active"
            class="text-sm font-medium"
          >Active</label>
        </div>
      </div>

      <template #footer>
        <Button
          label="Cancel"
          text
          @click="closeGroupDialog"
        />
        <Button
          :label="editingGroup ? 'Update' : 'Create'"
          :loading="savingGroup"
          @click="saveGroup"
        />
      </template>
    </Dialog>

    <!-- Add/Edit Member Dialog -->
    <Dialog 
      v-model:visible="showMemberDialog" 
      :header="editingMember ? 'Edit Member' : 'Add Member to Group'"
      :style="{ width: '500px' }"
      modal
    >
      <div class="space-y-4">
        <div v-if="!editingMember">
          <label class="block text-sm font-medium mb-2">Select Member</label>
          <Select 
            v-model="memberForm.user_id" 
            :options="availableUsers"
            option-label="name"
            option-value="id"
            class="w-full"
            placeholder="Choose a user"
          />
        </div>
        <div v-else>
          <label class="block text-sm font-medium mb-2">Member</label>
          <InputText
            :value="editingMember.name"
            class="w-full"
            disabled
          />
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">Payout Type (Override)</label>
          <Select 
            v-model="memberForm.payout_type" 
            :options="payoutTypesWithDefault"
            option-label="label"
            option-value="value"
            class="w-full"
          />
        </div>

        <div v-if="memberForm.payout_type && memberForm.payout_type !== 'equal_split'">
          <label class="block text-sm font-medium mb-2">
            Payout Value {{ memberForm.payout_type === 'percentage' ? '(%)' : '($)' }}
          </label>
          <InputNumber 
            v-model="memberForm.payout_value" 
            class="w-full"
            :min="0"
            :max="memberForm.payout_type === 'percentage' ? 100 : undefined"
            :prefix="memberForm.payout_type === 'fixed' ? '$' : undefined"
            :suffix="memberForm.payout_type === 'percentage' ? '%' : undefined"
          />
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">Notes</label>
          <Textarea
            v-model="memberForm.notes"
            class="w-full"
            rows="2"
          />
        </div>
      </div>

      <template #footer>
        <Button
          label="Cancel"
          text
          @click="closeMemberDialog"
        />
        <Button
          :label="editingMember ? 'Update' : 'Add'"
          :loading="savingMember"
          @click="saveMember"
        />
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
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'

const props = defineProps({
  band: {
    type: Object,
    required: true
  }
})

const showCreateGroupDialog = ref(false)
const showMemberDialog = ref(false)
const editingGroup = ref(null)
const editingMember = ref(null)
const selectedGroup = ref(null)
const savingGroup = ref(false)
const savingMember = ref(false)

const groupForm = ref({
  name: '',
  description: '',
  default_payout_type: 'equal_split',
  default_payout_value: null,
  display_order: 0,
  is_active: true
})

const memberForm = ref({
  user_id: null,
  payout_type: null,
  payout_value: null,
  notes: ''
})

const payoutTypes = [
  { label: 'Equal Split', value: 'equal_split' },
  { label: 'Percentage', value: 'percentage' },
  { label: 'Fixed Amount', value: 'fixed' }
]

const payoutTypesWithDefault = [
  { label: 'Use Group Default', value: null },
  ...payoutTypes
]

const sortedGroups = computed(() => {
  if (!props.band.payment_groups) return []
  return [...props.band.payment_groups].sort((a, b) => a.display_order - b.display_order)
})

const availableUsers = computed(() => {
  if (!selectedGroup.value) return []
  
  const groupUserIds = selectedGroup.value.users?.map(u => u.id) || []
  const allUsers = [
    ...(props.band.owners?.map(o => o.user) || []),
    ...(props.band.members?.map(m => m.user) || [])
  ]
  
  return allUsers.filter(user => !groupUserIds.includes(user.id))
})

const moneyFormat = (number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(number)
}

const getPayoutTypeLabel = (type) => {
  const typeObj = payoutTypes.find(t => t.value === type)
  return typeObj ? typeObj.label : type
}

const editGroup = (group) => {
  editingGroup.value = group
  groupForm.value = {
    name: group.name,
    description: group.description,
    default_payout_type: group.default_payout_type,
    default_payout_value: group.default_payout_value,
    display_order: group.display_order,
    is_active: group.is_active
  }
  showCreateGroupDialog.value = true
}

const closeGroupDialog = () => {
  showCreateGroupDialog.value = false
  editingGroup.value = null
  groupForm.value = {
    name: '',
    description: '',
    default_payout_type: 'equal_split',
    default_payout_value: null,
    display_order: 0,
    is_active: true
  }
}

const saveGroup = () => {
  savingGroup.value = true
  
  const url = editingGroup.value
    ? route('finances.paymentGroup.update', { bandId: props.band.id, groupId: editingGroup.value.id })
    : route('finances.paymentGroup.store', { bandId: props.band.id })
  
  const method = editingGroup.value ? 'put' : 'post'
  
  router[method](url, groupForm.value, {
    onFinish: () => {
      savingGroup.value = false
      closeGroupDialog()
    }
  })
}

const confirmDeleteGroup = (group) => {
  if (confirm(`Are you sure you want to delete the "${group.name}" payment group?`)) {
    router.delete(route('finances.paymentGroup.delete', { bandId: props.band.id, groupId: group.id }))
  }
}

const showAddMemberDialog = (group) => {
  selectedGroup.value = group
  showMemberDialog.value = true
}

const editMemberInGroup = (group, user) => {
  selectedGroup.value = group
  editingMember.value = user
  memberForm.value = {
    user_id: user.id,
    payout_type: user.pivot.payout_type,
    payout_value: user.pivot.payout_value,
    notes: user.pivot.notes
  }
  showMemberDialog.value = true
}

const closeMemberDialog = () => {
  showMemberDialog.value = false
  editingMember.value = null
  selectedGroup.value = null
  memberForm.value = {
    user_id: null,
    payout_type: null,
    payout_value: null,
    notes: ''
  }
}

const saveMember = () => {
  savingMember.value = true
  
  const url = editingMember.value
    ? route('finances.paymentGroup.updateUser', { 
        bandId: props.band.id, 
        groupId: selectedGroup.value.id,
        userId: editingMember.value.id
      })
    : route('finances.paymentGroup.addUser', { 
        bandId: props.band.id, 
        groupId: selectedGroup.value.id
      })
  
  const method = editingMember.value ? 'put' : 'post'
  
  router[method](url, memberForm.value, {
    onFinish: () => {
      savingMember.value = false
      closeMemberDialog()
    }
  })
}

const removeMemberFromGroup = (group, user) => {
  if (confirm(`Remove ${user.name} from ${group.name}?`)) {
    router.delete(route('finances.paymentGroup.removeUser', { 
      bandId: props.band.id, 
      groupId: group.id,
      userId: user.id
    }))
  }
}
</script>
