<template>
    <Link :class="[
                {
                    'bg-blue-200':unread,
                    'hover:bg-blue-300':unread,
                    'hover:bg-gray-100':!unread,
                },
                'block', 
                'w-full',
                'px-4', 
                'py-2', 
                'text-left', 
                'text-sm', 
                'leading-5', 
                'text-gray-700', 
                'focus:outline-none',
                'focus:bg-gray-100',
                'transition duration-150',
                'ease-in-out']"
                preserve-state
                >

        <slot />
    </Link>
</template>
<script>
    export default {
        props:['unread']
    }
</script>