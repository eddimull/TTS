<template>
  <g>
    <defs>
      <marker
        id="arrowhead-green"
        markerWidth="10"
        markerHeight="10"
        refX="9"
        refY="5"
        orient="auto"
        markerUnits="strokeWidth"
      >
        <polygon points="0 0, 10 5, 0 10" :fill="strokeColor" style="transition: fill 0.3s ease"/>
      </marker>
      <marker
        id="arrowhead-red"
        markerWidth="10"
        markerHeight="10"
        refX="9"
        refY="5"
        orient="auto"
        markerUnits="strokeWidth"
      >
        <polygon points="0 0, 10 5, 0 10" fill="#ef4444" />
      </marker>
      <!-- Filter to create stroke halo for hover detection -->
      <filter id="stroke-halo" x="-50%" y="-50%" width="200%" height="200%" >
        <feMorphology operator="dilate" radius="20" in="SourceGraphic" result="thickened" />
        <feColorMatrix in="thickened" type="matrix"
          values="0 0 0 0 0.0
                  0 0 0 0 0.0
                  0 0 0 0 0
                  0 0 0 0.0 0" result="colored" />
      </filter>
    </defs>

    <!-- Wide nearly-invisible stroke for hover detection matching halo size -->
    <path
      :d="edgePath"
      stroke="rgba(255, 255, 255, 0.01)"
      stroke-width="50"
      stroke-dasharray="none"
      stroke-linecap="round"
      fill="none"
      style="pointer-events: stroke;"
      class="cursor-pointer"
      @mouseenter="handleMouseEnter"
      @mouseleave="handleMouseLeave"
    />

    <!-- Visual hover halo (always visible) -->
    <path
      :d="edgePath"
      stroke="white"
      stroke-width="3"
      stroke-linecap="round"
      fill="none"
      filter="url(#stroke-halo)"
      class="pointer-events-none hover-halo"
    />

    <!-- Connection path -->
    <path
      :d="edgePath"
      :stroke="strokeColor"
      :stroke-width="3"
      fill="none"
      :marker-end="`url(#${markerId})`"
      :class="edgeClass"
      style="transition: stroke 0.3s ease;"
      class="pointer-events-none connection-path"
    />

    <!-- Amount label (if amount data exists) -->
    <g v-if="amount !== null && amount !== undefined" 
      :transform="`translate(${labelPosition.x}, ${labelPosition.y})`"
      :opacity="isHovered ? 0 : 0.9"
      style="transition: opacity 0.3s ease;"
    >
      <!-- Label background -->
      <rect
        :x="-labelWidth / 2"
        :y="-12"
        :width="labelWidth"
        height="24"
        :fill="labelBgColor"
        rx="12"
        :class="{ 'opacity-90': true }"
      />
      <!-- Label text -->
      <text
        text-anchor="middle"
        alignment-baseline="middle"
        :fill="labelTextColor"
        class="text-xs font-bold"
        style="pointer-events: none;"
      >
        {{ formattedAmount }}
      </text>
    </g>

    <!-- Hover Action Buttons -->
    <g
      :transform="`translate(${labelPosition.x}, ${labelPosition.y})`"
      :class="['hover-actions', { 'is-visible': isHovered }]"
      :style="{ pointerEvents: isHovered ? 'auto' : 'none' }"
      @mouseenter="handleMouseEnter"
      @mouseleave="handleMouseLeave"
    >
      <!-- Container background -->
      <rect
        x="-45"
        y="-18"
        width="90"
        height="36"
        fill="none"
        rx="18"
      />

      <!-- Delete button -->
      <g
        @click="handleRemove"
        @mouseenter="isHoveringDelete = true"
        @mouseleave="isHoveringDelete = false"
        class="cursor-pointer button-icon"
        transform="translate(-22, 0)"
      >
        <circle
          cx="0"
          cy="0"
          :r="isHoveringDelete ? 14 : 12"
          fill="white"
          style="transition:r 0.2s"
        />
        <circle
          cx="0"
          cy="0"
          r="12"
          :fill="isHoveringDelete ? '#dc2626' : '#ef4444'"
          style="transition: all 0.2s;"          
        />
        <path
          d="M-4,-4 L4,4 M4,-4 L-4,4"
          stroke="white"
          stroke-width="2"
          stroke-linecap="round"
          class="icon-path"
        />
      </g>

      <!-- Add node button -->
      <g
        @click="handleAddNode"
        @mouseenter="isHoveringAdd = true"
        @mouseleave="isHoveringAdd = false"
        class="cursor-pointer button-icon"
        transform="translate(22, 0)"
      >
        <circle
          cx="0"
          cy="0"
          :r="isHoveringAdd ? 14 : 12"
          fill="white"
          style="transition:r 0.2s"
        />
        <circle
          cx="0"
          cy="0"
          r="12"
          :fill="isHoveringAdd ? '#2563eb' : '#3b82f6'"
          style="transition: fill 0.2s"
        />
        <path
          d="M0,-6 L0,6 M-6,0 L6,0"
          stroke="white"
          stroke-width="2"
          stroke-linecap="round"
          class="icon-path"
        />
      </g>
    </g>
  </g>
