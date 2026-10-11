<template>
  <div>
    <div
      v-if="loading"
      class="py-8 text-center text-gray-500 dark:text-gray-400"
    >
      <i class="pi pi-spin pi-spinner mr-1" /> Loading pricing rules…
    </div>
    <p
      v-else-if="loadError"
      class="text-sm text-red-600 dark:text-red-400"
    >
      {{ loadError }}
    </p>

    <form
      v-else-if="settings"
      class="space-y-8"
      @submit.prevent="save"
    >
      <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
        <p class="text-sm text-blue-800 dark:text-blue-200">
          The <a
            :href="`/bands/${band.id}/price-estimator`"
            class="underline font-medium"
          >price estimator</a> learns a baseline for each event type from your past bookings, then applies the
          adjustments below. Set a manual base rate to override what it learned.
        </p>
      </div>

      <!-- Base rates -->
      <section>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
          Base rates
        </h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-xs text-gray-500 dark:text-gray-400">
                <th class="py-2 pr-3 font-medium">
                  Event type
                </th>
                <th class="py-2 pr-3 font-medium">
                  Learned baseline
                </th>
                <th class="py-2 pr-3 font-medium">
                  Manual base rate ($)
                </th>
                <th class="py-2 font-medium">
                  Standard hours
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="type in eventTypes"
                :key="type.id"
                class="border-t border-gray-100 dark:border-gray-700"
              >
                <td class="py-2 pr-3 text-gray-900 dark:text-gray-50">
                  {{ type.name }}
                </td>
                <td class="py-2 pr-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                  <template v-if="historyFor(type.id)">
                    {{ formatMoney(historyFor(type.id).baseline) }}
                    <span class="text-xs text-gray-400">({{ historyFor(type.id).sample_size }} bookings)</span>
                  </template>
                  <span
                    v-else
                    class="text-xs text-gray-400"
                  >No history</span>
                </td>
                <td class="py-2 pr-3">
                  <input
                    v-model="baseRates[type.id]"
                    type="number"
                    min="0"
                    step="1"
                    placeholder="Use learned"
                    :class="inputClass"
                  >
                </td>
                <td class="py-2">
                  <input
                    v-model="standardHoursByType[type.id]"
                    type="number"
                    min="0.5"
                    max="24"
                    step="0.5"
                    :placeholder="`${settings.standard_hours}`"
                    :class="inputClass"
                  >
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Season -->
      <section>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
          Season multipliers
        </h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
          1.0 = normal, 1.1 = 10% more, 0.9 = 10% less.
        </p>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
          <label
            v-for="(month, index) in months"
            :key="month"
            class="block text-xs text-gray-600 dark:text-gray-300"
          >
            {{ month }}
            <input
              v-model="settings.season_multipliers[String(index + 1)]"
              type="number"
              step="0.05"
              min="0"
              :class="inputClass"
            >
          </label>
        </div>
      </section>

      <!-- Day of week -->
      <section>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
          Day of week multipliers
        </h3>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-7 gap-3">
          <label
            v-for="(day, index) in weekdays"
            :key="day"
            class="block text-xs text-gray-600 dark:text-gray-300"
          >
            {{ day }}
            <input
              v-model="settings.day_of_week_multipliers[String(index)]"
              type="number"
              step="0.05"
              min="0"
              :class="inputClass"
            >
          </label>
        </div>
      </section>

      <!-- Special dates -->
      <section>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
          Special dates
        </h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
          Use MM-DD for a date every year (12-31) or YYYY-MM-DD for a one-off.
        </p>
        <div
          v-for="(special, index) in settings.special_dates"
          :key="`special-${index}`"
          class="grid grid-cols-12 gap-2 mb-2 items-center"
        >
          <input
            v-model="special.date"
            type="text"
            placeholder="12-31"
            :class="[inputClass, 'col-span-3']"
          >
          <input
            v-model="special.label"
            type="text"
            placeholder="Label"
            :class="[inputClass, 'col-span-5']"
          >
          <input
            v-model="special.multiplier"
            type="number"
            step="0.05"
            min="0"
            :class="[inputClass, 'col-span-3']"
          >
          <button
            type="button"
            class="col-span-1 text-red-600 hover:text-red-800"
            aria-label="Remove special date"
            @click="settings.special_dates.splice(index, 1)"
          >
            <i class="pi pi-trash" />
          </button>
        </div>
        <button
          type="button"
          class="text-sm text-indigo-600 dark:text-indigo-300 hover:underline"
          @click="settings.special_dates.push({ date: '', label: '', multiplier: 1.5 })"
        >
          <i class="pi pi-plus text-xs" /> Add special date
        </button>
      </section>

      <!-- Duration & timing -->
      <section>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
          Duration &amp; timing
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
          <label :class="labelClass">
            Standard hours
            <input
              v-model="settings.standard_hours"
              type="number"
              step="0.5"
              min="0.5"
              max="24"
              :class="inputClass"
            >
          </label>
          <label :class="labelClass">
            Overtime % per extra hour
            <input
              v-model="settings.overtime_pct_per_hour"
              type="number"
              step="1"
              min="0"
              :class="inputClass"
            >
          </label>
          <label :class="labelClass">
            Minimum duration factor
            <input
              v-model="settings.min_duration_factor"
              type="number"
              step="0.05"
              min="0"
              max="1"
              :class="inputClass"
            >
          </label>
          <label :class="labelClass">
            Short notice (days)
            <input
              v-model="settings.short_notice_days"
              type="number"
              step="1"
              min="0"
              :class="inputClass"
            >
          </label>
          <label :class="labelClass">
            Short notice multiplier
            <input
              v-model="settings.short_notice_multiplier"
              type="number"
              step="0.05"
              min="0"
              :class="inputClass"
            >
          </label>
          <label :class="labelClass">
            Annual price increase %
            <input
              v-model="settings.annual_increase_pct"
              type="number"
              step="0.5"
              :class="inputClass"
            >
          </label>
        </div>
      </section>

      <!-- Travel -->
      <section>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
          Travel
        </h3>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
          Distance is measured one-way from your band address; the per-mile rate is charged round trip.
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
          <label :class="labelClass">
            Free radius (miles)
            <input
              v-model="settings.free_radius_miles"
              type="number"
              step="1"
              min="0"
              :class="inputClass"
            >
          </label>
          <label :class="labelClass">
            Per-mile rate ($)
            <input
              v-model="settings.per_mile_rate"
              type="number"
              step="0.05"
              min="0"
              :class="inputClass"
            >
          </label>
          <label :class="labelClass">
            Lodging after (miles)
            <input
              v-model="settings.lodging_threshold_miles"
              type="number"
              step="1"
              min="0"
              :class="inputClass"
            >
          </label>
          <label :class="labelClass">
            Lodging fee ($)
            <input
              v-model="settings.lodging_fee"
              type="number"
              step="1"
              min="0"
              :class="inputClass"
            >
          </label>
        </div>
      </section>

      <!-- Add-ons -->
      <section>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
          Add-ons
        </h3>
        <div
          v-for="(addOn, index) in settings.add_ons"
          :key="`addon-${index}`"
          class="grid grid-cols-12 gap-2 mb-2 items-center"
        >
          <input
            v-model="addOn.label"
            type="text"
            placeholder="Label"
            :class="[inputClass, 'col-span-7']"
          >
          <input
            v-model="addOn.amount"
            type="number"
            step="1"
            min="0"
            placeholder="Amount"
            :class="[inputClass, 'col-span-4']"
          >
          <button
            type="button"
            class="col-span-1 text-red-600 hover:text-red-800"
            aria-label="Remove add-on"
            @click="settings.add_ons.splice(index, 1)"
          >
            <i class="pi pi-trash" />
          </button>
        </div>
        <button
          type="button"
          class="text-sm text-indigo-600 dark:text-indigo-300 hover:underline"
          @click="settings.add_ons.push({ key: '', label: '', amount: 0 })"
        >
          <i class="pi pi-plus text-xs" /> Add add-on
        </button>
      </section>

      <!-- Rounding -->
      <section>
        <label :class="[labelClass, 'max-w-xs']">
          Round estimates to the nearest ($)
          <input
            v-model="settings.round_to"
            type="number"
            step="1"
            min="1"
            :class="inputClass"
          >
        </label>
      </section>

      <div
        v-if="saveErrors.length"
        class="p-3 rounded border text-sm text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800"
      >
        <ul class="list-disc list-inside">
          <li
            v-for="message in saveErrors"
            :key="message"
          >
            {{ message }}
          </li>
        </ul>
      </div>

      <div class="flex items-center justify-end gap-3">
        <span
          v-if="savedMessage"
          class="text-sm text-green-700 dark:text-green-300"
        >{{ savedMessage }}</span>
        <button
          type="submit"
          :disabled="saving"
          class="inline-flex items-center gap-2 py-2 px-4 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50"
        >
          <i
            v-if="saving"
            class="pi pi-spin pi-spinner"
          />
          Save pricing rules
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { formatMoney } from '@/Components/PriceEstimate/usePriceEstimate';

