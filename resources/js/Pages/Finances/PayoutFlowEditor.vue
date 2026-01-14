<template>
  <AuthenticatedLayout>
    <Head :title="`Payout Flow Editor - ${band.name}`" />
    <Toast />

    <!-- Full viewport canvas - use fixed positioning with z-index above main -->
    <div class="fixed inset-0 top-[4rem] bg-white dark:bg-gray-800 z-[50]">
      <!-- Configuration Manager in top-left corner -->
      <div class="absolute top-4 left-4 z-[60]">
        <ConfigurationManager
          :band="band"
          :configurations="band.payout_configs || []"
          @configuration-changed="reloadPage"
        />
      </div>

      <FlowCanvas
        :band="band"
        :available-roles="availableRoles"
        :preview-roster-members="previewRosterMembers"
        :initial-flow="initialFlow"
        @save="handleSave"
      />
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/Authenticated.vue'
import Toast from 'primevue/toast'
import FlowCanvas from './Components/FlowEditor/FlowCanvas.vue'
import ConfigurationManager from './Components/FlowEditor/ConfigurationManager.vue'

const props = defineProps({
  band: {
    type: Object,
    required: true
  },
  availableRoles: {
    type: Array,
    default: () => []
  },
  previewRosterMembers: {
    type: Array,
    default: () => []
  }
})

// Compute initial flow from existing config
const initialFlow = computed(() => {
  const config = props.band.active_payout_config

  // If flow_diagram exists, use it
  if (config?.flow_diagram) {
    return config.flow_diagram
  }

  // Start with empty canvas
  return {
    nodes: [],
    edges: [],
    version: '2.0'
  }
})

/**
 * Handle saving the flow diagram
 */
const handleSave = async (flowData) => {
  try {
    // Convert flow to config fields for backward compatibility
    const configData = flowToConfig(flowData)

    // Save to backend
    const configId = props.band.active_payout_config?.id

    if (configId) {
      // Update existing config
      await router.put(
        route('finances.payoutConfig.update', [props.band.id, configId]),
        {
          ...configData,
          flow_diagram: flowData
        },
        {
          preserveState: true,
          preserveScroll: true
        }
      )
    } else {
      // Create new config
      await router.post(
        route('finances.payoutConfig.store', props.band.id),
        {
          name: 'Default Configuration',
          is_active: true,
          ...configData,
          flow_diagram: flowData
        },
        {
          preserveState: true,
          preserveScroll: true
        }
      )
    }
  } catch (error) {
    console.error('Failed to save flow:', error)
    throw error
  }
}

/**
 * Reload the page to reflect configuration changes
 */
const reloadPage = () => {
  router.reload({
    only: ['band'],
    preserveScroll: true
  })
}

/**
 * Convert flow diagram to config fields for backend storage
 */
function flowToConfig(flowData) {
  const { nodes } = flowData

  const config = {
    band_cut_type: 'none',
    band_cut_value: 0,
    band_cut_tier_config: null,
    use_payment_groups: false,
    payment_group_config: [],
    member_payout_type: 'equal_split',
    include_owners: true,
    include_members: true,
    minimum_payout: 0
  }

  // Extract Band Cut
  const bandCutNode = nodes.find(n => n.type === 'bandCut')
  if (bandCutNode) {
    config.band_cut_type = bandCutNode.data.cutType
    config.band_cut_value = bandCutNode.data.value || 0
    if (bandCutNode.data.cutType === 'tiered') {
      config.band_cut_tier_config = bandCutNode.data.tierConfig
    }
  }

  // Extract Payout Groups
  const payoutGroupNodes = nodes.filter(n => n.type === 'payoutGroup')

  if (payoutGroupNodes.length > 0) {
    // Check if any use paymentGroup source type (payment groups mode)
    const paymentGroupSourceNodes = payoutGroupNodes.filter(n => n.data.sourceType === 'paymentGroup')

    if (paymentGroupSourceNodes.length > 0) {
      config.use_payment_groups = true

      // Extract payment groups (in flow order)
      config.payment_group_config = paymentGroupSourceNodes.map(node => ({
        group_id: node.data.paymentGroupId,
        allocation_type: node.data.incomingAllocationType,
        allocation_value: node.data.incomingAllocationValue
      }))
    }

    // Extract settings from first allMembers node
    const allMembersNode = payoutGroupNodes.find(n => n.data.sourceType === 'allMembers')
    if (allMembersNode) {
      const { allMembersConfig } = allMembersNode.data
      config.include_owners = allMembersConfig.includeOwners ?? true
      config.include_members = allMembersConfig.includeMembers ?? true
      config.member_payout_type = allMembersNode.data.distributionMode || 'equal_split'
      config.minimum_payout = allMembersNode.data.minimumPayout || 0
    }
  }

  return config
}
</script>
