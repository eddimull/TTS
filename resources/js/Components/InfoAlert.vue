<template>
  <div 
    class="rounded-lg p-3 border"
    :class="[
      variant === 'info' && 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800',
      variant === 'warning' && 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800',
      variant === 'success' && 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800',
      variant === 'error' && 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'
    ]"
  >
    <div class="flex items-start">
      <i 
        :class="[
          'pi mr-2 mt-0.5',
          variant === 'info' && 'pi-info-circle text-blue-600 dark:text-blue-400',
          variant === 'warning' && 'pi-exclamation-triangle text-yellow-600 dark:text-yellow-400',
          variant === 'success' && 'pi-check-circle text-green-600 dark:text-green-400',
          variant === 'error' && 'pi-times-circle text-red-600 dark:text-red-400'
        ]"
      />
      <div class="text-sm text-gray-700 dark:text-gray-300">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  variant: {
    type: String,
    default: 'info',
    validator: (value) => ['info', 'warning', 'success', 'error'].includes(value)
  }
})
</script>
