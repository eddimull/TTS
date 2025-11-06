<template>
  <div>
    <SectionHeader
      title="Member-Specific Payouts"
      icon="pi-users"
    />
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
      Configure individual payout types and values for each band member
    </p>
  
    <div
      v-if="band.owners && band.owners.length > 0"
      class="mb-6"
    >
      <SectionHeader
        title="Owners"
        icon="pi-star-fill"
        icon-color="#f59e0b"
      />
      <div class="space-y-2">
        <PayoutMemberRow
          v-for="owner in band.owners"
          :key="'owner-' + owner.id"
          :member="{ ...getMemberConfig(owner.user.id, 'owner'), name: owner.user.name }"
          :payout-types="individualPayoutTypes"
          background-class="bg-blue-50 dark:bg-blue-900/20"
          :editable="false"
          :show-checkmark="true"
          @update:member="updateMemberConfig(owner.user.id, 'owner', $event)"
        />
      </div>
    </div>

    <div
      v-if="band.members && band.members.length > 0"
      class="mb-6"
    >
      <SectionHeader
        title="Band Members"
        icon="pi-user"
        icon-color="#3b82f6"
      />
      <div class="space-y-2">
        <PayoutMemberRow
          v-for="member in band.members"
          :key="'member-' + member.id"
          :member="{ ...getMemberConfig(member.user.id, 'member'), name: member.user.name }"
          :payout-types="individualPayoutTypes"
          background-class="bg-green-50 dark:bg-green-900/20"
          :editable="false"
          :show-checkmark="true"
          @update:member="updateMemberConfig(member.user.id, 'member', $event)"
        />
      </div>
    </div>

    <div class="mb-6">
      <SectionHeader
        title="Production Members"
        icon="pi-wrench"
        icon-color="#f97316"
      />
      <div class="space-y-2">
        <PayoutMemberRow
          v-for="(prodMember, index) in config.production_member_types"
          :key="'prod-' + index"
          :member="prodMember"
          :payout-types="[{label: 'Percentage', value: 'percentage'}, {label: 'Fixed', value: 'fixed'}]"
          background-class="bg-orange-50 dark:bg-orange-900/20"
          name-placeholder="e.g., Sound Engineer"
          :removable="true"
          @update:member="updateProductionMember(index, $event)"
          @remove="removeProductionMember(index)"
        />
        <Button
          label="Add Production Member"
          icon="pi pi-plus"
          severity="secondary"
          size="small"
          text
          @click="addProductionMember"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import SectionHeader from '@/Components/SectionHeader.vue'
import PayoutMemberRow from '@/Components/PayoutMemberRow.vue'
import Button from 'primevue/button'

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

const individualPayoutTypes = [
  { label: 'Percentage', value: 'percentage' },
  { label: 'Fixed Amount', value: 'fixed' },
  { label: 'Equal Split', value: 'equal_split' }
]

const getMemberConfig = (userId, memberType) => {
  const memberConfigs = props.config.member_specific_config || []
  const memberConfig = memberConfigs.find(m => m.user_id === userId && m.member_type === memberType)
  
  if (memberConfig) return memberConfig
  
  let memberName = ''
  if (memberType === 'owner') {
    const owner = props.band.owners.find(o => o.user.id === userId)
    memberName = owner ? owner.user.name : ''
  } else if (memberType === 'member') {
    const member = props.band.members.find(m => m.user.id === userId)
    memberName = member ? member.user.name : ''
  }
  
  return {
    user_id: userId,
    member_type: memberType,
    name: memberName,
    payout_type: 'equal_split',
    value: 0
  }
}

const updateMemberConfig = (userId, memberType, updates) => {
  const memberConfigs = [...(props.config.member_specific_config || [])]
  const index = memberConfigs.findIndex(m => m.user_id === userId && m.member_type === memberType)
  
  if (index >= 0) {
    memberConfigs[index] = { ...memberConfigs[index], ...updates }
  } else {
    memberConfigs.push({ ...getMemberConfig(userId, memberType), ...updates })
  }
  
  emit('update', { member_specific_config: memberConfigs })
}

const updateProductionMember = (index, updates) => {
  const productionMembers = [...(props.config.production_member_types || [])]
  productionMembers[index] = { ...productionMembers[index], ...updates }
  emit('update', { production_member_types: productionMembers })
}

const removeProductionMember = (index) => {
  const productionMembers = [...(props.config.production_member_types || [])]
  productionMembers.splice(index, 1)
  emit('update', { production_member_types: productionMembers })
}

const addProductionMember = () => {
  const productionMembers = [...(props.config.production_member_types || [])]
  productionMembers.push({ name: '', type: 'fixed', value: 500 })
  emit('update', { production_member_types: productionMembers })
}
</script>
