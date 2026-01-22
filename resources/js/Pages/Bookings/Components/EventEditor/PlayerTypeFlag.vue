<template>
    <div>
        <span
        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200"
        >
        {{ label }}
        </span>
    </div>
</template>
<script setup>
const props = defineProps({
    label: {
        type: String,
        required: false
    }
});
</script>