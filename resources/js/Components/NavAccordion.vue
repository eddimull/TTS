<template>
  <div class="nav-accordion">
    <!-- Accordion Trigger -->
    <button
      :id="headerId"
      type="button"
      :aria-expanded="isExpanded.toString()"
      :aria-controls="panelId"
      class="nav-accordion-trigger"
      :class="{ 'active': isActiveGroup }"
      @click="toggleExpanded"
    >
      <span>{{ label }}</span>
      <svg
        class="chevron"
        :class="{ 'rotate-90': isExpanded }"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 20 20"
        fill="currentColor"
      >
        <path
          fill-rule="evenodd"
          d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
          clip-rule="evenodd"
        />
      </svg>
    </button>

    <!-- Accordion Panel -->
    <transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="max-h-0 opacity-0"
      enter-to-class="max-h-96 opacity-100"
      leave-active-class="transition-all duration-200 ease-in"
      leave-from-class="max-h-96 opacity-100"
      leave-to-class="max-h-0 opacity-0"
    >
      <div
        v-if="isExpanded"
        :id="panelId"
        role="region"
        :aria-labelledby="headerId"
        class="nav-accordion-panel overflow-hidden"
      >
        <breeze-responsive-nav-link
          v-for="item in items"
          :key="item.routeName"
          :href="route(item.routeName)"
          :active="item.active"
          class="pl-8"
        >
          {{ item.label }}
        </breeze-responsive-nav-link>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import BreezeResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';

const props = defineProps({
  label: {
    type: String,
    required: true
  },
  items: {
    type: Array,
    required: true
  },
  groupId: {
    type: String,
    required: true
  },
  isActiveGroup: {
    type: Boolean,
    default: false
  }
});

// State - auto-expand if group contains active page
const isExpanded = ref(false);

// Computed IDs for ARIA
const headerId = computed(() => `nav-accordion-header-${props.groupId}`);
const panelId = computed(() => `nav-accordion-panel-${props.groupId}`);

// Auto-expand active group on mount
onMounted(() => {
  if (props.isActiveGroup) {
    isExpanded.value = true;
  }
});

// Toggle function
const toggleExpanded = () => {
  isExpanded.value = !isExpanded.value;
};
</script>

<style scoped>
/* Component-specific styles are in app.css */
.nav-accordion-panel {
  /* Ensures smooth height transitions */
  transition-property: max-height, opacity;
}
</style>
