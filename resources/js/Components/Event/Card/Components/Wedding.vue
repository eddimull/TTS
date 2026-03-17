<template>
    <div class="max-w-md mx-auto bg-white dark:bg-slate-700 shadow-lg rounded-lg overflow-hidden">
        <ul class="divide-y divide-gray-200 dark:divide-slate-600">

            <li class="flex items-center p-4">
                <div class="ml-1 flex-grow">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Ceremony Location</p>
                </div>
                <Tag
                    :value="wedding?.onsite ? 'On-Site' : 'Off-Site'"
                    :severity="wedding?.onsite ? 'success' : 'danger'"
                    rounded
                />
            </li>

            <template v-if="wedding?.dances && wedding.dances.length > 0">
                <li v-for="dance in wedding.dances" :key="dance.name" class="flex flex-col p-4">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ dance.title }}</p>
                    <div class="mt-1">
                        <Tag :value="dance.data" severity="secondary" rounded />
                    </div>
                </li>
            </template>
            <li v-else class="flex items-center p-4">
                <div class="ml-1 flex-grow">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Dances</p>
                </div>
                <Tag value="None" severity="secondary" rounded />
            </li>

        </ul>
    </div>
</template>
  
  <script setup>
const props = defineProps({
    wedding: {
        type: Object,
        required: true
    }
});
</script>