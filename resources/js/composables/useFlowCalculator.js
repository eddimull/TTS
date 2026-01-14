import { ref } from 'vue'

/**
 * Composable for calculating payouts by traversing the flow graph
 * Supports unified payoutGroup and conditional branching
 *
 * @returns {Object} Calculation functions
 */
export function useFlowCalculator() {
  /**
   * Calculate payouts by traversing the flow graph
   *
   * @param {Array} nodes - Flow nodes
   * @param {Array} edges - Flow edges
   * @param {Object} band - Band data with payment groups
   * @param {Object} context - Optional booking context for conditionals and roster
   * @returns {Object} Results with updated nodes/edges
   */
  const calculate = (nodes, edges, band, context = null) => {
    if (!nodes || nodes.length === 0) return null

    // Create working copies
    const nodeMap = new Map(nodes.map(n => [n.id, { ...n, data: { ...n.data } }]))
    const edgeMap = new Map(edges.map(e => [e.id, { ...e }]))

    // Find income node (entry point)
    const incomeNode = nodes.find(n => n.type === 'income')
    if (!incomeNode) return null

    let currentAmount = incomeNode.data.amount || 0

    const results = {
      total_amount: currentAmount,
      band_cut: 0,
      distributable_amount: currentAmount,
      member_payouts: [],
      payment_group_payouts: [],
      total_member_payout: 0,
      remaining: 0,
      flow: []
    }

    // Build adjacency list for graph traversal
    const adjacency = new Map()
    edges.forEach(edge => {
      if (!adjacency.has(edge.source)) {
        adjacency.set(edge.source, [])
      }
      adjacency.get(edge.source).push(edge)
    })

    // Traverse graph using DFS
    const visited = new Set()

    const traverse = (nodeId, amount, branchContext = {}) => {
      // Allow revisiting nodes if coming from different conditional branch
      const visitKey = branchContext.fromConditional
        ? `${nodeId}-${branchContext.branch}`
        : nodeId

      if (visited.has(visitKey)) return amount
      visited.add(visitKey)

      const node = nodeMap.get(nodeId)
      if (!node) return amount

      // Bypass deactivated nodes - pass amount through unchanged
      if (node.data.deactivated) {
        results.flow.push({
          node: node.data.customLabel || node.type,
          status: 'deactivated',
          amount
        })

        // Mark node as bypassed
        node.data = {
          ...node.data,
          input: amount,
          output: amount,
          bypassed: true
        }
        nodeMap.set(nodeId, node)

        // Process outgoing edges with unchanged amount
        const outgoingEdges = adjacency.get(nodeId) || []
        for (const edge of outgoingEdges) {
          edgeMap.set(edge.id, {
            ...edge,
            data: { ...edge.data, amount }
          })
          amount = traverse(edge.target, amount, branchContext)
        }

        return amount
      }

      // Process node based on type
      switch (node.type) {
        case 'income':
          amount = node.data.amount || 0
          node.data = {
            ...node.data,
            output: amount
          }
          nodeMap.set(nodeId, node)
          results.flow.push({ node: 'Income', amount })
          break

        case 'bandCut':
          const bandCut = calculateBandCut(node.data, amount)
          results.band_cut += bandCut
          amount -= bandCut
          results.distributable_amount = amount

          // Update node with calculated values
          node.data = {
            ...node.data,
            input: amount + bandCut,
            output: amount,
            bandCut: bandCut
          }
          nodeMap.set(nodeId, node)

          results.flow.push({ node: 'Band Cut', cut: bandCut, remaining: amount })
          break

        case 'conditional':
          // Evaluate condition
          const conditionResult = evaluateCondition(node.data, context)

          node.data = {
            ...node.data,
            input: amount,
            conditionResult: conditionResult,
            trueOutput: null,
            falseOutput: null
          }

          // Find true and false branches
          const outgoingEdges = adjacency.get(nodeId) || []
          const trueEdge = outgoingEdges.find(e => e.sourceHandle === 'true')
          const falseEdge = outgoingEdges.find(e => e.sourceHandle === 'false')

          results.flow.push({
            node: `Conditional: ${node.data.customLabel || 'unnamed'}`,
            condition: formatCondition(node.data),
            result: conditionResult,
            amount
          })

          // Traverse the matching branch
          if (conditionResult && trueEdge) {
            edgeMap.set(trueEdge.id, {
              ...trueEdge,
              data: { ...trueEdge.data, amount, active: true }
            })
            amount = traverse(trueEdge.target, amount, { fromConditional: nodeId, branch: 'true' })
            node.data.trueOutput = amount
          } else if (!conditionResult && falseEdge) {
            edgeMap.set(falseEdge.id, {
              ...falseEdge,
              data: { ...falseEdge.data, amount, active: true }
            })
            amount = traverse(falseEdge.target, amount, { fromConditional: nodeId, branch: 'false' })
            node.data.falseOutput = amount
          }

          // Mark the non-taken branch as inactive
          if (conditionResult && falseEdge) {
            edgeMap.set(falseEdge.id, {
              ...falseEdge,
              data: { ...falseEdge.data, amount: 0, active: false }
            })
          } else if (!conditionResult && trueEdge) {
            edgeMap.set(trueEdge.id, {
              ...trueEdge,
              data: { ...trueEdge.data, amount: 0, active: false }
            })
          }

          nodeMap.set(nodeId, node)
          return amount

        case 'payoutGroup':
          const groupResult = calculatePayoutGroup(node.data, amount, band, context)

          if (groupResult.payouts.length > 0) {
            results.member_payouts.push(...groupResult.payouts)
            results.total_member_payout += groupResult.allocated
          }

          if (groupResult.group_name) {
            results.payment_group_payouts.push({
              group_name: groupResult.group_name,
              allocated: groupResult.allocated,
              member_count: groupResult.payouts.length
            })
          }

          // Update node with calculated values
          node.data = {
            ...node.data,
            input: amount,
            allocated: groupResult.allocated,
            output: groupResult.remaining,
            _visualizationData: {
              actualMembers: groupResult.payouts,
              totalAllocated: groupResult.allocated
            }
          }
          nodeMap.set(nodeId, node)

          amount = groupResult.remaining

          results.flow.push({
            node: `Payout Group: ${node.data.customLabel || node.data.label}`,
            sourceType: node.data.sourceType,
            allocated: groupResult.allocated,
            memberCount: groupResult.payouts.length,
            remaining: amount
          })
          break
      }

      // Process outgoing edges (for non-conditional nodes)
      if (node.type !== 'conditional') {
        const outgoingEdges = adjacency.get(nodeId) || []
        for (const edge of outgoingEdges) {
          // Update edge with amount data
          edgeMap.set(edge.id, {
            ...edge,
            data: { ...edge.data, amount }
          })

          // Continue traversal
          amount = traverse(edge.target, amount, branchContext)
        }
      }

      return amount
    }

    // Start traversal from income node
    const finalAmount = traverse(incomeNode.id, currentAmount)
    results.remaining = finalAmount

    return {
      results,
      updatedNodes: Array.from(nodeMap.values()),
      updatedEdges: Array.from(edgeMap.values())
    }
  }

  /**
   * Calculate band cut from node data
   */
  const calculateBandCut = (nodeData, amount) => {
    const { cutType, value, tierConfig } = nodeData

    switch (cutType) {
      case 'percentage':
        return (amount * (value || 0)) / 100
      case 'fixed':
        return value || 0
      case 'tiered':
        const tier = findApplicableTier(amount, tierConfig)
        if (!tier) return 0
        return tier.type === 'percentage'
          ? (amount * tier.value) / 100
          : tier.value
      case 'none':
      default:
        return 0
    }
  }

  /**
   * Evaluate a conditional node's condition
   */
  const evaluateCondition = (nodeData, context) => {
    const { conditionType, operator, value } = nodeData

    // If no context, we can't evaluate - default to true
    if (!context) {
      return true
    }

    let actualValue

    // Extract the actual value based on condition type
    switch (conditionType) {
      case 'bookingPrice':
        actualValue = context.booking?.price || context.price || 0
        break
      case 'eventCount':
        actualValue = context.booking?.events?.length || context.eventCount || 1
        break
      case 'eventType':
        actualValue = context.event?.type || context.eventType || 'performance'
        break
      case 'dayOfWeek':
        const date = context.event?.date || context.date
        if (date) {
          const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
          actualValue = dayNames[new Date(date).getDay()]
        } else {
          actualValue = null
        }
        break
      case 'memberCount':
        actualValue = context.memberCount || 0
        break
      case 'eventMultiplier':
        actualValue = context.event?.payout_value_multiplier || context.multiplier || 1.0
        break
      default:
        actualValue = null
    }

    // Evaluate operator
    switch (operator) {
      case '>':
        return actualValue > value
      case '<':
        return actualValue < value
      case '>=':
        return actualValue >= value
      case '<=':
        return actualValue <= value
      case '==':
        return actualValue == value // Intentional == for type coercion
      case '!=':
        return actualValue != value
      default:
        return true
    }
  }

  /**
   * Format condition for display
   */
  const formatCondition = (nodeData) => {
    const { conditionType, operator, value } = nodeData
    return `${conditionType} ${operator} ${value}`
  }

  /**
   * Calculate unified payout group distribution
   */
  const calculatePayoutGroup = (nodeData, availableAmount, band, context) => {
    const {
      sourceType,
      incomingAllocationType,
      incomingAllocationValue,
      distributionMode,
      fixedAmountPerMember,
      minimumPayout,
      respectCustomPayouts
    } = nodeData

    // Step 1: Get recipients based on source type (need this first for fixed per-member calculations)
    const recipients = getRecipients(nodeData, band, context)

    if (recipients.length === 0) {
      return {
        group_name: nodeData.customLabel || nodeData.label,
        allocated: 0,
        remaining: availableAmount,
        payouts: []
      }
    }

    // Step 2: Calculate how much this group gets from incoming amount
    let groupAllocation = 0

    // Special case: if using fixed distribution with fixedAmountPerMember, auto-calculate total needed
    if (distributionMode === 'fixed' && fixedAmountPerMember > 0) {
      groupAllocation = Math.min(recipients.length * fixedAmountPerMember, availableAmount)
    } else {
      // Normal allocation logic
      switch (incomingAllocationType) {
        case 'remainder':
          groupAllocation = availableAmount
          break
        case 'percentage':
          groupAllocation = (availableAmount * (incomingAllocationValue || 0)) / 100
          break
        case 'fixed':
          groupAllocation = Math.min(incomingAllocationValue || 0, availableAmount)
          break
      }
    }

    // Step 3: Distribute allocation among recipients
    const payouts = distributeAmongRecipients(
      recipients,
      groupAllocation,
      distributionMode,
      nodeData,
      minimumPayout,
      respectCustomPayouts
    )

    const totalAllocated = payouts.reduce((sum, p) => sum + p.amount, 0)
    const remaining = availableAmount - totalAllocated

    return {
      group_name: nodeData.customLabel || nodeData.label,
      allocated: totalAllocated,
      remaining: Math.max(0, remaining),
      payouts
    }
  }

  /**
   * Get recipients based on source type
   */
  const getRecipients = (nodeData, band, context) => {
    const { sourceType } = nodeData
    const recipients = []

    switch (sourceType) {
      case 'roster':
        // Get recipients from event roster with attendance data
        if (context?.eventMembers) {
          const { rosterConfig } = nodeData

          context.eventMembers.forEach(member => {
            // Filter by role if specified
            if (rosterConfig.filterByRole && rosterConfig.filterByRole.length > 0) {
              if (!rosterConfig.filterByRole.includes(member.role)) {
                return
              }
            }

            // Exclude roles if specified
            if (rosterConfig.excludeRoles && rosterConfig.excludeRoles.includes(member.role)) {
              return
            }

            // Check minimum events
            if (member.eventsAttended < (rosterConfig.minEventsToQualify || 1)) {
              return
            }

            // Exclude substitutes if not included
            if (!rosterConfig.includeSubstitutes && member.type === 'substitute') {
              return
            }

            recipients.push({
              id: member.user_id || member.roster_member_id,
              name: member.name,
              type: member.type,
              eventsAttended: member.eventsAttended || 1,
              totalEvents: member.totalEvents || 1,
              weight: rosterConfig.useAttendanceWeighting
                ? (member.eventsAttended / member.totalEvents)
                : 1,
              customPayout: member.customPayout || null
            })
          })
        }
        break

      case 'paymentGroup':
        // Get recipients from payment group
        const group = band?.payment_groups?.find(g => g.id === nodeData.paymentGroupId)
        if (group?.users) {
          group.users.forEach(user => {
            const pivotData = user.pivot || {}
            recipients.push({
              id: user.id,
              name: user.name,
              type: 'member',
              payoutType: pivotData.payout_type || group.default_payout_type,
              payoutValue: parseFloat(pivotData.payout_value || group.default_payout_value || 0),
              weight: 1
            })
          })
        }
        break

      case 'specific':
        // Get specific members
        (nodeData.specificMembers || []).forEach(member => {
          recipients.push({
            id: member.user_id || member.roster_member_id,
            name: member.name,
            type: 'member',
            weight: 1
          })
        })
        break

      case 'roles':
        // Get role slots (would be filled at runtime with actual people)
        (nodeData.roleSlots || []).forEach(slot => {
          recipients.push({
            id: `role-${slot.role}`,
            name: slot.role,
            type: 'role',
            role: slot.role,
            weight: 1
          })
        })
        break

      case 'allMembers':
        // Get all band members
        const { allMembersConfig } = nodeData

        if (allMembersConfig.includeOwners && band?.owners) {
          band.owners.forEach(owner => {
            recipients.push({
              id: owner.id,
              name: owner.name,
              type: 'owner',
              weight: 1
            })
          })
        }

        if (allMembersConfig.includeMembers && band?.members) {
          band.members.forEach(member => {
            recipients.push({
              id: member.id,
              name: member.name,
              type: 'member',
              weight: 1
            })
          })
        }

        if (allMembersConfig.includeProduction) {
          for (let i = 0; i < (allMembersConfig.productionCount || 0); i++) {
            recipients.push({
              id: `production-${i}`,
              name: `Production Member ${i + 1}`,
              type: 'production',
              weight: 1
            })
          }
        }
        break
    }

    return recipients
  }

  /**
   * Distribute amount among recipients
   */
  const distributeAmongRecipients = (recipients, totalAmount, distributionMode, nodeData, minimumPayout, respectCustomPayouts) => {
    const payouts = []

    // Handle custom payouts first if respecting them
    let remainingAmount = totalAmount
    const recipientsWithCustom = []
    const recipientsWithoutCustom = []

    recipients.forEach(recipient => {
      if (respectCustomPayouts && recipient.customPayout) {
        const customAmount = recipient.customPayout
        payouts.push({
          id: recipient.id,
          name: recipient.name,
          type: recipient.type,
          amount: customAmount,
          payout_type: 'custom',
          custom: true
        })
        remainingAmount -= customAmount
        recipientsWithCustom.push(recipient)
      } else {
        recipientsWithoutCustom.push(recipient)
      }
    })

    if (recipientsWithoutCustom.length === 0) {
      return payouts
    }

    // Distribute remaining amount based on mode
    switch (distributionMode) {
      case 'equal_split':
        const perMember = Math.max(
          remainingAmount / recipientsWithoutCustom.length,
          minimumPayout || 0
        )
        recipientsWithoutCustom.forEach(recipient => {
          payouts.push({
            id: recipient.id,
            name: recipient.name,
            type: recipient.type,
            amount: perMember,
            payout_type: 'equal_split'
          })
        })
        break

      case 'weighted':
        // Use weights (e.g., attendance weights)
        const totalWeight = recipientsWithoutCustom.reduce((sum, r) => sum + r.weight, 0)
        recipientsWithoutCustom.forEach(recipient => {
          const weightedAmount = totalWeight > 0
            ? (remainingAmount * recipient.weight) / totalWeight
            : 0
          payouts.push({
            id: recipient.id,
            name: recipient.name,
            type: recipient.type,
            amount: Math.max(weightedAmount, minimumPayout || 0),
            payout_type: 'weighted',
            weight: recipient.weight,
            eventsAttended: recipient.eventsAttended
          })
        })
        break

      case 'percentage':
        // Use per-member percentages
        recipientsWithoutCustom.forEach(recipient => {
          const percentage = recipient.payoutValue || 0
          const amount = (remainingAmount * percentage) / 100
          payouts.push({
            id: recipient.id,
            name: recipient.name,
            type: recipient.type,
            amount: Math.max(amount, minimumPayout || 0),
            payout_type: 'percentage',
            percentage
          })
        })
        break

      case 'fixed':
        // Use fixed amounts - either from recipient payoutValue or nodeData fixedAmountPerMember
        const fixedAmount = nodeData.fixedAmountPerMember || 0
        recipientsWithoutCustom.forEach(recipient => {
          // For payment group members, use their payoutValue; for roster members, use fixedAmountPerMember
          const amount = recipient.payoutValue !== undefined ? recipient.payoutValue : fixedAmount
          payouts.push({
            id: recipient.id,
            name: recipient.name,
            type: recipient.type,
            amount: Math.max(amount, minimumPayout || 0),
            payout_type: 'fixed'
          })
        })
        break

      case 'tiered':
        // Use tiered configuration from nodeData
        const tier = findApplicableTier(totalAmount, nodeData.tierConfig)
        if (tier) {
          const tierAmount = tier.type === 'percentage'
            ? (remainingAmount * tier.value) / 100
            : tier.value
          const perMember = tierAmount / recipientsWithoutCustom.length

          recipientsWithoutCustom.forEach(recipient => {
            payouts.push({
              id: recipient.id,
              name: recipient.name,
              type: recipient.type,
              amount: Math.max(perMember, minimumPayout || 0),
              payout_type: 'tiered',
              tier: tier.name || `$${tier.min}-${tier.max}`
            })
          })
        }
        break
    }

    return payouts
  }

  /**
   * Find applicable tier for tiered calculations
   */
  const findApplicableTier = (amount, tiers) => {
    if (!tiers || !Array.isArray(tiers)) return null

    for (const tier of tiers) {
      const min = tier.min || 0
      const max = tier.max || Infinity
      if (amount >= min && amount <= max) {
        return tier
      }
    }
    return null
  }

  /**
   * Format money for display
   */
  const moneyFormat = (number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(number)
  }

  return {
    calculate,
    moneyFormat
  }
}
