<template>
  <Container>
    <h2>Contract Administration for {{ booking.name }}</h2>
    <div class="mb-4">
      <label class="block mb-2">Contract Option:</label>
      <select
        v-model="selectedOption"
        class="w-full p-2 border rounded"
      >
        <option value="none">
          None
        </option>
        <option value="default">
          Default
        </option>
        <option value="external">
          External
        </option>
      </select>
    </div>

    <div
      v-if="selectedOption === 'none'"
      class="mb-4"
    >
      <p>No contract will be generated or uploaded for this booking.</p>
    </div>

    <div
      v-if="selectedOption === 'default'"
      class="mb-4"
    >
      <p>A contract will be automatically generated for this booking.</p>
      <button
        class="bg-blue-500 text-white px-4 py-2 rounded"
        @click="generateContract"
      >
        Generate Contract
      </button>
    </div>

    <div
      v-if="selectedOption === 'external'"
      class="mb-4"
    >
      <p>Upload an external contract for this booking.</p>
      <input
        type="file"
        class="mb-2"
        accept=".pdf"
        @change="handleFileUpload"
      >
      <button
        class="bg-green-500 text-white px-4 py-2 rounded"
        @click="uploadContract"
      >
        Upload Contract
      </button>
    </div>

    <div
      v-if="booking.contract"
      class="mt-4"
    >
      <h3>Current Contract:</h3>
      <p>Status: {{ booking.contract.status }}</p>
      <VuePdfEmbed
        :source="route('contracts.show', booking.contract.id)"
      />
    </div>
  </Container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import VuePdfEmbed from 'vue-pdf-embed'

// optional styles
import 'vue-pdf-embed/dist/styles/annotationLayer.css'
import 'vue-pdf-embed/dist/styles/textLayer.css'


const props = defineProps({
  booking: Object,
  band: Object,
});

const selectedOption = ref(props.booking.contract_option || 'none');
const contractFile = ref(null);
const showContract = ref(false);

const isContractViewable = computed(() => {
  if (!props.booking.contract || !props.booking.contract.asset_url) return false;
  const fileExtension = props.booking.contract.asset_url.split('.').pop().toLowerCase();
  return ['pdf'].includes(fileExtension);
});

onMounted(() => {
  // You might want to fetch the latest booking data here
});

const generateContract = () => {
  // Implement contract generation logic here
  console.log('Generating contract...');
};

const handleFileUpload = (event) => {
  contractFile.value = event.target.files[0];
};

const uploadContract = () => {
  if (contractFile.value) {
    // Implement contract upload logic here
    console.log('Uploading contract:', contractFile.value.name);
  } else {
    console.error('No file selected');
  }
};

const toggleContractView = () => {
  showContract.value = !showContract.value;
};

const handlePassword = (callback, reason) => {
  // Implement password handling if needed
  console.log('Password required:', reason);
};

const handleError = (err) => {
  console.error('Error loading PDF:', err);
};
</script>