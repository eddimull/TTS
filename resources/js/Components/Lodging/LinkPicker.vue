<template>
  <div>
    <Label
      :value="label"
    />
    <input
      v-model="filterText"
      type="text"
      placeholder="Filter…"
      data-testid="link-picker-filter"
      class="w-full p-2 border rounded dark:bg-slate-700 dark:text-gray-50 mb-2"
    >
    <div class="max-h-56 overflow-y-auto border rounded dark:border-slate-600">
      <button
        type="button"
        data-testid="link-picker-option"
        class="w-full text-left px-2 py-1.5"
        :class="modelValue === null ? 'bg-blue-500/10 text-blue-500' : 'dark:text-gray-50'"
        @click="select(null)"
      >
        None
      </button>

      <template
        v-for="group in groups"
        :key="group.label || 'ungrouped'"
      >
        <div
          v-if="group.label"
          class="text-xs font-semibold text-gray-500 dark:text-gray-400 px-2 pt-2"
        >
          {{ group.label }}
        </div>
        <button
          v-for="option in group.options"
          :key="option.id"
          type="button"
          data-testid="link-picker-option"
          class="w-full text-left px-2 py-1.5"
          :class="option.id === modelValue ? 'bg-blue-500/10 text-blue-500' : 'dark:text-gray-50'"
          @click="select(option.id)"
        >
          {{ option.name ?? option.title }}
          <span
            v-if="option.date"
            class="text-xs text-gray-500 dark:text-gray-400 ml-1"
          >
            {{ formatDate(option.date) }}
          </span>
        </button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { DateTime } from 'luxon';
import Label from '@/Components/Label.vue';
import { groupLinkOptions } from '@/utils/lodgingLinkOptions';

const props = defineProps({
    modelValue: { type: Number, default: null },
    options: { type: Array, default: () => [] },
    checkIn: { type: String, default: null },
    checkOut: { type: String, default: null },
    label: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const filterText = ref('');

const filtered = computed(() => {
    const term = filterText.value.trim().toLowerCase();
    if (!term) return props.options;
    return props.options.filter((o) => (o.name ?? o.title ?? '').toLowerCase().includes(term));
});

const groups = computed(() => groupLinkOptions(filtered.value, props.checkIn, props.checkOut));

const formatDate = (date) => DateTime.fromISO(date).toFormat('EEE, MMM d, yyyy');

const select = (id) => emit('update:modelValue', id);
</script>
