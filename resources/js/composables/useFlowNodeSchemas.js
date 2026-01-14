/**
 * Unified node type schemas for the payout flow editor
 *
 * This defines the complete data structure for each node type in the flow diagram.
 */

/**
 * Get default node data based on node type
 * @param {string} nodeType - The type of node to create
 * @returns {Object} Default data structure for the node
 */
export function getDefaultNodeData(nodeType) {
  const baseData = {
    deactivated: false,
    customLabel: null
  }

  switch (nodeType) {
    case 'income':
      return {
        ...baseData,
        amount: 5000,
        label: 'Total Income',
        editable: true
      }

    case 'bandCut':
      return {
        ...baseData,
        cutType: 'percentage', // 'percentage' | 'fixed' | 'tiered'
        value: 10,
        tierConfig: null
      }

    case 'conditional':
      return {
        ...baseData,
        label: 'Condition',
        conditionType: 'bookingPrice', // 'bookingPrice' | 'eventType' | 'eventCount' | 'dayOfWeek' | 'memberCount' | 'eventMultiplier'
        operator: '>', // '>' | '<' | '>=' | '<=' | '==' | '!='
        value: 1000,
        // Conditional nodes have two output handles: 'true' and 'false'
      }

    case 'payoutGroup':
      return {
        ...baseData,
        label: 'Payout Group',

        // SOURCE: Where recipients come from
        sourceType: 'roster', // 'roster' | 'paymentGroup' | 'specific' | 'roles' | 'allMembers'

        // Roster-based source configuration
        rosterConfig: {
          useAttendanceWeighting: true,
          filterByRole: null, // Array of role strings or null for all
          excludeRoles: [], // Array of roles to exclude
          minEventsToQualify: 1,
          includeSubstitutes: true
        },

        // Payment group source
        paymentGroupId: null,

        // Specific members source
        specificMembers: [], // [{ user_id: 1, roster_member_id: null, name: 'John' }]

        // Role-based slots
        roleSlots: [], // [{ role: 'Drummer', required: true, fallbackToRoster: true }]

        // All members configuration
        allMembersConfig: {
          includeOwners: true,
          includeMembers: true,
          includeProduction: false,
          productionCount: 0
        },

        // DISTRIBUTION: How to split among recipients
        distributionMode: 'equal_split', // 'equal_split' | 'percentage' | 'fixed' | 'tiered' | 'weighted'

        // Per-member allocations (for percentage/fixed modes)
        memberAllocations: [], // [{ identifier: 'user_1', type: 'percentage', value: 50 }]

        // Tiered distribution config
        tierConfig: null, // [{ min: 0, max: 1000, type: 'percentage', value: 10 }]

        // INCOMING ALLOCATION: How much this group gets
        incomingAllocationType: 'remainder', // 'percentage' | 'fixed' | 'remainder'
        incomingAllocationValue: 0, // Used for percentage/fixed

        // OVERRIDES: Custom payout handling
        respectCustomPayouts: true, // Honor EventMember.payout_amount overrides

        // MINIMUM: Minimum payout per member
        minimumPayout: 0,

        // VISUALIZATION: Runtime data (not persisted)
        _visualizationData: null
      }

    default:
      return baseData
  }
}

/**
 * Condition type options for conditional nodes
 */
export const CONDITION_TYPES = [
  {
    value: 'bookingPrice',
    label: 'Booking Price',
    description: 'Total booking price in dollars',
    operators: ['>', '<', '>=', '<=', '==', '!='],
    inputType: 'currency'
  },
  {
    value: 'eventCount',
    label: 'Event Count',
    description: 'Number of events in the booking',
    operators: ['>', '<', '>=', '<=', '==', '!='],
    inputType: 'number'
  },
  {
    value: 'eventType',
    label: 'Event Type',
    description: 'Type of event (performance, rehearsal, etc.)',
    operators: ['==', '!='],
    inputType: 'select',
    options: ['performance', 'rehearsal', 'recording', 'other']
  },
  {
    value: 'dayOfWeek',
    label: 'Day of Week',
    description: 'Day of the week for the event',
    operators: ['==', '!='],
    inputType: 'select',
    options: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
  },
  {
    value: 'memberCount',
    label: 'Member Count',
    description: 'Number of members attending',
    operators: ['>', '<', '>=', '<=', '==', '!='],
    inputType: 'number'
  },
  {
    value: 'eventMultiplier',
    label: 'Event Value Multiplier',
    description: 'Event payout value multiplier',
    operators: ['>', '<', '>=', '<=', '==', '!='],
    inputType: 'number'
  }
]

