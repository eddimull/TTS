<template>
  <button 
    :type="type" 
    :class="buttonClasses"
    :disabled="disabled"
  >
    <slot />
  </button>
</template>

<script>
export default {
    props: {
        type: {
            type: String,
            default: 'submit',
        },
        variant: {
            type: String,
            default: 'primary',
            validator: (value) => ['primary', 'secondary', 'success', 'danger', 'warning', 'info'].includes(value)
        },
        size: {
            type: String,
            default: 'md',
            validator: (value) => ['sm', 'md', 'lg'].includes(value)
        },
        disabled: {
            type: Boolean,
            default: false
        },
        outline: {
            type: Boolean,
            default: false
        }
    },
    computed: {
        buttonClasses() {
            const baseClasses = 'inline-flex items-center border rounded-md font-semibold uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed';
            
            const sizeClasses = {
                sm: 'px-3 py-1.5 text-xs',
                md: 'px-4 py-2 text-xs',
                lg: 'px-6 py-3 text-sm'
            };

            const variantClasses = this.outline ? this.outlineVariantClasses : this.solidVariantClasses;

            return `${baseClasses} ${sizeClasses[this.size]} ${variantClasses[this.variant]}`;
        },
        solidVariantClasses() {
            return {
                primary: 'bg-gray-800 border-transparent text-white hover:bg-gray-700 active:bg-gray-900 focus:ring-gray-500 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-gray-300 dark:active:bg-gray-100 dark:focus:ring-gray-400',
                secondary: 'bg-gray-500 border-transparent text-white hover:bg-gray-400 active:bg-gray-600 focus:ring-gray-400 dark:bg-gray-600 dark:hover:bg-gray-500 dark:active:bg-gray-700',
                success: 'bg-green-600 border-transparent text-white hover:bg-green-500 active:bg-green-700 focus:ring-green-500 dark:bg-green-500 dark:hover:bg-green-400 dark:active:bg-green-600',
                danger: 'bg-red-600 border-transparent text-white hover:bg-red-500 active:bg-red-700 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-400 dark:active:bg-red-600',
                warning: 'bg-yellow-500 border-transparent text-white hover:bg-yellow-400 active:bg-yellow-600 focus:ring-yellow-400 dark:bg-yellow-400 dark:text-gray-900 dark:hover:bg-yellow-300 dark:active:bg-yellow-500',
                info: 'bg-blue-600 border-transparent text-white hover:bg-blue-500 active:bg-blue-700 focus:ring-blue-500 dark:bg-blue-500 dark:hover:bg-blue-400 dark:active:bg-blue-600'
            };
        },
        outlineVariantClasses() {
            return {
                primary: 'bg-transparent border-gray-800 text-gray-800 hover:bg-gray-800 hover:text-white focus:ring-gray-500 dark:border-gray-200 dark:text-gray-200 dark:hover:bg-gray-200 dark:hover:text-gray-800',
                secondary: 'bg-transparent border-gray-500 text-gray-500 hover:bg-gray-500 hover:text-white focus:ring-gray-400 dark:border-gray-400 dark:text-gray-400 dark:hover:bg-gray-400 dark:hover:text-gray-900',
                success: 'bg-transparent border-green-600 text-green-600 hover:bg-green-600 hover:text-white focus:ring-green-500 dark:border-green-400 dark:text-green-400 dark:hover:bg-green-400 dark:hover:text-gray-900',
                danger: 'bg-transparent border-red-600 text-red-600 hover:bg-red-600 hover:text-white focus:ring-red-500 dark:border-red-400 dark:text-red-400 dark:hover:bg-red-400 dark:hover:text-gray-900',
                warning: 'bg-transparent border-yellow-500 text-yellow-500 hover:bg-yellow-500 hover:text-white focus:ring-yellow-400 dark:border-yellow-400 dark:text-yellow-400 dark:hover:bg-yellow-400 dark:hover:text-gray-900',
                info: 'bg-transparent border-blue-600 text-blue-600 hover:bg-blue-600 hover:text-white focus:ring-blue-500 dark:border-blue-400 dark:text-blue-400 dark:hover:bg-blue-400 dark:hover:text-gray-900'
            };
        }
    }
}
</script>
