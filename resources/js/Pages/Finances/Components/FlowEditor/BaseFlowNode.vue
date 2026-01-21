<template>
  <div
    class="relative"
    @mouseenter="handleMouseEnter"
    @mouseleave="handleMouseLeave"
  >
    <!-- Hover Toolbar -->
    <Transition name="fade-toolbar">
      <div
        v-if="isHovered"
        class="absolute -top-16 left-1/2 transform -translate-x-1/2 z-50"
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
      >
        <div class="flex items-center gap-2 bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-600 rounded-full shadow-lg px-3 py-2">
          <button
            @click.stop="toggleDeactivated"
            class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-orange-500 hover:text-white transition-colors text-gray-700 dark:text-gray-300"
            v-tooltip.bottom="'Deactivate Node'"
          >
            <i class="pi pi-power-off text-sm" />
          </button>

          <!-- Delete Button -->
          <button
            @click.stop="handleDelete"
            class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-red-500 hover:text-white transition-colors text-gray-700 dark:text-gray-300"
            v-tooltip.bottom="'Delete Node'"
          >
            <i class="pi pi-trash text-sm" />
          </button>

          <!-- Three-dots Menu -->
          <button
            @click="showContextMenu($event)"
            class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-blue-500 hover:text-white transition-colors text-gray-700 dark:text-gray-300"
            v-tooltip.bottom="'More Actions'"
          >
            <i class="pi pi-ellipsis-v text-sm" />
          </button>
        </div>
      </div>
    </Transition>

    <!-- Node Content -->
    <div
      class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border-2 p-4 transition-opacity relative"
      :class="[borderColorClass, widthClass, (data.deactivated || false) ? 'opacity-50 grayscale' : '']"
    >
      <!-- Deactivation Badge -->
      <div
        v-if="(data.deactivated || false)"
        class="absolute -top-2 -right-2 bg-orange-500 text-white rounded-full w-6 h-6 flex items-center justify-center shadow-md z-10 cursor-pointer"
        v-tooltip.left="'Node Deactivated - Click to reactivate'"
        @click.stop="toggleDeactivated"
      >
        <i class="pi pi-power-off text-xs" />
      </div>

      <!-- Handles -->
    <slot name="handles" :Position="Position">
      <Handle
        v-for="handle in handles"
        :key="handle.id"
        :type="handle.type"
        :position="handle.position"
        :id="handle.id"
        :style="handle.style"
      />
    </slot>

    <!-- Header -->
    <div class="flex items-center mb-3" :class="headerJustify">
      <div class="flex items-center gap-2">
        <slot name="icon">
          <i v-if="icon" :class="[icon, iconColorClass, 'text-xl']" />
        </slot>
        <div>
          <slot name="title">
            <span class="font-bold text-gray-800 dark:text-gray-100">{{ data.customLabel || title }}</span>
          </slot>
          <slot name="subtitle">
            <div v-if="subtitle" class="text-xs text-gray-500 dark:text-gray-400">{{ subtitle }}</div>
          </slot>
        </div>
      </div>
      <slot name="header-actions" />
    </div>

    <!-- Main Content -->
    <slot name="content" />

    <!-- Calculated Values Footer -->
    <div
      v-if="showCalculations && (data.input !== undefined || calculatedValues.length > 0)"
      class="mt-3 pt-2 border-t dark:border-gray-700"
    >
      <slot name="calculations">
        <div class="space-y-1 text-sm">
          <div v-for="calc in calculatedValues" :key="calc.label" class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-400">{{ calc.label }}:</span>
            <span :class="calc.class || 'font-medium'">
              {{ formatValue(calc) }}
            </span>
          </div>
        </div>
      </slot>
    </div>
    </div>

    <!-- Context Menu -->
    <ContextMenu ref="contextMenu" :model="contextMenuItems" />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Handle, Position } from '@vue-flow/core'
import ContextMenu from 'primevue/contextmenu'

const props = defineProps({
  id: {
    type: String,
    required: true
  },
  data: {
    type: Object,
    required: true
  },
  // Styling props
  borderColor: {
    type: String,
    default: 'border-blue-500'
  },
  iconColor: {
    type: String,
    default: 'text-blue-500'
  },
  width: {
    type: String,
    default: 'min-w-[200px]'
  },
  // Header props
  icon: {
    type: String,
    default: null
  },
  title: {
    type: String,
    default: ''
  },
  subtitle: {
    type: String,
    default: null
  },
  headerJustify: {
    type: String,
    default: 'justify-start'
  },
  // Handle configuration
  handles: {
    type: Array,
    default: () => []
  },
  // Calculations display
  showCalculations: {
    type: Boolean,
    default: true
  },
  calculatedValues: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update', 'settings', 'delete', 'duplicate', 'rename'])

// Hover state management
const isHovered = ref(false)
const contextMenu = ref(null)
let mouseLeaveTimeout = null

const handleMouseEnter = () => {
  if (mouseLeaveTimeout) {
    clearTimeout(mouseLeaveTimeout)
    mouseLeaveTimeout = null
  }
  isHovered.value = true
}

const handleMouseLeave = () => {
  mouseLeaveTimeout = setTimeout(() => {
    isHovered.value = false
  }, 200)
}

// Action handlers
const toggleDeactivated = () => {
  emit('update', props.id, {
    ...props.data,
    deactivated: !(props.data.deactivated || false)
  })
}

const handleDelete = () => {
  emit('delete', props.id)
}

const showContextMenu = (event) => {
  if (contextMenu.value) {
    contextMenu.value.show(event)
  }
}

// Context menu items
const contextMenuItems = computed(() => [
  {
    label: (props.data.deactivated || false) ? 'Activate' : 'Deactivate',
    icon: 'pi pi-power-off',
    command: toggleDeactivated
  },
  { separator: true },
  {
    label: 'Rename/Edit Label',
    icon: 'pi pi-pencil',
    command: () => emit('rename', props.id)
  },
  {
    label: 'Duplicate',
    icon: 'pi pi-copy',
    command: () => emit('duplicate', props.id)
  },
  { separator: true },
  {
    label: 'Delete',
    icon: 'pi pi-trash',
    command: handleDelete,
    class: 'text-red-500'
  }
])

const borderColorClass = computed(() => props.borderColor)
const iconColorClass = computed(() => props.iconColor)
const widthClass = computed(() => props.width)

const moneyFormat = (num) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(num)
}

const formatValue = (calc) => {
  if (calc.format === 'money') {
    return moneyFormat(calc.value)
  }
  return calc.value
}

defineExpose({ moneyFormat })
</script>

<style scoped>
:deep(.vue-flow__handle) {
  width: 12px;
  height: 12px;
  border: 2px solid white;
}

:deep(.vue-flow__handle:hover) {
  border-color: #f63bdd; 
  border-width: 5px;
}
:deep(.vue-flow__handle.connectable) {
  cursor: pointer;
}

/* Fade transition for toolbar */
.fade-toolbar-enter-active,
.fade-toolbar-leave-active {
  transition: opacity 0.2s ease-in-out;
}

.fade-toolbar-enter-from,
.fade-toolbar-leave-to {
  opacity: 0;
}

/* Deactivated node styling */
.grayscale {
  filter: grayscale(100%);
}
</style>
