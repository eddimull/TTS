<template>
  <div class="h-full relative bg-gray-50 dark:bg-gray-900">
    <VueFlow
      v-model:nodes="nodes"
      v-model:edges="edges"
      :default-viewport="{ zoom: 1, x: 0, y: 0 }"
      :min-zoom="0.2"
      :max-zoom="4"
      :edges-deletable="true"
      :nodes-deletable="true"
      @nodes-change="handleNodesChange"
      @edges-change="handleEdgesChange"
      @connect="handleConnect"
      @node-click="handleNodeClick"
      @drop="onDrop"
      @dragover="onDragOver"
      class="flow-canvas"
    >
      <!-- Custom node components -->
      <template #node-income="nodeProps">
        <IncomeNode
          :id="nodeProps.id"
          :data="nodeProps.data"
          @update="handleUpdateNodeData"
          @delete="handleDeleteNode"
          @duplicate="handleDuplicateNode"
          @rename="handleNodeRename"
        />
      </template>

      <template #node-bandCut="nodeProps">
        <BandCutNode
          :id="nodeProps.id"
          :data="nodeProps.data"
          @update="handleUpdateNodeData"
          @settings="handleNodeSettings"
          @delete="handleDeleteNode"
          @duplicate="handleDuplicateNode"
          @rename="handleNodeRename"
        />
      </template>

      <template #node-conditional="nodeProps">
        <ConditionalNode
          :id="nodeProps.id"
          :data="nodeProps.data"
          @update="handleUpdateNodeData"
          @settings="handleNodeSettings"
          @delete="handleDeleteNode"
          @duplicate="handleDuplicateNode"
          @rename="handleNodeRename"
        />
      </template>

      <template #node-payoutGroup="nodeProps">
        <PayoutGroupNode
          :id="nodeProps.id"
          :data="nodeProps.data"
          :available-payment-groups="band.payment_groups || []"
          :available-roles="availableRoles"
          @update="handleUpdateNodeData"
          @settings="handleNodeSettings"
          @delete="handleDeleteNode"
          @duplicate="handleDuplicateNode"
          @rename="handleNodeRename"
        />
      </template>

      <!-- Custom edge component -->
      <template #edge-custom="edgeProps">
        <ConnectionLine
          :id="edgeProps.id"
          :source="edgeProps.source"
          :target="edgeProps.target"
          :source-x="edgeProps.sourceX"
          :source-y="edgeProps.sourceY"
          :target-x="edgeProps.targetX"
          :target-y="edgeProps.targetY"
          :source-position="edgeProps.sourcePosition"
          :target-position="edgeProps.targetPosition"
          :marker-end="edgeProps.markerEnd"
          :data="edgeProps.data"
          @remove="handleRemoveEdge"
          @add-node="handleAddNodeBetween"
        />
      </template>

      <!-- Background with dots pattern -->
      <Background pattern-color="#aaa" :gap="16" />

      <!-- Minimap overview -->
      <MiniMap />

      <!-- Zoom/fit controls -->
      <Controls />

      <!-- Custom control panel -->
      <Panel position="top-right" class="flex gap-2">
        <Button
          icon="pi pi-id-card"
          @click="showRosterPanel = !showRosterPanel"
          rounded
          :severity="showRosterPanel ? 'success' : 'secondary'"
          :text="!showRosterPanel"
          v-tooltip.left="'Customize Preview Roster'"
        />
        <Button
          icon="pi pi-users"
          @click="showPaymentGroupDialog = true"
          rounded
          severity="info"
          v-tooltip.left="'Create Payment Group'"
        />
        <Button
          :icon="showPreview ? 'pi pi-eye-slash' : 'pi pi-eye'"
          @click="showPreview = !showPreview"
          :disabled="!calculationResults"
          rounded
          text
          :severity="showPreview ? 'success' : 'secondary'"
          v-tooltip.left="showPreview ? 'Hide Preview' : 'Show Preview'"
        />
        <Button
          icon="pi pi-undo"
          @click="undo"
          :disabled="!canUndo"
          rounded
          text
          severity="secondary"
          v-tooltip.left="'Undo (Ctrl+Z)'"
        />
        <Button
          icon="pi pi-redo"
          @click="redo"
          :disabled="!canRedo"
          rounded
          text
          severity="secondary"
          v-tooltip.left="'Redo (Ctrl+Y)'"
        />
        <Button
          icon="pi pi-save"
          @click="saveFlow"
          :loading="saving"
          rounded
          severity="success"
          v-tooltip.left="'Save Flow (Ctrl+S)'"
        />
      </Panel>
    </VueFlow>

    <!-- Node toolbox (left sidebar) -->
    <NodeToolbox :nodes="nodes" @add-node="handleAddNode" />

    <!-- Save indicator -->
    <div v-if="isDirty && !saving" class="absolute top-4 left-1/2 transform -translate-x-1/2 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 px-4 py-2 rounded-lg shadow-lg text-sm font-medium">
      <i class="pi pi-circle-fill text-xs mr-2" />
      Unsaved changes
    </div>

    <!-- Success indicator -->
    <div v-if="showSaveSuccess" class="absolute top-4 left-1/2 transform -translate-x-1/2 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 px-4 py-2 rounded-lg shadow-lg text-sm font-medium">
      <i class="pi pi-check-circle mr-2" />
      Flow saved successfully
    </div>

    <!-- Roster Preview Panel -->
    <RosterPreviewPanel
      v-if="showRosterPanel"
      :band="band"
      :available-roles="availableRoles"
      :initial-members="previewRosterMembers"
      @close="showRosterPanel = false"
      @update="handleRosterMembersUpdate"
    />

    <!-- Calculation Preview Panel -->
    <div v-if="showPreview && calculationResults" class="absolute bottom-4 left-1/2 transform -translate-x-1/2 w-full max-w-4xl px-4">
      <FlowCalculationPreview
        :results="calculationResults"
        @close="showPreview = false"
      />
    </div>

    <!-- Tier Configuration Dialog -->
    <TierConfigDialog
      v-model="showTierDialog"
      :tier-config="selectedNodeTierConfig"
      @save="handleTierConfigSave"
    />

    <!-- Payment Group Dialog -->
    <PaymentGroupDialog
      v-model="showPaymentGroupDialog"
      :band-id="band.id"
      :available-members="availableMembers"
      @created="handlePaymentGroupCreated"
    />

    <!-- Node Label Dialog -->
    <NodeLabelDialog
      v-model:visible="showLabelDialog"
      :current-label="selectedNodeLabel"
      @save="handleLabelSave"
    />

    <!-- Node Insert Menu Dialog -->
    <Dialog
      v-model:visible="showNodeInsertMenu"
      header="Insert Node"
      :modal="true"
      :closable="true"
      :style="{ width: '400px' }"
    >
      <div class="space-y-3">
        <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">
          Choose a node type to insert between the connected nodes:
        </p>

        <button
          @click="insertNodeBetween('bandCut')"
          class="w-full flex items-center gap-3 p-3 rounded-lg border-2 border-purple-500 bg-purple-50 dark:bg-purple-900/20 hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors"
        >
          <i class="pi pi-percentage text-purple-500 text-xl" />
          <div class="text-left">
            <div class="font-bold text-gray-800 dark:text-gray-100">Band Cut</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Take a percentage or fixed amount for the band</div>
          </div>
        </button>

        <button
          @click="insertNodeBetween('conditional')"
          class="w-full flex items-center gap-3 p-3 rounded-lg border-2 border-amber-500 bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 dark:hover:bg-amber-900/30 transition-colors"
        >
          <i class="pi pi-question-circle text-amber-500 text-xl" />
          <div class="text-left">
            <div class="font-bold text-gray-800 dark:text-gray-100">Conditional</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Branch based on conditions (price, event type, etc.)</div>
          </div>
        </button>

        <button
          @click="insertNodeBetween('payoutGroup')"
          class="w-full flex items-center gap-3 p-3 rounded-lg border-2 border-blue-500 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors"
        >
          <i class="pi pi-users text-blue-500 text-xl" />
          <div class="text-left">
            <div class="font-bold text-gray-800 dark:text-gray-100">Payout Group</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Unified distribution to members, groups, or roster</div>
          </div>
        </button>
      </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { VueFlow, Panel, useVueFlow, applyNodeChanges, applyEdgeChanges } from '@vue-flow/core'
