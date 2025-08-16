<template>
  <div class="font-sans max-w-2xl mx-auto p-5 bg-gray-100 rounded-lg shadow-md">
    <div
      v-if="loading"
      class="text-center py-5 bg-white rounded border-t-4 border-blue-500 mt-5"
    >
      <div
        class="animate-spin inline-block w-6 h-6 border-[3px] border-current border-t-transparent text-blue-600 rounded-full"
        role="status"
        aria-label="loading"
      >
        <span class="sr-only">Loading...</span>
      </div>
      <p class="mt-2 text-gray-600">
        Loading contract history...
      </p>
    </div>
    
    <div
      v-else-if="error"
      class="text-center py-5 bg-white rounded border-t-4 border-red-500 mt-5"
    >
      <p class="text-red-600">
        Error: {{ error }}
      </p>
    </div>
    
    <div v-else>
      <ul
        v-if="history.length"
        class="list-none p-0"
      >
        <li
          v-for="item in history"
          :key="item.id"
          class="bg-white rounded mb-4 p-4 shadow-sm border-l-4 border-gray-300"
        >
          <div class="flex justify-between items-center mb-3">
            <span class="text-sm text-gray-600 font-medium">{{ formatDate(item.created_at) }}</span>
            <span :class="['font-bold py-1 px-3 rounded-full text-xs uppercase tracking-wide', getActionClass(item.action_code)]">
              {{ item.action }}
            </span>
          </div>
          
          <div class="mb-2">
            <strong class="text-gray-800 text-sm">{{ item.user_email }}</strong>
          </div>
          
          <div class="mb-2 text-gray-700 text-sm leading-relaxed">
            {{ item.description }}
          </div>
          
          <div
            v-if="item.reason"
            class="italic text-gray-600 mb-2 text-xs"
          >
            Reason: {{ item.reason }}
          </div>
          
          <div class="text-xs text-gray-500 flex justify-between items-center">
            <span :class="['py-1 px-2 rounded-full font-medium capitalize', getStatusClass(item.status)]">
              {{ item.status }}
            </span>
            <span
              v-if="item.ip_address"
              class="font-mono bg-gray-100 py-1 px-2 rounded"
            >
              IP: {{ item.ip_address }}
            </span>
          </div>
        </li>
      </ul>
      
      <div
        v-else
        class="text-center py-5 bg-white rounded border-t-4 border-gray-400 mt-5"
      >
        <p class="text-gray-600">
          No history available.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import axios from 'axios';
import { ref, onMounted } from 'vue';

const props = defineProps({
  contract: {
    type: Object,
    required: true,
  },
});

const loading = ref(true);
const history = ref([]);
const error = ref(null);

const fetchContractHistory = async () => {
  try {
    const response = await axios.get(route('getContractHistory', { contract: props.contract.envelope_id }), {
      headers: {
        'Content-Type': 'application/json',
      },
    });
    
    // Handle the transformed audit trail response
    history.value = response.data.history.results || response.data.history || [];
  } catch (err) {
    console.error(err);
    error.value = 'Failed to load contract history';
  } finally {
    loading.value = false;
  }
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleString();
};

const getActionClass = (actionCode) => {
  const classes = {
    1: 'bg-blue-100 text-blue-800',      // Document Created
    2: 'bg-purple-100 text-purple-800',  // Document Updated
    6: 'bg-green-100 text-green-800',    // Document Sent
    7: 'bg-orange-100 text-orange-800',  // Document Signed
    8: 'bg-purple-100 text-purple-800',  // Document Viewed
    9: 'bg-cyan-100 text-cyan-800',      // Document Downloaded
    10: 'bg-indigo-100 text-indigo-800', // Document Completed
    11: 'bg-red-100 text-red-800',       // Document Declined
    12: 'bg-gray-100 text-gray-800',     // Document Voided
    15: 'bg-green-100 text-green-800',   // Payment Completed
    18: 'bg-yellow-100 text-yellow-800', // Comment Added
    20: 'bg-green-100 text-green-800',   // Approval Granted
  };
  return classes[actionCode] || 'bg-gray-100 text-gray-600';
};

const getStatusClass = (status) => {
  const classes = {
    'completed': 'bg-green-100 text-green-800',
    'failed': 'bg-red-100 text-red-800',
    'pending': 'bg-yellow-100 text-yellow-800',
    'info': 'bg-blue-100 text-blue-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-600';
};

onMounted(fetchContractHistory);
</script>