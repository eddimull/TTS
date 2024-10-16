<template>
  <div class="booking-layout">
    <div class="booking-header dark:bg-slate-700 p-4 border-b grid">
      <h1 class="text-xl font-semibold text-gray-700">
        <span class="text-blue-600">{{ booking.name }}</span>
      </h1>
      <span class="mt-0 text-xs text-gray-500 dark:text-gray-50">{{ booking.date }}</span>
      <span class="mt-0 text-xs text-gray-500 dark:text-gray-50">Status: {{ booking.status }}</span>
    </div>
    
    <TabMenu
      :model="items"
      class="pb-2 border-b"
      :activeIndex="items.findIndex(item => item.href === $page.url) || 0"
    >
      <template #item="{ item }">
        <Link
          :href="item.href"
          :active="$page.url === item.href"
          custom
          preserve-scroll
          preserve-state
        >
          <a
            role="menuitem"
            class="p-menuitem-link"
          >
            <span class="p-menuitem-text">{{ item.label }}</span>
          </a>
        </Link>
      </template>
    </TabMenu>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import TabMenu from 'primevue/tabmenu';
import Link from '@/Components/NavLink.vue';
import { usePage } from '@inertiajs/inertia-vue3';


// Find the current route params
const routeParameters = route().params;
const props = defineProps({
    routes: {
        type: Object,
        required: true
    },
    booking: {
        type: Object,
        required: true
    }
});


const items = computed(() => {
    
    return Object.entries(props.routes).map(([name, routeInfo]) => ({
        label: name,
        route: routeInfo.uri,
        href: route(name, routeParameters,false)
    }));
});

let activeItemIndex = ref(0);




</script>

<style scoped>
.booking-layout {
    display: flex;
    flex-direction: column;
    /* min-height: 100vh; */
}

.booking-header {
    margin-left: -1rem;
    margin-right: -1rem;
}
</style>