import { Background } from '@vue-flow/background'
import { MiniMap } from '@vue-flow/minimap'
import { Controls } from '@vue-flow/controls'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import { useFlowState } from '@/composables/useFlowState'
import { useFlowCalculator } from '@/composables/useFlowCalculator'
import { useFlowValidation } from '@/composables/useFlowValidation'
import { getDefaultNodeData } from '@/composables/useFlowNodeSchemas'
import { useToast } from 'primevue/usetoast'
import NodeToolbox from './NodeToolbox.vue'
import IncomeNode from './Nodes/IncomeNode.vue'
import BandCutNode from './Nodes/BandCutNode.vue'
import ConditionalNode from './Nodes/ConditionalNode.vue'
import PayoutGroupNode from './Nodes/PayoutGroupNode.vue'
import ConnectionLine from './ConnectionLine.vue'
import FlowCalculationPreview from './FlowCalculationPreview.vue'
import TierConfigDialog from './TierConfigDialog.vue'
import PaymentGroupDialog from './PaymentGroupDialog.vue'
import NodeLabelDialog from './NodeLabelDialog.vue'
import RosterPreviewPanel from './RosterPreviewPanel.vue'

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
  },
  initialFlow: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['save'])

// Initialize flow state
const {
  nodes,
  edges,
  isDirty,
  canUndo,
  canRedo,
  undo,
  redo,
  addNode,
  updateNodeData,
  addEdge,
  snapshot,
  markClean,
  markDirty
} = useFlowState(props.initialFlow)

