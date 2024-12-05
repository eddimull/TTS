<template>
    <Link :class="classes">
        <slot />
    </Link>
</template>

<script>
export default {
    props: ["active"],

    computed: {
        classes() {
            return this.active
                ? "block pl-3 pr-4 py-2 border-l-4 border-indigo-400 text-base font-medium text-indigo-700 dark:text-indigo-300 focus:outline-none focus:text-indigo-800 dark:focus:text-indigo-200 dark:focus:border-indigo-300 dark:border-indigo-500 transition duration-150 ease-in-out"
                : "block pl-3 pr-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-gray-100 hover:bg-gray-50 dark:hover:bg-slate-600 hover:border-gray-300 dark:hover:border-gray-600 focus:outline-none focus:text-gray-800 dark:focus:text-gray-100 focus:border-gray-300 dark:focus:border-gray-600 transition duration-150 ease-in-out";
        },
    },
};
</script>
