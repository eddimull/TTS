<template>
  <div class="booking-layout overflow-x-hidden">
    <ResponsiveSubNav
      :items="items"
      :active-item-matcher="(item) => $page.url === item.href"
      :preserve-scroll="true"
      :preserve-state="true"
    >
      <template #header>
        <div class="booking-header dark:bg-slate-700 p-4 border-b grid max-w-full">
          <h1 class="text-3xl font-semibold text-gray-700 py-2 truncate">
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
      </template>
    </ResponsiveSubNav>
  </div>
</template>

<script setup>
import { computed } from "vue";
import ResponsiveSubNav from "@/Components/ResponsiveSubNav.vue";

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
</style>
