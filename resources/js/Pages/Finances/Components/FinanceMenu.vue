<template>
    <div>
        <TabMenu
            :model="items"
            class="py-2"
            :activeIndex="
                items.findIndex((item) => route().current() === item.label) || 0
            "
        >
            <template #item="{ item }">
                <Link
                    :href="route(item.label)"
                    :active="route().current() === item.label"
                    custom
                >
                    <a role="menuitem" class="p-menuitem-link">
                        <span class="p-menuitem-text text-ellipsis truncate">{{
                            item.label
                        }}</span>
                    </a>
                </Link>
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
});

const items = computed(() => {
    return Object.entries(props.routes).map(([name, route]) => ({
        label: name,
        route: route.uri,
    }));
});
</script>