const props = defineProps({
    band: {
        type: Object,
        required: true,
    },
});

const inputClass = 'mt-1 block w-full rounded-md border-gray-300 dark:bg-slate-700 dark:text-gray-50 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm';
const labelClass = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const loading = ref(true);
const loadError = ref(null);
const saving = ref(false);
const saveErrors = ref([]);
const savedMessage = ref('');

const settings = ref(null);
const eventTypes = ref([]);
const history = ref([]);
// Per-type override inputs keyed by event type id; '' means "no override".
const baseRates = ref({});
const standardHoursByType = ref({});

function hydrate(data) {
    settings.value = JSON.parse(JSON.stringify(data.settings));
    eventTypes.value = data.event_types ?? [];
    history.value = data.history ?? [];
    baseRates.value = Object.fromEntries(
        eventTypes.value.map((t) => [t.id, data.settings.base_rates?.[t.id] ?? '']),
    );
    standardHoursByType.value = Object.fromEntries(
        eventTypes.value.map((t) => [t.id, data.settings.standard_hours_by_type?.[t.id] ?? '']),
    );
}

function historyFor(typeId) {
    return history.value.find((h) => h.event_type_id === typeId) ?? null;
}

function nonEmptyMap(map) {
    return Object.fromEntries(
        Object.entries(map)
            .filter(([, value]) => value !== '' && value !== null && value !== undefined)
            .map(([key, value]) => [key, Number(value)]),
    );
}

