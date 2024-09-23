<script setup>
import { computed } from 'vue';
import TabMenu from 'primevue/tabmenu';
import Link from '@/Components/NavLink.vue';
import { usePage } from '@inertiajs/inertia-vue3';


// Find the current route params
const routeParameters = route().params;
const props = defineProps({
    routes: {
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

</script>

<template>
  <div>
    <TabMenu
      :model="items"
      class="py-2"
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