<template>
  <div class="booking-layout">
    <div class="booking-header dark:bg-slate-700 p-4 border-b grid">
      <h1 class="text-3xl font-semibold text-gray-700 py-2">
        <span class="text-black dark:text-white">{{
          booking.name
        }}</span>
      </h1>
      <span class="mt-0 text-xs text-gray-500 dark:text-gray-50">{{
        booking.date
      }}</span>
      <span
        class="mt-0 text-xs text-gray-500 dark:text-gray-50"
      >Status: {{ booking.status }}</span>
    </div>
    <TabMenu
      :model="items"
      class="pb-2 border-b md:flex md:flex-row flex-col w-full"
      :active-index="
        items.findIndex((item) => item.href === $page.url) || 0
      "
    >
      <template #item="{ item }">
        <div class="flex justify-evenly items-center p-2">
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
              <span class="p-menuitem-text">{{
                item.label
              }}</span>
            </a>
          </Link>
        </div>
      </template>
    </TabMenu>
  </div>
</template>

<script setup>
import { computed } from "vue";
import TabMenu from "primevue/tabmenu";
import Link from "@/Components/NavLink.vue";

const props = defineProps({
    routes: {
        type: Object,
        required: true,
    },
    booking: {
        type: Object,
        required: true,
    },
});

const items = computed(() => {
    const routeParameters = { ...route().params, booking: props.booking.id };
    return Object.entries(props.routes).map(([name, routeInfo]) => ({
        label: name.replace("Booking ", ""),
        route: routeInfo.uri,
        href: route(name, routeParameters, false),
    }));
});

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