function slugify(label, index) {
    const slug = String(label ?? '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
    return slug || `addon_${index + 1}`;
}

function buildPayload() {
    const s = settings.value;
    const usedKeys = new Set();
    const addOns = s.add_ons
        .filter((a) => String(a.label ?? '').trim() !== '')
        .map((a, index) => {
            let key = a.key || slugify(a.label, index);
            while (usedKeys.has(key)) key = `${key}_${index + 1}`;
            usedKeys.add(key);
            return { key, label: a.label, amount: Number(a.amount) || 0 };
        });

    return {
        season_multipliers: nonEmptyMap(s.season_multipliers),
        day_of_week_multipliers: nonEmptyMap(s.day_of_week_multipliers),
        special_dates: s.special_dates
            .filter((d) => String(d.date ?? '').trim() !== '')
            .map((d) => ({ date: d.date.trim(), label: d.label ?? '', multiplier: Number(d.multiplier) })),
        standard_hours: Number(s.standard_hours),
        standard_hours_by_type: nonEmptyMap(standardHoursByType.value),
        overtime_pct_per_hour: Number(s.overtime_pct_per_hour),
        min_duration_factor: Number(s.min_duration_factor),
        short_notice_days: parseInt(s.short_notice_days, 10) || 0,
        short_notice_multiplier: Number(s.short_notice_multiplier),
        annual_increase_pct: Number(s.annual_increase_pct),
        free_radius_miles: Number(s.free_radius_miles),
        per_mile_rate: Number(s.per_mile_rate),
        lodging_threshold_miles: Number(s.lodging_threshold_miles),
        lodging_fee: Number(s.lodging_fee),
        add_ons: addOns,
        base_rates: nonEmptyMap(baseRates.value),
        round_to: Number(s.round_to),
    };
}

async function load() {
    loading.value = true;
    loadError.value = null;
    try {
        const response = await axios.get(`/bands/${props.band.id}/pricing-settings`);
        hydrate(response.data);
    } catch (e) {
        loadError.value = e.response?.status === 403
            ? 'Only band owners can change pricing rules.'
            : 'Could not load pricing rules.';
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    saveErrors.value = [];
    savedMessage.value = '';
    try {
        const response = await axios.patch(`/bands/${props.band.id}/pricing-settings`, {
            settings: buildPayload(),
        });
        hydrate(response.data);
        savedMessage.value = 'Pricing rules saved.';
    } catch (e) {
        if (e.response?.status === 422) {
            saveErrors.value = Object.values(e.response.data?.errors ?? {}).flat();
        } else {
            saveErrors.value = ['Could not save pricing rules. Please try again.'];
        }
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
