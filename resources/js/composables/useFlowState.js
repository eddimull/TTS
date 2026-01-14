import { ref, computed } from 'vue'
import { getDefaultNodeData } from './useFlowNodeSchemas'

/**
 * Composable for managing flow editor state with undo/redo functionality
 *
 * @param {Object|null} initialFlow - Initial flow data with nodes and edges
 * @returns {Object} State management functions and reactive state
 */
export function useFlowState(initialFlow = null) {
  const nodes = ref(initialFlow?.nodes || [])
  const edges = ref(initialFlow?.edges || [])

  // History stacks for undo/redo
  const history = ref([])
  const historyIndex = ref(-1)
  const maxHistorySize = 50

  const canUndo = computed(() => historyIndex.value > 0)
  const canRedo = computed(() => historyIndex.value < history.value.length - 1)

  // Dirty state tracking
  const isDirty = ref(false)
  const markDirty = () => { isDirty.value = true }
  const markClean = () => { isDirty.value = false }

  /**
   * Take a snapshot of current state for undo/redo
   */
  const snapshot = () => {
    const state = {
      nodes: JSON.parse(JSON.stringify(nodes.value)),
      edges: JSON.parse(JSON.stringify(edges.value)),
      timestamp: Date.now()
    }

    // Remove any redo history
    if (historyIndex.value < history.value.length - 1) {
      history.value = history.value.slice(0, historyIndex.value + 1)
    }

    history.value.push(state)

    // Limit history size
    if (history.value.length > maxHistorySize) {
      history.value.shift()
    } else {
      historyIndex.value++
    }
  }

  /**
   * Undo to previous state
   */
  const undo = () => {
    if (!canUndo.value) return
    historyIndex.value--
    const state = history.value[historyIndex.value]
    nodes.value = JSON.parse(JSON.stringify(state.nodes))
    edges.value = JSON.parse(JSON.stringify(state.edges))
  }

  /**
   * Redo to next state
   */
  const redo = () => {
    if (!canRedo.value) return
    historyIndex.value++
    const state = history.value[historyIndex.value]
    nodes.value = JSON.parse(JSON.stringify(state.nodes))
    edges.value = JSON.parse(JSON.stringify(state.edges))
  }

  /**
   * Add a new node to the canvas
   *
   * @param {string} nodeType - Type of node to add
   * @param {Object} position - {x, y} position on canvas
   * @returns {Object} The newly created node
   */
  const addNode = (nodeType, position = null) => {
    const newNode = {
      id: `node-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      type: nodeType,
      position: position || { x: 250, y: 100 },
      data: getDefaultNodeData(nodeType)
    }
    nodes.value.push(newNode)
    snapshot()
    markDirty()
    return newNode
  }

  /**
   * Update data for a specific node
   *
   * @param {string} nodeId - ID of the node to update
   * @param {Object} newData - New data to merge
   */
  const updateNodeData = (nodeId, newData) => {
    const node = nodes.value.find(n => n.id === nodeId)
    if (node) {
      node.data = { ...node.data, ...newData }
      debouncedSnapshot()
      markDirty()
    }
  }

  /**
   * Remove a node and its connections
   *
   * @param {string} nodeId - ID of the node to remove
   */
  const removeNode = (nodeId) => {
    nodes.value = nodes.value.filter(n => n.id !== nodeId)
    edges.value = edges.value.filter(e => e.source !== nodeId && e.target !== nodeId)
    snapshot()
    markDirty()
  }

  /**
   * Remove an edge
   *
   * @param {string} edgeId - ID of the edge to remove
   */
  const removeEdge = (edgeId) => {
    edges.value = edges.value.filter(e => e.id !== edgeId)
    snapshot()
    markDirty()
  }

  /**
   * Add an edge between nodes
   *
   * @param {Object} edge - Edge object with source, target, etc.
   */
  const addEdge = (edge) => {
    edges.value.push(edge)
    snapshot()
    markDirty()
  }

  // Debounced snapshot for frequent updates (like inline editing)
  let snapshotTimeout = null
  const debouncedSnapshot = () => {
    clearTimeout(snapshotTimeout)
    snapshotTimeout = setTimeout(snapshot, 500)
  }

  // Take initial snapshot if we have initial data
  if (initialFlow?.nodes && initialFlow.nodes.length > 0) {
    snapshot()
    markClean()
  }

  return {
    // State
    nodes,
    edges,
    isDirty,
    canUndo,
    canRedo,

    // State management
    snapshot,
    undo,
    redo,
    markClean,
    markDirty,

    // Node/edge operations
    addNode,
    updateNodeData,
    removeNode,
    removeEdge,
    addEdge
  }
}
