<template>
  <ResponsiveSubNav
    :items="items"
    :active-item-matcher="(item) => route().current() === item.routeName"
  />
</template>

<script setup>
import { computed } from "vue";
import ResponsiveSubNav from "@/Components/ResponsiveSubNav.vue";

const props = defineProps({
    routes: {
        type: Object,
        required: true,
    },
});

const items = computed(() => {
    return Object.entries(props.routes).map(([name, routeInfo]) => ({
        label: name,
        routeName: name,
        route: routeInfo.uri,
    }));
});
</script>

<style scoped>
/* Optional: Add custom styling if needed */
</style>