/**
 * Source type options for payout groups
 */
export const SOURCE_TYPES = [
  {
    value: 'roster',
    label: 'Event Roster',
    description: 'Dynamic members from event attendance',
    icon: 'pi-calendar',
    supportsAttendanceWeighting: true
  },
  {
    value: 'paymentGroup',
    label: 'Payment Group',
    description: 'Pre-defined payment group',
    icon: 'pi-users',
    requiresSelection: true
  },
  {
    value: 'specific',
    label: 'Specific Members',
    description: 'Manually selected members',
    icon: 'pi-user-edit',
    allowsCustomSelection: true
  },
  {
    value: 'roles',
    label: 'Role Slots',
    description: 'Role-based placeholders',
    icon: 'pi-briefcase',
    allowsRoleConfiguration: true
  },
  {
    value: 'allMembers',
    label: 'All Band Members',
    description: 'All owners and members',
    icon: 'pi-users',
    usesBandMembers: true
  }
]

/**
 * Distribution mode options
 */
export const DISTRIBUTION_MODES = [
  {
    value: 'equal_split',
    label: 'Equal Split',
    description: 'Divide equally among all recipients',
    icon: 'pi-equals'
  },
  {
    value: 'percentage',
    label: 'Percentage',
    description: 'Custom percentage per recipient',
    icon: 'pi-percentage',
    requiresPerMemberConfig: true
  },
  {
    value: 'fixed',
    label: 'Fixed Amount',
    description: 'Fixed dollar amount per recipient',
    icon: 'pi-dollar',
    requiresPerMemberConfig: true
  },
  {
    value: 'tiered',
    label: 'Tiered',
    description: 'Different splits based on booking price',
    icon: 'pi-chart-bar',
    requiresTierConfig: true
  },
  {
    value: 'weighted',
    label: 'Weighted',
    description: 'Custom weights per recipient',
    icon: 'pi-sliders-h',
    requiresPerMemberConfig: true
  }
]

/**
 * Validate node data structure
 * @param {Object} node - Node to validate
 * @returns {Object} { valid: boolean, errors: string[] }
 */
export function validateNodeData(node) {
  const errors = []

  switch (node.type) {
    case 'income':
      if (!node.data.amount || node.data.amount <= 0) {
        errors.push('Income amount must be greater than 0')
      }
      break

    case 'bandCut':
      if (node.data.cutType === 'percentage' && (node.data.value < 0 || node.data.value > 100)) {
        errors.push('Band cut percentage must be between 0 and 100')
      }
      if (node.data.cutType === 'fixed' && node.data.value < 0) {
        errors.push('Band cut amount must be 0 or greater')
      }
      break

    case 'conditional':
      if (!node.data.conditionType) {
        errors.push('Condition type is required')
      }
      if (!node.data.operator) {
        errors.push('Operator is required')
      }
      if (node.data.value === null || node.data.value === undefined) {
        errors.push('Comparison value is required')
      }
      break

    case 'payoutGroup':
      if (!node.data.sourceType) {
        errors.push('Source type is required')
      }

      if (node.data.sourceType === 'paymentGroup' && !node.data.paymentGroupId) {
        errors.push('Payment group must be selected')
      }

      if (node.data.sourceType === 'specific' && (!node.data.specificMembers || node.data.specificMembers.length === 0)) {
        errors.push('At least one member must be selected')
      }

      if (node.data.sourceType === 'roles' && (!node.data.roleSlots || node.data.roleSlots.length === 0)) {
        errors.push('At least one role slot must be defined')
      }

      if (node.data.incomingAllocationType !== 'remainder' && (!node.data.incomingAllocationValue || node.data.incomingAllocationValue <= 0)) {
        errors.push('Allocation value must be greater than 0')
      }

      if (node.data.distributionMode === 'percentage') {
        const totalPercentage = node.data.memberAllocations.reduce((sum, m) => sum + (m.type === 'percentage' ? m.value : 0), 0)
        if (totalPercentage > 100) {
          errors.push('Total percentage allocation cannot exceed 100%')
        }
      }

      break
  }

  return {
    valid: errors.length === 0,
    errors
  }
}
