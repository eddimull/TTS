<template>
  <div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <h4 class="font-semibold mb-4 text-gray-700 dark:text-gray-300">
          Dances
        </h4>
        <div
          v-for="(dance, index) in modelValue.additional_data.wedding.dances"
          :key="index"
          class="mb-4 p-3 border rounded-lg group relative"
        >
          <div class="flex items-center justify-between mb-2">
            <label class="block font-medium">{{ dance.title }}</label>
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
              v-model="dance.title"
              type="text"
              placeholder="Dance title"
              class="w-full p-2 border dark:bg-slate-700 dark:text-gray-50 rounded"
            >
          </div>
          <input
            v-model="dance.data"
            type="text"
            placeholder="Song/artist information"
            class="w-full p-2 border dark:bg-slate-700 dark:text-gray-50 rounded"
          >
        </div>
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
          v-model="modelValue.additional_data.wedding.onsite"
          type="checkbox"
          class="form-checkbox h-5 w-5 text-blue-600"
        >
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
});

const addNewDance = () => {
    if (!props.modelValue.additional_data.wedding.dances) {
        props.modelValue.additional_data.wedding.dances = [];
    }
    
    props.modelValue.additional_data.wedding.dances.push({
        title: "New Dance",
        data: "TBD"
    });
};

const removeDance = (index) => {
    if (confirm("Are you sure you want to remove this dance?")) {
        props.modelValue.additional_data.wedding.dances.splice(index, 1);
    }
};
</script>
