import { validateNodeData as validateNodeDataFromSchema } from './useFlowNodeSchemas'

/**
 * Composable for validating flow editor connections and structure
 * Supports unified payoutGroup and conditional nodes
 *
 * @returns {Object} Validation functions
 */
export function useFlowValidation() {
  /**
   * Validate a new connection before it's created
   *
   * @param {Object} connection - Connection params { source, target, sourceHandle, targetHandle }
   * @param {Array} nodes - Current nodes
   * @param {Array} edges - Current edges
   * @returns {Object} { valid: boolean, error: string }
   */
  const validateConnection = (connection, nodes, edges) => {
    const sourceNode = nodes.find(n => n.id === connection.source)
    const targetNode = nodes.find(n => n.id === connection.target)

    if (!sourceNode || !targetNode) {
      return { valid: false, error: 'Invalid nodes' }
    }

    // Check for duplicate connection
    const duplicate = edges.some(e =>
      e.source === connection.source &&
      e.target === connection.target &&
      e.sourceHandle === connection.sourceHandle
    )
    if (duplicate) {
      return { valid: false, error: 'Connection already exists' }
    }

    // Check for self-connection
    if (connection.source === connection.target) {
      return { valid: false, error: 'Cannot connect node to itself' }
    }

    // Check for cycles (except for conditional branches)
    const isConditionalBranch = sourceNode.type === 'conditional' &&
      (connection.sourceHandle === 'true' || connection.sourceHandle === 'false')

    if (!isConditionalBranch && wouldCreateCycle(connection, edges)) {
      return { valid: false, error: 'Connection would create a cycle' }
    }

    // Conditional node validation
    if (sourceNode.type === 'conditional') {
      // Must use true or false handle
      if (connection.sourceHandle !== 'true' && connection.sourceHandle !== 'false') {
        return {
          valid: false,
          error: 'Conditional nodes must connect via true or false handles'
        }
      }

      // Check if this handle already has a connection
      const handleAlreadyUsed = edges.some(e =>
        e.source === connection.source &&
        e.sourceHandle === connection.sourceHandle
      )

      if (handleAlreadyUsed) {
        return {
          valid: false,
          error: `${connection.sourceHandle.toUpperCase()} branch already connected`
        }
      }
    }

    return { valid: true }
  }

  /**
   * Validate entire flow structure
   *
   * @param {Array} nodes - All nodes
   * @param {Array} edges - All edges
   * @returns {Object} { valid: boolean, errors: Array, warnings: Array }
   */
  const validate = (nodes, edges) => {
    const errors = []
    const warnings = []

    // Must have exactly one income node
    const incomeNodes = nodes.filter(n => n.type === 'income')
    if (incomeNodes.length === 0) {
      errors.push('Flow must have exactly one Income node as a starting point')
    } else if (incomeNodes.length > 1) {
      errors.push('Flow can only have one Income node')
    }

    // Check for disconnected nodes
    if (nodes.length > 1) {
      const connectedNodeIds = new Set()
      edges.forEach(edge => {
        connectedNodeIds.add(edge.source)
        connectedNodeIds.add(edge.target)
      })

      nodes.forEach(node => {
        if (!connectedNodeIds.has(node.id)) {
          if (node.type === 'income' && incomeNodes.length === 1) {
            // Income node can be disconnected if it's the only node
            if (nodes.length > 1) {
              warnings.push('Income node is not connected to any other nodes')
            }
          } else {
            warnings.push(`Disconnected node: ${node.data.customLabel || node.data.label || node.type}`)
          }
        }
      })
    }

    // Check for cycles
    if (hasCycle(nodes, edges)) {
      errors.push('Flow contains a cycle - circular connections are not allowed')
    }

    // Validate conditional nodes have both branches
    nodes.filter(n => n.type === 'conditional').forEach(node => {
      const outgoingEdges = edges.filter(e => e.source === node.id)
      const hasTrueBranch = outgoingEdges.some(e => e.sourceHandle === 'true')
      const hasFalseBranch = outgoingEdges.some(e => e.sourceHandle === 'false')

      if (!hasTrueBranch && !hasFalseBranch) {
        warnings.push(`Conditional "${node.data.customLabel || 'unnamed'}" has no connections`)
      } else if (!hasTrueBranch) {
        warnings.push(`Conditional "${node.data.customLabel || 'unnamed'}" missing TRUE branch`)
      } else if (!hasFalseBranch) {
        warnings.push(`Conditional "${node.data.customLabel || 'unnamed'}" missing FALSE branch`)
      }
    })

    // Validate node-specific data using schema validation
    nodes.forEach(node => {
      const schemaValidation = validateNodeDataFromSchema(node)
      if (!schemaValidation.valid) {
        schemaValidation.errors.forEach(error => {
          errors.push(`${node.data.customLabel || node.type}: ${error}`)
        })
      }
    })

    // Additional payoutGroup validations
    nodes.filter(n => n.type === 'payoutGroup').forEach(node => {
      const { sourceType, rosterConfig, incomingAllocationType, distributionMode } = node.data

      // Roster-specific warnings
      if (sourceType === 'roster') {
        if (rosterConfig.useAttendanceWeighting) {
          warnings.push(`Payout Group "${node.data.customLabel || node.data.label}" uses attendance weighting - requires booking context at runtime`)
        }
      }

      // Distribution mode warnings
      if (distributionMode === 'percentage' || distributionMode === 'fixed') {
        if (!node.data.memberAllocations || node.data.memberAllocations.length === 0) {
          warnings.push(`Payout Group "${node.data.customLabel || node.data.label}" uses ${distributionMode} mode but has no member allocations configured`)
        }
      }

      // Incoming allocation warnings
      if (incomingAllocationType !== 'remainder') {
        const hasMultipleSiblings = edges.filter(e => {
          // Find parent node
          const parentEdge = edges.find(pe => pe.target === node.id)
          if (!parentEdge) return false

          // Check if parent has multiple children
          return e.source === parentEdge.source && e.target !== node.id
        }).length > 0

        if (!hasMultipleSiblings && incomingAllocationType === 'percentage') {
          warnings.push(`Payout Group "${node.data.customLabel || node.data.label}" takes only ${node.data.incomingAllocationValue}% - consider using "remainder" if it's the only output`)
        }
      }
    })

    // Warn about unused band cuts
    const bandCutNodes = nodes.filter(n => n.type === 'bandCut')
    bandCutNodes.forEach(node => {
      if (node.data.cutType === 'none' || node.data.value === 0) {
        warnings.push(`Band Cut node "${node.data.customLabel || 'unnamed'}" has no cut configured`)
      }
    })

    return {
      valid: errors.length === 0,
      errors,
      warnings
    }
  }

  /**
   * Check if adding a connection would create a cycle
   *
   * @param {Object} newConnection - The connection to test
   * @param {Array} existingEdges - Current edges
   * @returns {boolean} True if would create cycle
   */
  const wouldCreateCycle = (newConnection, existingEdges) => {
    // Create temporary edge list with new connection
    const tempEdges = [...existingEdges, newConnection]

    // Build adjacency list
    const adjacency = new Map()
    tempEdges.forEach(edge => {
      if (!adjacency.has(edge.source)) {
        adjacency.set(edge.source, [])
      }
      adjacency.get(edge.source).push(edge.target)
    })

    // DFS to detect cycle
    const visited = new Set()
    const recursionStack = new Set()

    const dfs = (nodeId) => {
      visited.add(nodeId)
      recursionStack.add(nodeId)

      const neighbors = adjacency.get(nodeId) || []
      for (const neighbor of neighbors) {
        if (!visited.has(neighbor)) {
          if (dfs(neighbor)) return true
        } else if (recursionStack.has(neighbor)) {
          return true // Cycle detected
        }
      }

      recursionStack.delete(nodeId)
      return false
    }

    // Check from the new connection's source
    return dfs(newConnection.source)
  }

  /**
   * Check if flow has cycles
   *
   * @param {Array} nodes - All nodes
   * @param {Array} edges - All edges
   * @returns {boolean} True if cycle exists
   */
  const hasCycle = (nodes, edges) => {
    // Build adjacency list
    const adjacency = new Map()
    edges.forEach(edge => {
      if (!adjacency.has(edge.source)) {
        adjacency.set(edge.source, [])
      }
      adjacency.get(edge.source).push(edge.target)
    })

    const visited = new Set()
    const recursionStack = new Set()

    const dfs = (nodeId) => {
      visited.add(nodeId)
      recursionStack.add(nodeId)

      const neighbors = adjacency.get(nodeId) || []
      for (const neighbor of neighbors) {
        if (!visited.has(neighbor)) {
          if (dfs(neighbor)) return true
        } else if (recursionStack.has(neighbor)) {
          return true
        }
      }

      recursionStack.delete(nodeId)
      return false
    }

    // Check all nodes as potential starting points
    for (const node of nodes) {
      if (!visited.has(node.id)) {
        if (dfs(node.id)) return true
      }
    }

    return false
  }

  return {
    validateConnection,
    validate
  }
}
