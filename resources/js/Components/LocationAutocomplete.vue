<template>
  <div
    ref="autocompleteContainer"
    class="createEventInput"
  >
    <p class="text-gray-600 dark:text-gray-50">
      <label :for="props.name">{{ label }}</label>
    </p>
    <div>
      <input
        :id="props.name"
        :value="modelValue"
        :type="props.type"
        :placeholder="props.placeholder"
        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:bg-slate-700 dark:text-gray-50 leading-tight focus:outline-none focus:shadow-outline"
        @input="$emit('update:modelValue', $event.target.value)"
        @keyup="autoComplete"
        @keydown.esc="clearResults"
      >

      <!-- Loading indicator -->
      <div
        v-if="isLoading"
        class="my-4 p-4 bg-gray-100 dark:bg-slate-700 rounded flex items-center justify-center"
      >
        <svg
          class="animate-spin h-5 w-5 text-indigo-600 dark:text-indigo-400 mr-2"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
          />
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          />
        </svg>
        <span class="text-sm text-gray-600 dark:text-gray-300">Searching...</span>
      </div>

      <!-- Search results -->
      <ul class="">
        <li
          v-for="(result, index) in searchResults"
          :key="index"
          class="border-black my-4 p-4 bg-gray-200 hover:bg-gray-300 hover:dark:bg-gray-700 dark:bg-slate-700 dark:text-gray-50  cursor-pointer"
          @click="getLocationDetails(result.place_id)"
        >
          {{ result.description }}
        </li>
      </ul>
    </div>
  </div>
</template>

  <script setup>
  import { ref, onMounted, onBeforeUnmount } from 'vue';
  import axios from 'axios';

  const autocompleteContainer = ref(null);

  const props = defineProps({
    modelValue: {
      type: String,
      required: true
    },
    name: {
      type: String,
      required: true
    },
    label: {
      type: String,
      required: false,
      default: ''
    },
    placeholder: {
      type: String,
      required: false,
      default: ''
    },
    type: {
      type: String,
      required: false,
      default: 'text'
    }
  });

  const emit = defineEmits(['update:modelValue','location-selected']);

  const searchResults = ref(null);
  const searchTimer = ref(null);
  const sessionToken = ref(0);
  const isLoading = ref(false);

  const handleClickOutside = (event) => {
    if (autocompleteContainer.value && !autocompleteContainer.value.contains(event.target)) {
      searchResults.value = null;
      isLoading.value = false;
    }
  };

  onMounted(() => {
    generateSessionToken();
    document.addEventListener('click', handleClickOutside);
  });

  onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
  });

  const generateSessionToken = () => {
    sessionToken.value = Math.floor(Math.random() * 1000000000);
  };

  const clearResults = () => {
    searchResults.value = null;
    isLoading.value = false;
  };

  const autoComplete = () => {
    if (searchTimer.value) {
      clearTimeout(searchTimer.value);
      searchTimer.value = null;
    }

    // Only search if input is 3+ characters
    if (!props.modelValue || props.modelValue.length < 3) {
      searchResults.value = null;
      isLoading.value = false;
      return;
    }

    isLoading.value = true;
    searchTimer.value = setTimeout(() => {
      try {
        axios.post('/api/searchLocations', {
          sessionToken: sessionToken.value,
          input: props.modelValue,
        }).then((response) => {
          searchResults.value = response.data.predictions;
          isLoading.value = false;
        }).catch((error) => {
          console.error('Error in autocomplete', error);
          searchResults.value = null;
          isLoading.value = false;
        });
      } catch (e) {
        console.error('Error in autocomplete', e);
        searchResults.value = null;
        isLoading.value = false;
      }
    }, 1200); // Increased from 800ms to 1200ms to reduce API calls
  };

  const getLocationDetails = (place_id) => {
    isLoading.value = true;
    searchResults.value = null; // Clear results to show loading indicator

    axios.post('/api/getLocationDetails', {
      sessionToken: sessionToken.value,
      place_id
    }).then((response) => {
      // Handle the response here
      emit('update:modelValue', response.data.result.name); // Adjust according to your API response
      // You might want to emit an additional event here to notify the parent component
      // of the full selected location details
      emit('location-selected', response.data);
      isLoading.value = false;
    }).catch((error) => {
      console.error('Error getting location details', error);
      isLoading.value = false;
    });

    // After using the session token, generate a new one for the next use
    generateSessionToken();
  };
  </script>