const saving = ref(false)
const showSaveSuccess = ref(false)
const showPreview = ref(false)
const showTierDialog = ref(false)
const selectedNodeId = ref(null)
const selectedNodeTierConfig = ref([])
const showPaymentGroupDialog = ref(false)
const showNodeInsertMenu = ref(false)
const nodeInsertContext = ref(null)
const showLabelDialog = ref(false)
const selectedNodeLabel = ref('')
const selectedNodeIdForLabel = ref(null)
const showRosterPanel = ref(false)
const customPreviewMembers = ref([...props.previewRosterMembers])

// Initialize validation
const { validateConnection, validate } = useFlowValidation()
const toast = useToast()

// Initialize flow calculator
const { calculate } = useFlowCalculator()
const calculationResults = ref(null)

// Available members for payment group dialog
const availableMembers = computed(() => {
  const members = []
  if (props.band.owners) {
    members.push(...props.band.owners.map(owner => ({ ...owner, type: 'owner' })))
  }
  if (props.band.members) {
    members.push(...props.band.members.map(member => ({ ...member, type: 'member' })))
  }
  return members
})

// Handle roster members update
const handleRosterMembersUpdate = (updatedMembers) => {
  customPreviewMembers.value = updatedMembers
  recalculate()
}

// Manual recalculation function
const recalculate = () => {
  // Create context with custom preview roster members for calculation
  const context = {
    eventMembers: customPreviewMembers.value.length > 0
      ? customPreviewMembers.value
      : props.previewRosterMembers
  }

  const calculationResult = calculate(nodes.value, edges.value, props.band, context)
  if (calculationResult) {
    calculationResults.value = calculationResult.results

    // Update nodes with calculated values (mutate in place to avoid triggering watcher)
    if (calculationResult.updatedNodes) {
      calculationResult.updatedNodes.forEach(updatedNode => {
        const existingNode = nodes.value.find(n => n.id === updatedNode.id)
        if (existingNode) {
          // Merge data, explicitly handling undefined to clear old calculated values
          const merged = { ...existingNode.data }
          for (const key in updatedNode.data) {
            merged[key] = updatedNode.data[key]
          }
          existingNode.data = merged
        }
      })
    }

    // Update edges with calculated amount data (mutate in place to avoid triggering watcher)
    if (calculationResult.updatedEdges) {
      calculationResult.updatedEdges.forEach(updatedEdge => {
        const existingEdge = edges.value.find(e => e.id === updatedEdge.id)
        if (existingEdge) {
          // Explicitly copy all properties including undefined
          const merged = { ...existingEdge.data }
          for (const key in updatedEdge.data) {
            merged[key] = updatedEdge.data[key]
          }
          existingEdge.data = merged
        }
      })
    }
  }
}

