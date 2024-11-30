<template>
    <div class="contract-history">
      <div v-if="loading" class="loading">Loading...</div>
      <div v-else-if="error" class="error">Error: {{ error }}</div>
      <div v-else>
        <ul v-if="history.length" class="history-list">
          <li v-for="item in history" :key="item.id" class="history-item">
            <div class="history-header">
              <span class="date">{{ formatDate(item.date_created) }}</span>
              <span :class="['action', getActionClass(item.action)]">{{ getActionDescription(item.action) }}</span>
            </div>
            <div class="user-info">
              <strong>{{ item.user.first_name }} {{ item.user.last_name }}</strong> ({{ item.user.email }})
            </div>
            <div v-if="item.reason" class="reason">
              Reason: {{ item.reason }}
            </div>
            <div class="additional-info">
              <span class="location">{{ item.data.city }}, {{ item.data.country }}</span>
              <span class="ip-address">IP: {{ item.ip_address }}</span>
            </div>
          </li>
        </ul>
        <p v-else class="no-history">No history available.</p>
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
      history.value = response.data.history;
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
  
  const getActionDescription = (action) => {
    const actions = {
      1: 'Created',
      6: 'Sent',
      7: 'Signed',
      8: 'Viewed',
      9: 'Completed',
    };
    return actions[action] || `Action ${action}`;
  };
  
  const getActionClass = (action) => {
    const classes = {
      1: 'created',
      6: 'sent',
      7: 'signed',
      8: 'viewed',
      9: 'completed',
    };
    return classes[action] || 'default';
  };
  
  onMounted(fetchContractHistory);
  </script>
  
  <style scoped>
  .contract-history {
    font-family: Arial, sans-serif;
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
    background-color: #f5f5f5;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }
  
  .title {
    color: #333;
    border-bottom: 2px solid #ddd;
    padding-bottom: 10px;
  }
  
  .loading, .error, .no-history {
    text-align: center;
    padding: 20px;
    background-color: #fff;
    border-radius: 4px;
    margin-top: 20px;
  }
  
  .error {
    color: #d32f2f;
  }
  
  .history-list {
    list-style-type: none;
    padding: 0;
  }
  
  .history-item {
    background-color: #fff;
    border-radius: 4px;
    margin-bottom: 15px;
    padding: 15px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }
  
  .history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
  }
  
  .date {
    font-size: 0.9em;
    color: #666;
  }
  
  .action {
    font-weight: bold;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.8em;
  }
  
  .action.created { background-color: #e3f2fd; color: #1565c0; }
  .action.sent { background-color: #e8f5e9; color: #2e7d32; }
  .action.signed { background-color: #fff3e0; color: #ef6c00; }
  .action.viewed { background-color: #f3e5f5; color: #7b1fa2; }
  .action.completed { background-color: #e8eaf6; color: #3f51b5; }
  .action.default { background-color: #eeeeee; color: #616161; }
  
  .user-info {
    margin-bottom: 5px;
  }
  
  .reason {
    font-style: italic;
    color: #666;
    margin-bottom: 5px;
  }
  
  .additional-info {
    font-size: 0.8em;
    color: #888;
    display: flex;
    justify-content: space-between;
  }
  </style>