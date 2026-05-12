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
          <div class="flex justify-between items-start gap-3 flex-wrap">
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-50 truncate">
                  {{ booking.name }}
                </h1>
                <span
                  v-if="booking.is_multi_event"
                  class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                >
                  {{ booking.event_count }} events
                </span>
              </div>
              <div
                v-if="eventType"
                class="inline-block bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-1 rounded text-xs font-medium mt-2"
              >
                <i class="pi pi-tag mr-1" />
                {{ eventType.name }}
              </div>
            </div>
            <span
              data-test="status-pill"
              :class="statusClass"
              class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide whitespace-nowrap"
            >
              {{ booking.status }}
            </span>
          </div>
          <EngagementSummary :booking="booking" class="mt-2" />
        </div>
      </template>
    </ResponsiveSubNav>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useStore } from "vuex";
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

const store = useStore();

const eventType = computed(() => {
    const types = store.getters["eventTypes/getAllEventTypes"];
    return types.find((type) => type.id === props.booking.event_type_id);
});

const statusClass = computed(() => {
    const statusClasses = {
        draft: "bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200",
        pending: "bg-yellow-200 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200",
        confirmed: "bg-green-200 text-green-800 dark:bg-green-900 dark:text-green-200",
        cancelled: "bg-red-200 text-red-800 dark:bg-red-900 dark:text-red-200",
    };
    return statusClasses[props.booking.status] || statusClasses.draft;
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
}
</style>
