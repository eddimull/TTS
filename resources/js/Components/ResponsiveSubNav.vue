<template>
    <div class="responsive-subnav-container">
        <!-- Optional Header Slot -->
        <slot name="header" />

        <!-- Mobile: Dropdown Select -->
        <div class="block md:hidden py-2">
            <Select
                v-model="selectedItem"
                :options="items"
                optionLabel="label"
                placeholder="Select a page..."
                class="w-full"
                @change="handleNavigation"
            />
        </div>

        <!-- Desktop: Tab Menu -->
        <TabMenu
            :model="items"
            class="hidden md:block py-2"
            :activeIndex="activeIndex"
        >
            <template #item="{ item }">
                <Link
                    v-if="item.href"
                    :href="item.href"
                    :active="isActiveItem(item)"
                    custom
                    :preserve-scroll="preserveScroll"
                    :preserve-state="preserveState"
                >
                    <a role="menuitem" class="p-menuitem-link">
                        <span class="p-menuitem-text">{{ item.label }}</span>
                    </a>
                </Link>
                <Link
                    v-else
                    :href="route(item.routeName)"
                    :active="isActiveItem(item)"
                    custom
                >
                    <a role="menuitem" class="p-menuitem-link">
                        <span class="p-menuitem-text">{{ item.label }}</span>
                    </a>
                </Link>
            </template>
        </TabMenu>
    </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import TabMenu from "primevue/tabmenu";
import Select from "primevue/select";
import Link from "@/Components/NavLink.vue";

const props = defineProps({
    items: {
        type: Array,
        required: true,
        // Items should have structure: { label, href?, routeName? }
    },
    // Optional function to determine if an item is active
    // Signature: (item) => boolean
    activeItemMatcher: {
        type: Function,
        default: null,
    },
    preserveScroll: {
        type: Boolean,
        default: false,
    },
    preserveState: {
        type: Boolean,
        default: false,
    },
});

// For mobile select dropdown
const selectedItem = ref(null);

// Determine if an item is active
const isActiveItem = (item) => {
    if (props.activeItemMatcher) {
        return props.activeItemMatcher(item);
    }

    // Default matchers
    if (item.href) {
        return window.location.pathname === new URL(item.href, window.location.origin).pathname;
    }

    if (item.routeName) {
        return route().current() === item.routeName;
    }

    return false;
};

// Calculate active index for tab menu
const activeIndex = computed(() => {
    return props.items.findIndex(item => isActiveItem(item)) || 0;
});

// Set initial selected item and watch for changes
watch(() => props.items, (newItems) => {
    const currentItem = newItems.find(item => isActiveItem(item));
    if (currentItem) {
        selectedItem.value = currentItem;
    }
}, { immediate: true });

// Handle navigation from mobile dropdown
const handleNavigation = () => {
    if (!selectedItem.value) return;

    if (selectedItem.value.href) {
        router.get(selectedItem.value.href, {}, {
            preserveScroll: props.preserveScroll,
            preserveState: props.preserveState,
        });
    } else if (selectedItem.value.routeName) {
        router.get(route(selectedItem.value.routeName));
    }
};
</script>

<style scoped>
/* Optional: Add custom styling if needed */
</style>
