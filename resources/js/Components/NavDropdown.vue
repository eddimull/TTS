<template>
  <div
    ref="dropdownRef"
    class="relative nav-dropdown"
  >
    <!-- Trigger Button -->
    <button
      :id="triggerId"
      ref="triggerRef"
      type="button"
      :aria-expanded="isOpen.toString()"
      :aria-controls="menuId"
      aria-haspopup="true"
      class="nav-dropdown-trigger"
      :class="{ 'active': isActiveGroup }"
      @click="toggleMenu"
      @keydown="handleTriggerKeydown"
    >
      <span>{{ label }}</span>
      <svg
        class="chevron"
        :class="{ 'rotate-180': isOpen }"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 20 20"
        fill="currentColor"
      >
        <path
          fill-rule="evenodd"
          d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
          clip-rule="evenodd"
        />
      </svg>
    </button>

    <!-- Dropdown Menu -->
    <transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div
        v-if="isOpen"
        :id="menuId"
        ref="menuRef"
        role="menu"
        :aria-labelledby="triggerId"
        aria-orientation="vertical"
        class="nav-dropdown-menu sm:items-end"
        @keydown="handleMenuKeydown"
      >
        <Link
          v-for="(item, index) in items"
          :key="item.routeName"
          :ref="el => setMenuItemRef(el, index)"
          role="menuitem"
          :tabindex="focusedIndex === index ? 0 : -1"
          :href="route(item.routeName)"
          :aria-current="item.active ? 'page' : undefined"
          class="nav-dropdown-item"
          :class="{ 'active': item.active }"
          @click="handleItemClick"
          @mouseenter="focusedIndex = index"
        >
          {{ item.label }}
        </Link>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onBeforeUpdate } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useClickOutside } from '@/composables/useClickOutside';

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

// State
const isOpen = ref(false);
const focusedIndex = ref(0);
const dropdownRef = ref(null);
const triggerRef = ref(null);
const menuRef = ref(null);
const menuItemRefs = ref([]);

// Computed IDs for ARIA
const triggerId = computed(() => `nav-dropdown-trigger-${props.groupId}`);
const menuId = computed(() => `nav-dropdown-menu-${props.groupId}`);

// Setup click outside detection
useClickOutside(dropdownRef, () => {
  if (isOpen.value) {
    closeMenu();
  }
});

// Menu item refs management
const setMenuItemRef = (el, index) => {
  if (el) {
    menuItemRefs.value[index] = el;
  }
};

// Clear refs before each update
onBeforeUpdate(() => {
  menuItemRefs.value = [];
});

// Menu control functions
const openMenu = () => {
  isOpen.value = true;
};

const closeMenu = () => {
  isOpen.value = false;
  focusedIndex.value = 0;
};

const toggleMenu = () => {
  if (isOpen.value) {
    closeMenu();
  } else {
    openMenu();
    // Focus first item after menu opens
    nextTick(() => focusMenuItem(0));
  }
};

// Focus management
const focusMenuItem = (index) => {
  if (index >= 0 && index < props.items.length) {
    focusedIndex.value = index;
    nextTick(() => {
      const item = menuItemRefs.value[index];
      if (item?.$el) {
        item.$el.focus();
      } else if (item) {
        item.focus();
      }
    });
  }
};

const focusNextItem = () => {
  const nextIndex = (focusedIndex.value + 1) % props.items.length;
  focusMenuItem(nextIndex);
};

const focusPreviousItem = () => {
  const prevIndex = focusedIndex.value === 0
    ? props.items.length - 1
    : focusedIndex.value - 1;
  focusMenuItem(prevIndex);
};

// Keyboard event handlers
const handleTriggerKeydown = (event) => {
  switch (event.key) {
    case 'Enter':
    case ' ':
    case 'ArrowDown':
      event.preventDefault();
      openMenu();
      // Focus first item after menu opens
      nextTick(() => focusMenuItem(0));
      break;
    case 'ArrowUp':
      event.preventDefault();
      openMenu();
      // Focus last item after menu opens
      nextTick(() => focusMenuItem(props.items.length - 1));
      break;
    case 'Escape':
      if (isOpen.value) {
        event.preventDefault();
        closeMenu();
      }
      break;
  }
};

const handleMenuKeydown = (event) => {
  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault();
      focusNextItem();
      break;
    case 'ArrowUp':
      event.preventDefault();
      focusPreviousItem();
      break;
    case 'Home':
      event.preventDefault();
      focusMenuItem(0);
      break;
    case 'End':
      event.preventDefault();
      focusMenuItem(props.items.length - 1);
      break;
    case 'Escape':
      event.preventDefault();
      closeMenu();
      // Return focus to trigger
      nextTick(() => {
        if (triggerRef.value) {
          triggerRef.value.focus();
        }
      });
      break;
    case 'Tab':
      // Allow tab to close and move to next element
      closeMenu();
      break;
    case 'Enter':
    case ' ':
      event.preventDefault();
      // Link component handles navigation
      // Just close the menu
      closeMenu();
      break;
  }
};

const handleItemClick = () => {
  closeMenu();
};
</script>

<style scoped>
/* Component-specific styles are in app.css */
</style>