// Live calculation - recalculate when nodes/edges are added/removed
// Note: We watch the arrays themselves (not deep), so it only triggers on structural changes
watch([nodes, edges], recalculate)

// Recalculate when roster members become available
watch(() => props.previewRosterMembers, () => {
  if (props.previewRosterMembers.length > 0) {
    recalculate()
  }
}, { immediate: true })

// Wrapper for updateNodeData that triggers recalculation
const handleUpdateNodeData = (nodeId, newData) => {
  updateNodeData(nodeId, newData)
  // Trigger recalculation after a brief delay to batch updates
  setTimeout(recalculate, 100)
}

// Handle node position/selection changes
const handleNodesChange = (changes) => {
  // Apply changes to nodes array - required for proper edge tracking
  nodes.value = applyNodeChanges(changes, nodes.value)

  // Snapshot for undo/redo on drag end
  const hasDragStop = changes.some(change => change.type === 'position' && change.dragging === false)
  if (hasDragStop) {
    snapshot()
  }

  // Recalculate when nodes move (position changes)
  const hasPositionChange = changes.some(change => change.type === 'position')
  if (hasPositionChange) {
    recalculate()
  }
}

// Handle edge changes (deletion, etc.)
const handleEdgesChange = (changes) => {
  // Apply changes to edges array - required for proper rendering
  edges.value = applyEdgeChanges(changes, edges.value)

  const hasRemoval = changes.some(change => change.type === 'remove')
  if (hasRemoval) {
    snapshot()
  }
}