</template>

<script setup>
import { ref, computed } from 'vue'
import { getBezierPath } from '@vue-flow/core'

const props = defineProps({
  id: { type: String, required: true },
  sourceX: { type: Number, required: true },
  sourceY: { type: Number, required: true },
  targetX: { type: Number, required: true },
  targetY: { type: Number, required: true },
  sourcePosition: { type: String, default: 'bottom' },
  targetPosition: { type: String, default: 'top' },
  source: { type: String, required: true },
  target: { type: String, required: true },
  markerEnd: { type: String, default: '' },
  data: { type: Object, default: () => ({}) }
})

const emit = defineEmits(['remove', 'addNode'])

const isHovered = ref(false)
const isHoveringDelete = ref(false)
const isHoveringAdd = ref(false)
let mouseLeaveTimeout = null

// Compute bezier path reactively
const edgePath = computed(() => {
  const [path] = getBezierPath({
    sourceX: props.sourceX,
    sourceY: props.sourceY,
    sourcePosition: props.sourcePosition,
    targetX: props.targetX,
    targetY: props.targetY,
    targetPosition: props.targetPosition
  })
  return path
})

const labelPosition = computed(() => {
  const [, labelX, labelY] = getBezierPath({
    sourceX: props.sourceX,
    sourceY: props.sourceY,
    sourcePosition: props.sourcePosition,
    targetX: props.targetX,
    targetY: props.targetY,
    targetPosition: props.targetPosition
  })
  return { x: labelX, y: labelY }
})

// Extract amount from edge data
const amount = computed(() => props.data?.amount)

// Determine if amount is negative or zero
const isNegative = computed(() => amount.value !== null && amount.value < 0)
const isZero = computed(() => amount.value !== null && amount.value === 0)

// Stroke color based on amount and hover state
const strokeColor = computed(() => {
  if (isHovered.value) return '#ec4899' // pink when hovered
  if (isNegative.value) return '#ef4444' // red
  if (isZero.value) return '#f59e0b' // amber
  return '#34d399' // green
})

const strokeWidth = computed(() => {
  if (isHovered.value) return 4 // Thicker when hovered
  return amount.value !== null ? 3 : 2
})

const markerId = computed(() => {
  // if (isHovered.value) return 'arrowhead-pink' // pink arrowhead when hovered
  return isNegative.value ? 'arrowhead-red' : 'arrowhead-green'
})

const edgeClass = computed(() => {
  return '' // Disable animated class to avoid dash array animations
})

// Format amount for display
const formattedAmount = computed(() => {
  if (amount.value === null || amount.value === undefined) return ''
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount.value)
})

// Label dimensions and styling
const labelWidth = computed(() => {
  const length = formattedAmount.value.length
  return Math.max(60, length * 8)
})

const labelBgColor = computed(() => {
  if (isHovered.value) return '#fbcfe8' // pink-200 when hovered
  if (isNegative.value) return '#fecaca' // red-200
  if (isZero.value) return '#fde68a' // amber-200
  return '#d1fae5' // green-200
})

const labelTextColor = computed(() => {
  if (isHovered.value) return '#831843' // pink-900 when hovered
  if (isNegative.value) return '#7f1d1d' // red-900
  if (isZero.value) return '#78350f' // amber-900
  return '#065f46' // green-900
})

// Handle mouse enter with immediate hover state
const handleMouseEnter = () => {
  if (mouseLeaveTimeout) {
    clearTimeout(mouseLeaveTimeout)
    mouseLeaveTimeout = null
  }
  isHovered.value = true
}

// Handle mouse leave with debounce
const handleMouseLeave = () => {
  mouseLeaveTimeout = setTimeout(() => {
    isHovered.value = false
  }, 0)
}

// Handle remove edge action
const handleRemove = (event) => {
  event.stopPropagation()
  emit('remove', props.id)
  isHovered.value = false
  isHoveringDelete.value = false
}

// Handle add node action
const handleAddNode = (event) => {
  event.stopPropagation()
  emit('addNode', {
    edgeId: props.id,
    source: props.source,
    target: props.target,
    position: labelPosition.value
  })
  isHovered.value = false
  isHoveringAdd.value = false
}
</script>

<style scoped>
/* Prevent button icon paths from being animated by parent .vue-flow__edge.animated */
.button-icon .icon-path {
  animation: none !important;
  stroke-dasharray: none !important;
  stroke-dashoffset: 0 !important;
}

/* Prevent hover halo from being animated to avoid jagged edges */
.hover-halo {
  animation: none !important;
  stroke-dasharray: none !important;
  stroke-dashoffset: 0 !important;
}

/* Prevent main connection path from being animated */
.connection-path {
  animation: none !important;
  stroke-dasharray: none !important;
  stroke-dashoffset: 0 !important;
}

/* Smooth fade transition for hover action buttons */
.hover-actions {
  opacity: 0;
  transition: opacity 0.2s ease-in-out;
}

.hover-actions.is-visible {
  opacity: 1;
}
</style>
