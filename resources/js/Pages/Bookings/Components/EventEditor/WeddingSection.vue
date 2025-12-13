<template>
  <div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <h4 class="font-semibold mb-4 text-gray-700 dark:text-gray-300">
          Dances
        </h4>
        <draggable
          v-model="dances"
          item-key="id"
          handle=".drag-handle"
          :scroll="false"
          :scroll-sensitivity="0"
          :touch-start-threshold="10"
          :force-fallback="true"
          :fallback-tolerance="3"
          :prevent-on-filter="false"
          v-bind="dragOptions"
          @start="onDragStart"
          @end="onDragEnd"
        >
          <template #item="{ element, index }">
            <div
              class="mb-4 p-3 border rounded-lg group relative"
            >
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2 flex-1">
                  <span class="drag-handle cursor-move opacity-50 hover:opacity-100 transition-opacity">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      class="h-5 w-5"
                      viewBox="0 0 20 20"
                      fill="currentColor"
                    >
                      <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                    </svg>
                  </span>
                  <label class="block font-medium">{{ element.title }}</label>
                </div>
                <button
                  type="button"
                  class="text-red-500 hover:text-red-700 transition-colors"
                  @click="removeDance(index)"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                  >
                    <path
                      fill-rule="evenodd"
                      d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                      clip-rule="evenodd"
                    />
                  </svg>
                </button>
              </div>
              <div class="flex items-center gap-2 mb-2">
                <input
                  :value="element.title"
                  type="text"
                  placeholder="Dance title"
                  class="w-full p-2 border dark:bg-slate-700 dark:text-gray-50 rounded"
                  @input="updateDanceField(index, 'title', $event.target.value)"
                >
              </div>
              <input
                :value="element.data"
                type="text"
                placeholder="Song/artist information"
                class="w-full p-2 border dark:bg-slate-700 dark:text-gray-50 rounded"
                @input="updateDanceField(index, 'data', $event.target.value)"
              >
            </div>
          </template>
        </draggable>
        <button
          type="button"
          class="mt-2 px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 flex items-center gap-2"
          @click="addNewDance"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
              clip-rule="evenodd"
            />
          </svg>
          Add Dance
        </button>
      </div>
      <div>
        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Onsite</label>
        <input
          :checked="modelValue.additional_data.wedding.onsite"
          type="checkbox"
          class="form-checkbox h-5 w-5 text-blue-600"
          @change="updateOnsite($event.target.checked)"
        >
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const drag = ref(false);

// Computed property for two-way binding without direct mutation
const dances = computed({
    get() {
        return props.modelValue.additional_data.wedding.dances || [];
    },
    set(value) {
        emit('update:modelValue', {
            ...props.modelValue,
            additional_data: {
                ...props.modelValue.additional_data,
                wedding: {
                    ...props.modelValue.additional_data.wedding,
                    dances: value
                }
            }
        });
    }
});

const dragOptions = computed(() => {
    return {
        animation: 200,
        disabled: false,
        ghostClass: 'ghost'
    };
});

const onDragStart = () => {
    drag.value = true;
    // Add class to prevent scrolling without disrupting focus
    document.body.classList.add('dragging-active');
};

const onDragEnd = () => {
    drag.value = false;
    // Remove the dragging class
    document.body.classList.remove('dragging-active');
};

const updateDanceField = (index, field, value) => {
    const updatedDances = [...dances.value];
    updatedDances[index] = {
        ...updatedDances[index],
        [field]: value
    };
    dances.value = updatedDances;
};

const addNewDance = () => {
    const newDances = [...(dances.value || [])];
    newDances.push({
        id: Date.now() + Math.random(), // Unique stable ID
        title: "New Dance",
        data: "TBD"
    });
    dances.value = newDances;
};

const removeDance = (index) => {
    if (confirm("Are you sure you want to remove this dance?")) {
        const updatedDances = [...dances.value];
        updatedDances.splice(index, 1);
        dances.value = updatedDances;
    }
};

const updateOnsite = (value) => {
    emit('update:modelValue', {
        ...props.modelValue,
        additional_data: {
            ...props.modelValue.additional_data,
            wedding: {
                ...props.modelValue.additional_data.wedding,
                onsite: value
            }
        }
    });
};
</script>

<style>
body.dragging-active {
  overflow: hidden !important;
  touch-action: none !important;
}

body.dragging-active html {
  overflow: hidden !important;
}
</style>

<style scoped>
.flip-list-move {
  transition: transform 0.5s;
}

.ghost {
  opacity: 0.5;
  background: #c8ebfb;
}
</style>
