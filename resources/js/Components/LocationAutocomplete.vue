<template>
  <div class="createEventInput">
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
      >

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
  import { ref, onMounted } from 'vue';
  import axios from 'axios';

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

  onMounted(() => {
    generateSessionToken();
  });

  const generateSessionToken = () => {
    sessionToken.value = Math.floor(Math.random() * 1000000000);
  };

  const autoComplete = () => {
    if (searchTimer.value) {
      clearTimeout(searchTimer.value);
      searchTimer.value = null;
    }

    // Only search if input is 3+ characters
    if (!props.modelValue || props.modelValue.length < 3) {
      searchResults.value = null;
      return;
    }

    searchTimer.value = setTimeout(() => {
      try {
        axios.post('/api/searchLocations', {
          sessionToken: sessionToken.value,
          input: props.modelValue,
        }).then((response) => {
          searchResults.value = response.data.predictions;
        }).catch((error) => {
          console.error('Error in autocomplete', error);
          searchResults.value = null;
        });
      } catch (e) {
        console.error('Error in autocomplete', e);
        searchResults.value = null;
      }
    }, 1200); // Increased from 800ms to 1200ms to reduce API calls
  };

  const getLocationDetails = (place_id) => {
    axios.post('/api/getLocationDetails', {
      sessionToken: sessionToken.value,
      place_id
    }).then((response) => {
      // Handle the response here
      emit('update:modelValue', response.data.result.name); // Adjust according to your API response
      searchResults.value = null;
      // You might want to emit an additional event here to notify the parent component
      // of the full selected location details
      emit('location-selected', response.data);
    }).catch((error) => {
      console.error('Error getting location details', error);
    });

    // After using the session token, generate a new one for the next use
    generateSessionToken();
  };
  </script>
