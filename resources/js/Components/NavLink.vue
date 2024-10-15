<template>
  <Link
    :href="href"
    :class="classes"
    preserve-scroll
    preserve-state
  >
    <slot />
  </Link>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePage } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    href: String,
    active: Boolean
})

const classes = computed(() =>
    props.active
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 text-sm font-medium leading-5 text-gray-900 dark:text-gray-100 focus:outline-none focus:border-indigo-700 transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 dark:text-gray-50 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out'
)

const page = usePage()

router.on('navigate', (event) => {
    page.props.url = event.detail?.page?.url
})
</script>
