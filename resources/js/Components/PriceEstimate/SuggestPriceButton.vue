<template>
  <span class="inline-block">
    <button
      type="button"
      data-test="suggest-price-button"
      class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-300 dark:hover:text-indigo-200 disabled:opacity-50"
      :disabled="loading"
      @click="open"
    >
      <i :class="loading ? 'pi pi-spin pi-spinner' : 'pi pi-calculator'" />
      Suggest price
    </button>

    <Popover
      ref="popover"
      class="w-[22rem] max-w-[calc(100vw-2rem)]"
    >
      <div class="space-y-3">
        <p
          v-if="missingMessage"
          class="text-sm text-gray-700 dark:text-gray-200"
        >
          {{ missingMessage }}
        </p>
        <div
          v-else-if="loading && !result"
          class="text-sm text-gray-500 dark:text-gray-400"
        >
          <i class="pi pi-spin pi-spinner mr-1" /> Estimating…
        </div>
        <p
          v-else-if="error"
          class="text-sm text-red-600 dark:text-red-400"
        >
          {{ error }}
        </p>
        <template v-else-if="result">
          <EstimateResult
            :result="result"
            :band-id="bandId"
            compact
          />
          <div class="flex items-center justify-between gap-2 pt-1">
            <a
              :href="`/bands/${bandId}/price-estimator`"
              class="text-xs text-gray-500 dark:text-gray-400 hover:underline"
            >Open estimator</a>
            <button
              v-if="result.estimate !== null"
              type="button"
              data-test="use-suggested-price"
              class="py-1.5 px-3 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600"
              @click="usePrice"
            >
              Use this price
            </button>
          </div>
        </template>
      </div>
    </Popover>
  </span>
</template>

<script setup>
import { ref, computed } from 'vue';
import Popover from 'primevue/popover';
import EstimateResult from './EstimateResult.vue';
import { usePriceEstimate } from './usePriceEstimate';

const props = defineProps({
    bandId: {
        type: [Number, String],
        required: true,
    },
    // Request body for POST /bands/{band}/price-estimate built from the
    // surrounding form's current values.
    payload: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['use-price']);

const popover = ref(null);
const { result, loading, error, fetchEstimate } = usePriceEstimate(props.bandId);

const missingMessage = computed(() => {
    if (!props.payload.event_type_id && !props.payload.date) return 'Pick an event type and date first.';
    if (!props.payload.event_type_id) return 'Pick an event type first.';
    if (!props.payload.date) return 'Pick a date first.';
    return null;
});

function open(event) {
    popover.value.show(event);
    if (!missingMessage.value) {
        result.value = null;
        fetchEstimate(props.payload);
    }
}

function usePrice() {
    emit('use-price', result.value.estimate);
    popover.value.hide();
}
</script>