// Handle new connection between nodes
const handleConnect = (params) => {
  // Validate connection before creating
  const validation = validateConnection(params, nodes.value, edges.value)

  if (!validation.valid) {
    toast.add({
      severity: 'error',
      summary: 'Invalid Connection',
      detail: validation.error,
      life: 3000
    })
    return
  }

  // Create edge with custom type
  const edge = {
    id: `edge-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
    source: params.source,
    target: params.target,
    sourceHandle: params.sourceHandle,
    targetHandle: params.targetHandle,
    type: 'custom',
    animated: false
  }

  addEdge(edge)
}

// Handle node click for selection
const handleNodeClick = (event) => {
  // Future: show property panel for selected node
  console.log('Node clicked:', event.node)
}

// Handle removing an edge
const handleRemoveEdge = (edgeId) => {
  edges.value = edges.value.filter(edge => edge.id !== edgeId)
  snapshot()
  recalculate()
}

// Handle adding a node between two connected nodes
const handleAddNodeBetween = ({ edgeId, source, target, position }) => {
  nodeInsertContext.value = {
    edgeId,
    source,
    target,
    position
  }
  showNodeInsertMenu.value = true
}

// Insert a specific node type between two nodes
const insertNodeBetween = (nodeType) => {
  if (!nodeInsertContext.value) return

  const { edgeId, source, target, position } = nodeInsertContext.value

  // Create new node ID
  const newNodeId = `node-${nodeType}-${Date.now()}`

  // Get default data for the node type
  const defaultData = getDefaultNodeData(nodeType)

  // Add the new node
  nodes.value.push({
    id: newNodeId,
    type: nodeType,
    position: { x: position.x - 100, y: position.y - 50 }, // Offset to center the node
    data: defaultData
  })

  // Remove the old edge
  edges.value = edges.value.filter(edge => edge.id !== edgeId)

  // Create two new edges: source -> new node -> target
  const edge1 = {
    id: `edge-${Date.now()}-1`,
    source,
    target: newNodeId,
    type: 'custom',
    animated: false
  }

  const edge2 = {
    id: `edge-${Date.now()}-2`,
    source: newNodeId,
    target,
    type: 'custom',
    animated: false
  }

  edges.value.push(edge1, edge2)

  // Clean up
  showNodeInsertMenu.value = false
  nodeInsertContext.value = null
  snapshot()
  recalculate()

  toast.add({
    severity: 'success',
    summary: 'Node Added',
    detail: `${nodeType} node inserted successfully`,
    life: 2000
  })
}

// Handle adding node from toolbox (legacy click method)
const handleAddNode = (nodeType, position) => {
  addNode(nodeType, position)
}

// Get Vue Flow instance for coordinate projection
const { screenToFlowCoordinate } = useVueFlow()

// Handle drag over - allow drop
const onDragOver = (event) => {
  event.preventDefault()
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'move'
  }
}

// Handle drop - add node at drop position
const onDrop = (event) => {
  const nodeType = event.dataTransfer?.getData('nodeType')
  if (!nodeType) return

  // Convert screen coordinates to flow coordinates
  const position = screenToFlowCoordinate({
    x: event.clientX,
    y: event.clientY
  })

  addNode(nodeType, position)
  snapshot()
}

// Handle node settings click
const handleNodeSettings = (nodeId) => {
  selectedNodeId.value = nodeId
  const node = nodes.value.find(n => n.id === nodeId)
  if (node && node.data.tierConfig) {
    selectedNodeTierConfig.value = JSON.parse(JSON.stringify(node.data.tierConfig))
  } else {
    // Default tier config
    selectedNodeTierConfig.value = [
      { min: 0, max: 1000, type: 'percentage', value: 10 },
      { min: 1000, max: null, type: 'percentage', value: 15 }
    ]
  }
  showTierDialog.value = true
}

// Handle tier config save
const handleTierConfigSave = (tierConfig) => {
  if (selectedNodeId.value) {
    const node = nodes.value.find(n => n.id === selectedNodeId.value)
    if (node) {
      updateNodeData(selectedNodeId.value, {
        ...node.data,
        tierConfig: tierConfig
      })
      recalculate()
    }
  }
  showTierDialog.value = false
  selectedNodeId.value = null
}

// Handle payment group created
const handlePaymentGroupCreated = () => {
  // Reload the page to get updated band data with new payment group
  window.location.reload()
}

// Handle node deletion
const handleDeleteNode = (nodeId) => {
  const node = nodes.value.find(n => n.id === nodeId)
  if (!node) return

  const connectedEdges = edges.value.filter(
    e => e.source === nodeId || e.target === nodeId
  )

  const nodeName = node.data.customLabel || node.type
  let shouldDelete = true

  if (connectedEdges.length > 0) {
    shouldDelete = window.confirm(
      `Delete "${nodeName}" node and ${connectedEdges.length} connection(s)?`
    )
  }

  if (shouldDelete) {
    nodes.value = nodes.value.filter(n => n.id !== nodeId)
    edges.value = edges.value.filter(
      e => e.source !== nodeId && e.target !== nodeId
    )
    snapshot()
    markDirty()
    recalculate()

    toast.add({
      severity: 'success',
      summary: 'Node Deleted',
      detail: 'Node and connections removed',
      life: 2000
    })
  }
}

// Handle node duplication
const handleDuplicateNode = (nodeId) => {
  const node = nodes.value.find(n => n.id === nodeId)
  if (!node) return

  const newNode = {
    id: `node-${Date.now()}-${Math.random().toString(36).slice(2, 11)}`,
    type: node.type,
    position: {
      x: node.position.x + 50,
      y: node.position.y + 50
    },
    data: {
      ...JSON.parse(JSON.stringify(node.data)),
      customLabel: node.data.customLabel
        ? `${node.data.customLabel} (Copy)`
        : null
    }
  }

  nodes.value.push(newNode)
  snapshot()
  markDirty()

  toast.add({
    severity: 'success',
    summary: 'Node Duplicated',
    detail: 'Drag to reposition the copy',
    life: 2000
  })
}

// Handle node rename
const handleNodeRename = (nodeId) => {
  const node = nodes.value.find(n => n.id === nodeId)
  if (!node) return

  selectedNodeIdForLabel.value = nodeId
  selectedNodeLabel.value = node.data.customLabel || ''
  showLabelDialog.value = true
}

// Handle label save
const handleLabelSave = (newLabel) => {
  if (selectedNodeIdForLabel.value) {
    updateNodeData(selectedNodeIdForLabel.value, {
      customLabel: newLabel
    })
    recalculate()
  }
}

// Save flow to backend
const saveFlow = async () => {
  saving.value = true
  showSaveSuccess.value = false

  try {
    const flowData = {
      nodes: nodes.value,
      edges: edges.value,
      version: '1.0'
    }

    emit('save', flowData)

    markClean()
    showSaveSuccess.value = true

    setTimeout(() => {
      showSaveSuccess.value = false
    }, 3000)
  } catch (error) {
    console.error('Failed to save flow:', error)
    alert('Failed to save flow')
  } finally {
    saving.value = false
  }
}

// Keyboard shortcuts
const handleKeyDown = (e) => {
  // Ctrl+Z: Undo
  if (e.ctrlKey && e.key === 'z' && !e.shiftKey) {
    e.preventDefault()
    undo()
  }
  // Ctrl+Y or Ctrl+Shift+Z: Redo
  if ((e.ctrlKey && e.key === 'y') || (e.ctrlKey && e.shiftKey && e.key === 'z')) {
    e.preventDefault()
    redo()
  }
  // Ctrl+S: Save
  if (e.ctrlKey && e.key === 's') {
    e.preventDefault()
    saveFlow()
  }
}

// Warn before leaving with unsaved changes
const handleBeforeUnload = (e) => {
  if (isDirty.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
  window.addEventListener('beforeunload', handleBeforeUnload)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeyDown)
  window.removeEventListener('beforeunload', handleBeforeUnload)
})
</script>

<style>
@import '@vue-flow/core/dist/style.css';
@import '@vue-flow/core/dist/theme-default.css';
@import '@vue-flow/controls/dist/style.css';
@import '@vue-flow/minimap/dist/style.css';

.flow-canvas {
  width: 100%;
  height: 100%;
}

.vue-flow {
  width: 100%;
  height: 100%;
}

/* Dark mode support for Vue Flow */
.dark .vue-flow__node {
  color: #e5e7eb;
}

.dark .vue-flow__edge-path {
  stroke: #4b5563;
}

.dark .vue-flow__minimap {
  background-color: #1f2937;
  border-color: #374151;
}

.dark .vue-flow__controls {
  border-color: #374151;
  background-color: #1f2937;
}

.dark .vue-flow__controls-button {
  background-color: #374151;
  border-color: #4b5563;
  color: #e5e7eb;
}

.dark .vue-flow__controls-button:hover {
  background-color: #4b5563;
}

/* Selected edge styling */
.vue-flow__edge.selected .vue-flow__edge-path {
  stroke: #3b82f6 !important;
  stroke-width: 3 !important;
}

/* Selected node styling */
.vue-flow__node.selected {
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
}

/* Edge interaction */
.vue-flow__edge:hover .vue-flow__edge-path {
  stroke-width: 4;
  cursor: pointer;
}

/* Make edges more visible and easier to click */
.vue-flow__edge-path {
  stroke-width: 2;
}

.vue-flow__edge {
  pointer-events: stroke;
}
</style>
