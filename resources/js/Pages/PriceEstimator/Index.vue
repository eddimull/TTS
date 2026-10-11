<template>
  <Container class="md:container md:mx-auto">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-50">
            Price Estimator
          </h1>
          <p class="text-sm text-gray-600 dark:text-gray-300">
            {{ band.name }} — suggested pricing from your booking history and pricing rules.
          </p>
        </div>
        <Link
          :href="`/bands/${band.id}/edit/pricing`"
          class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-300"
        >
          <i class="pi pi-sliders-h" /> Pricing rules
        </Link>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <!-- Inputs -->
        <form
          class="lg:col-span-2 componentPanel shadow-sm rounded-lg p-5 space-y-4"
          @submit.prevent="submit"
        >
          <div>
            <label
              for="event_type_id"
              class="block text-sm font-medium text-gray-700 dark:text-gray-200"
            >Event type</label>
            <select
              id="event_type_id"
              v-model="form.event_type_id"
              class="mt-1 block w-full rounded-md border-gray-300 dark:bg-slate-700 dark:text-gray-50 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
            >
              <option :value="null">
                Select an event type
              </option>
              <option
                v-for="type in eventTypes"
                :key="type.id"
                :value="type.id"
              >
                {{ type.name }}
              </option>
            </select>
            <InputError :message="fieldError('event_type_id')" />
          </div>

          <div>
            <label
              for="date"
              class="block text-sm font-medium text-gray-700 dark:text-gray-200"
            >Date</label>
            <input
              id="date"
              v-model="form.date"
              type="date"
              class="mt-1 block w-full rounded-md border-gray-300 dark:bg-slate-700 dark:text-gray-50 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
            >
            <InputError :message="fieldError('date')" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label
                for="start_time"
                class="block text-sm font-medium text-gray-700 dark:text-gray-200"
              >Start time</label>
              <input
                id="start_time"
                v-model="form.start_time"
                type="time"
                class="mt-1 block w-full rounded-md border-gray-300 dark:bg-slate-700 dark:text-gray-50 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
              >
            </div>
            <div>
              <label
                for="end_time"
                class="block text-sm font-medium text-gray-700 dark:text-gray-200"
              >End time</label>
              <input
                id="end_time"
                v-model="form.end_time"
                type="time"
                class="mt-1 block w-full rounded-md border-gray-300 dark:bg-slate-700 dark:text-gray-50 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
              >
            </div>
          </div>

          <div>
            <label
              for="duration_hours"
              class="block text-sm font-medium text-gray-700 dark:text-gray-200"
            >Or duration (hours)</label>
            <input
              id="duration_hours"
              v-model="form.duration_hours"
              type="number"
              step="0.25"
              min="0.25"
              max="24"
              :placeholder="`Default ${standardHours}`"
              class="mt-1 block w-full rounded-md border-gray-300 dark:bg-slate-700 dark:text-gray-50 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
            >
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Start and end times take priority. An end time before the start means the gig runs past midnight.
            </p>
            <InputError :message="fieldError('duration_hours')" />
          </div>

          <div>
            <LocationAutocomplete
              v-model="venueName"
              name="venue"
              label="Venue"
              placeholder="Search for a venue or address"
              @location-selected="onVenueSelected"
            />
            <input
              v-model="form.venue_address"
              type="text"
              placeholder="Venue address"
              aria-label="Venue address"
              class="mt-2 block w-full rounded-md border-gray-300 dark:bg-slate-700 dark:text-gray-50 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
            >
            <p
              v-if="!band.has_address"
              class="mt-1 text-xs text-yellow-700 dark:text-yellow-300"
            >
              Your band has no address on file, so travel distance can't be calculated automatically.
            </p>
          </div>

          <div>
            <label
              for="distance_miles"
              class="block text-sm font-medium text-gray-700 dark:text-gray-200"
            >Distance override (one-way miles)</label>
            <input
              id="distance_miles"
              v-model="form.distance_miles"
              type="number"
              step="0.1"
              min="0"
              placeholder="Calculated from the venue address"
              class="mt-1 block w-full rounded-md border-gray-300 dark:bg-slate-700 dark:text-gray-50 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
            >
            <InputError :message="fieldError('distance_miles')" />
          </div>

          <fieldset v-if="addOns.length">
            <legend class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
              Add-ons
            </legend>
            <label
              v-for="addOn in addOns"
              :key="addOn.key"
              class="flex items-center gap-2 py-0.5 text-sm text-gray-700 dark:text-gray-200"
            >
              <input
                v-model="form.add_ons"
                type="checkbox"
                :value="addOn.key"
                class="rounded border-gray-300 text-indigo-600"
              >
              <span class="flex-1">{{ addOn.label }}</span>
              <span class="tabular-nums text-gray-500 dark:text-gray-400">{{ formatMoney(addOn.amount) }}</span>
            </label>
          </fieldset>

          <button
            type="submit"
            :disabled="loading"
            class="w-full flex justify-center items-center gap-2 py-2 px-4 rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 disabled:opacity-50"
          >
            <i
              v-if="loading"
              class="pi pi-spin pi-spinner"
            />
            Estimate price
          </button>
        </form>

        <!-- Result -->
        <div class="lg:col-span-3 componentPanel shadow-sm rounded-lg p-5">
          <p
            v-if="error"
            class="text-sm text-red-600 dark:text-red-400"
          >
            {{ error }}
          </p>
          <EstimateResult
            v-else-if="result"
            :result="result"
            :band-id="band.id"
          />
          <div
            v-else
            class="h-full flex flex-col items-center justify-center text-center text-gray-500 dark:text-gray-400 py-12"
          >
            <i class="pi pi-calculator text-4xl mb-3" />
            <p>Pick an event type and date, then press <strong>Estimate price</strong>.</p>
          </div>
        </div>
      </div>
    </div>
  </Container>
</template>

<script setup>
import { reactive, ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Container from '@/Components/Container.vue';
import InputError from '@/Components/InputError.vue';
import LocationAutocomplete from '@/Components/LocationAutocomplete.vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import EstimateResult from '@/Components/PriceEstimate/EstimateResult.vue';
import { usePriceEstimate, formatMoney } from '@/Components/PriceEstimate/usePriceEstimate';

defineOptions({
    layout: BreezeAuthenticatedLayout,
});

const props = defineProps({
    band: {
        type: Object,
        required: true,
    },
    addOns: {
        type: Array,
        default: () => [],
    },
    standardHours: {
        type: Number,
        default: 4,
    },
});

const page = usePage();
const eventTypes = computed(() => page.props.eventTypes ?? []);

const form = reactive({
    event_type_id: null,
    date: '',
    start_time: '',
    end_time: '',
    duration_hours: '',
    venue_address: '',
    distance_miles: '',
    add_ons: [],
});
const venueName = ref('');

const { result, loading, error, fieldErrors, fetchEstimate } = usePriceEstimate(props.band.id);

function fieldError(field) {
    return fieldErrors.value[field]?.[0] ?? null;
}

function onVenueSelected(location) {
    form.venue_address = location?.result?.formatted_address ?? form.venue_address;
}

function blankToNull(value) {
    return value === '' || value === undefined ? null : value;
}

function submit() {
    fetchEstimate({
        event_type_id: form.event_type_id,
        date: blankToNull(form.date),
        start_time: blankToNull(form.start_time),
        end_time: blankToNull(form.end_time),
        duration_hours: form.duration_hours === '' ? null : Number(form.duration_hours),
        venue_address: blankToNull(form.venue_address),
        distance_miles: form.distance_miles === '' ? null : Number(form.distance_miles),
        add_ons: form.add_ons,
    });
}
</script>
