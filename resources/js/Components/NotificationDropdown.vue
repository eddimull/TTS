<template>
    <Link
        :class="[
            {
                'bg-blue-200 dark:bg-blue-800': unread,
                'hover:bg-blue-300 dark:hover:bg-blue-900': unread,
                'hover:bg-gray-100 dark:hover:bg-gray-700': !unread,
            },
            'block',
            'w-full',
            'px-4',
            'py-2',
            'text-left',
            'text-sm',
            'leading-5',
            'text-gray-700',
            'dark:text-gray-300',
            'focus:outline-none',
            'focus:bg-gray-100',
            'dark:focus:bg-gray-700',
            'transition duration-150',
            'ease-in-out',
        ]"
        preserve-state
    >
        <slot />
    </Link>
</template>
<script>
export default {
    props: ["unread"],
};
</script>
