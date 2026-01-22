import { ref, watch } from 'vue'

/**
 * Composable for flow node common logic
 * Handles local state management and prop synchronization
 */
export function useFlowNode(props, emit) {
  /**
   * Money formatting utility
   */
  const moneyFormat = (num) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD'
    }).format(num)
  }

  /**
   * Create a synced local ref for a data property
   * Automatically watches for external changes and syncs bidirectionally
   *
   * @param {string} key - The property key in props.data
   * @param {*} defaultValue - Default value if property doesn't exist
   * @param {boolean} parseNumber - Whether to parse strings to numbers
   */
  const useSyncedRef = (key, defaultValue, parseNumber = false) => {
    // Initialize local ref
    const getValue = (val) => {
      if (parseNumber && typeof val === 'string') {
        return parseFloat(val) || defaultValue
      }
      return val ?? defaultValue
    }

    const localRef = ref(getValue(props.data[key]))

    // Watch for external changes to props.data[key]
    watch(() => props.data[key], (newVal) => {
      const processedVal = getValue(newVal)
      if (processedVal !== localRef.value) {
        localRef.value = processedVal
      }
    })

    return localRef
  }

  /**
   * Create an update handler that emits changes to parent
   * Merges provided updates with existing data
   */
  const createUpdateHandler = (updates = {}) => {
    return () => {
      emit('update', props.id, {
        ...props.data,
        ...updates
      })
    }
  }

  /**
   * Create a simple emit update function with immediate data
   */
  const emitUpdate = (updates) => {
    emit('update', props.id, {
      ...props.data,
      ...updates
    })
  }

  return {
    moneyFormat,
    useSyncedRef,
    createUpdateHandler,
    emitUpdate
  }
}
