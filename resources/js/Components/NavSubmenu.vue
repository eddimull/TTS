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
          <div class="flex items-center gap-2 flex-wrap py-2">
            <h1 class="text-3xl font-semibold text-gray-700 truncate">
              <span class="text-black dark:text-white">{{
                booking.name
              }}</span>
            </h1>
            <span
              v-if="booking.is_multi_event"
              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
            >
              {{ booking.event_count }} events
            </span>
          </div>
          <EngagementSummary :booking="booking" />
          <span
            class="mt-1 text-xs text-gray-500 dark:text-gray-50"
          >Status: {{ booking.status }}</span>
        </div>
      </template>
    </ResponsiveSubNav>
  </div>
</template>

<script setup>
import { computed } from "vue";
import ResponsiveSubNav from "@/Components/ResponsiveSubNav.vue";
import EngagementSummary from "@/Pages/Bookings/Components/EngagementSummary.vue";

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
