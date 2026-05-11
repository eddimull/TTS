<template>
  <Container class="p-4">
    <form
      class="space-y-6"
      @submit.prevent="saveBooking"
    >
      <!-- Engagement section -->
      <div>
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-50 mb-4">
          Engagement
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <TextInput
            v-model="form.name"
            name="name"
            label="Name"
          />
          <div>
            <label
              for="event_type_id"
              class="block text-sm font-medium text-gray-700 dark:text-gray-50"
            >Event Type</label>
            <select
              id="event_type_id"
              v-model="form.event_type_id"
              class="mt-1 block w-full rounded-md border-gray-300 dark:bg-slate-700 dark:text-gray-50 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
            >
              <option
                v-for="eventType in eventTypes"
                :key="eventType.id"
                :value="eventType.id"
              >
                {{ eventType.name }}
              </option>
            </select>
          </div>
          <div>
            <label
              for="price"
              class="block text-sm font-medium text-gray-700 dark:text-gray-50"
            >Total Price</label>
            <input
              id="price"
              v-model="form.price"
              type="number"
              step="0.01"
              class="mt-1 block w-full rounded-md border-gray-300 dark:bg-slate-700 dark:text-gray-50 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
            >
          </div>
          <div>
            <label
              for="status"
              class="block text-sm font-medium text-gray-700 dark:text-gray-50"
            >Status</label>
            <select
              id="status"
              v-model="form.status"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-slate-700 dark:text-gray-50 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
            >
              <option value="draft">Draft</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div>
            <label
              for="contract_option"
              class="block text-sm font-medium text-gray-700 dark:text-gray-50"
            >Contract Option</label>
            <select
              id="contract_option"
              v-model="form.contract_option"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-slate-700 dark:text-gray-50 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
            >
              <option value="default">Default (Automatic)</option>
              <option value="none">None</option>
              <option value="external">External</option>
            </select>
          </div>
        </div>
        <div class="mt-4">
          <label
            for="notes"
            class="block text-sm font-medium text-gray-700 dark:text-gray-50"
          >Notes</label>
          <textarea
            id="notes"
            v-model="form.notes"
            rows="3"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-slate-700 dark:text-gray-50 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
          />
        </div>
      </div>

      <!-- Events section -->
      <div>
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-50 mb-4">
          Events
        </h3>

        <p
          v-if="topLevelError"
          class="mb-3 text-sm text-red-600 dark:text-red-400"
        >
          {{ topLevelError }}
        </p>

        <div class="space-y-4">
          <EventSubForm
            v-for="row in eventRows"
            :key="row._key"
            v-model="eventRows[eventRows.indexOf(row)]"
            :can-delete="eventRows.length > 1"
            :save-error="rowErrors[row._key] ?? null"
            @delete="removeRow(row._key)"
          />
        </div>

        <Button
          type="button"
          label="Add event"
          icon="pi pi-plus"
          severity="secondary"
          outlined
          class="mt-4"
          @click="addRow"
        />
      </div>

      <!-- Form actions -->
      <div class="flex justify-between space-x-4">
        <Button
          type="button"
          label="Back to View"
          icon="pi pi-arrow-left"
          severity="secondary"
          outlined
          @click="backToView"
        />
        <div class="flex space-x-4">
          <Button
            v-if="props.booking.status !== 'confirmed'"
            label="Delete Booking"
            severity="danger"
            @click="deleteBooking"
          />
          <Button
            v-if="props.booking.status === 'confirmed'"
            type="button"
            label="Cancel Booking"
            severity="danger"
            @click="cancelBooking"
          />
          <Button
            label="Save Booking"
            type="submit"
            severity="success"
            :loading="isSaving"
          />
        </div>
      </div>
    </form>
  </Container>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useStore } from 'vuex';
import Container from '@/Components/Container.vue';
import Button from 'primevue/button';
import TextInput from '@/Components/TextInput.vue';
import EventSubForm from './EventSubForm.vue';

const props = defineProps({
  booking: {
    type: Object,
    required: true,
  },
  band: {
    type: Object,
    required: true,
  },
});

const store = useStore();

const eventTypes = computed(() => store.getters['eventTypes/getAllEventTypes']);

// ── Booking-level form ────────────────────────────────────────────────────────
const form = useForm({
  name:            props.booking.name,
  event_type_id:   props.booking.event_type_id,
  price:           props.booking.price,
  status:          props.booking.status,
  contract_option: props.booking.contract_option,
  notes:           props.booking.notes,
});

// ── Event rows ────────────────────────────────────────────────────────────────
let _keyCounter = 0;

function normalizeEvent(evt) {
  return {
    _key:           `existing-${evt.id}`,
    id:             evt.id,
    title:          evt.title   ?? '',
    date:           evt.date    ?? '',
    start_time:     evt.start_time  ?? '',
    end_time:       evt.end_time    ?? '',
    venue_name:     evt.venue_name  ?? '',
    venue_address:  evt.venue_address ?? '',
    additional_data: parseAdditionalData(evt.additional_data),
    roster_id:      evt.roster_id ?? null,
    notes:          evt.notes   ?? null,
  };
}

// additional_data sometimes comes back as a JSON-encoded string from
// older event rows that bypassed the model cast. Normalize to an object
// so the form request's `array` validation passes on re-save.
function parseAdditionalData(raw) {
  if (!raw) return {};
  if (typeof raw === 'string') {
    try {
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (_) {
      return {};
    }
  }
  return raw;
}

const eventRows = ref(
  (props.booking.events ?? []).map(normalizeEvent),
);

// Track which event ids existed at load time so we can issue DELETEs for removed ones.
const originalEventIds = new Set((props.booking.events ?? []).map((e) => e.id));

// ── UI state ──────────────────────────────────────────────────────────────────
const isSaving     = ref(false);
const topLevelError = ref(null);
const rowErrors    = ref({});   // keyed by row._key

// ── Row management ────────────────────────────────────────────────────────────
function addRow() {
  const prev = eventRows.value[eventRows.value.length - 1];
  _keyCounter += 1;
  eventRows.value.push({
    _key:           `new-${_keyCounter}`,
    id:             null,
    title:          `${form.name} Event`,
    date:           prev?.date ?? '',
    start_time:     '',
    end_time:       '',
    venue_name:     '',
    venue_address:  '',
    additional_data: {},
    roster_id:      null,
    notes:          null,
  });
}

function removeRow(key) {
  eventRows.value = eventRows.value.filter((r) => r._key !== key);
}

// ── Helpers ───────────────────────────────────────────────────────────────────

// Wraps router.put/post/delete (callback-style) in a Promise so we can await it.
function inertiaPromise(method, url, data) {
  return new Promise((resolve, reject) => {
    router[method](url, data, {
      preserveScroll: true,
      preserveState:  true,
      onSuccess: () => resolve(),
      onError:   (errors) => reject(errors),
    });
  });
}

function errorMessage(errors) {
  if (!errors) return null;
  if (typeof errors === 'string') return errors;
  const first = Object.values(errors)[0];
  return Array.isArray(first) ? first[0] : first;
}

function eventPayload(row) {
  return {
    title:          row.title,
    date:           row.date,
    start_time:     row.start_time  || null,
    end_time:       row.end_time    || null,
    venue_name:     row.venue_name  || null,
    venue_address:  row.venue_address || null,
    additional_data: row.additional_data ?? {},
    roster_id:      row.roster_id ?? null,
    notes:          row.notes     ?? null,
    silent:         true,
  };
}

// ── Save orchestration ────────────────────────────────────────────────────────
async function saveBooking() {
  isSaving.value     = true;
  topLevelError.value = null;
  rowErrors.value    = {};

  // Phase 1 — PATCH booking (engagement fields only)
  try {
    await new Promise((resolve, reject) => {
      form
        .transform((data) => ({
          name:            data.name,
          event_type_id:   data.event_type_id,
          price:           data.price,
          status:          data.status,
          contract_option: data.contract_option,
          notes:           data.notes,
        }))
        .put(route('bands.booking.update', [props.band, props.booking]), {
          preserveScroll: true,
          preserveState:  true,
          onSuccess: () => resolve(),
          onError:   (errors) => reject(errors),
        });
    });
  } catch (errors) {
    topLevelError.value = errorMessage(errors);
    isSaving.value = false;
    return;
  }

  // Phase 2 — PUT existing events
  for (const row of eventRows.value) {
    if (!row.id) continue;
    try {
      await inertiaPromise(
        'put',
        route('Update Booking Event', [props.band.id, props.booking.id, row.id]),
        eventPayload(row),
      );
    } catch (errors) {
      rowErrors.value = { ...rowErrors.value, [row._key]: errorMessage(errors) };
      isSaving.value = false;
      return;
    }
  }

  // Phase 3 — POST new events (no id)
  for (const row of eventRows.value) {
    if (row.id) continue;
    try {
      await inertiaPromise(
        'post',
        route('Update Booking Event', [props.band.id, props.booking.id]),
        eventPayload(row),
      );
    } catch (errors) {
      rowErrors.value = { ...rowErrors.value, [row._key]: errorMessage(errors) };
      isSaving.value = false;
      return;
    }
  }

  // Phase 4 — DELETE removed events (were in original set, no longer in eventRows)
  const currentIds = new Set(eventRows.value.filter((r) => r.id).map((r) => r.id));
  for (const id of originalEventIds) {
    if (currentIds.has(id)) continue;
    try {
      await inertiaPromise(
        'delete',
        route('Delete Booking Event', [props.band.id, props.booking.id, id]),
        {},
      );
    } catch (errors) {
      topLevelError.value = errorMessage(errors);
      isSaving.value = false;
      return;
    }
  }

  // All phases succeeded — navigate to detail view
  router.visit(route('Booking Details', [props.band.id, props.booking.id]));
}

// ── Action buttons ────────────────────────────────────────────────────────────
function backToView() {
  router.visit(route('Booking Details', [props.band.id, props.booking.id]));
}

function deleteBooking() {
  if (confirm('Are you sure you want to delete this booking? This action cannot be undone.')) {
    form.delete(route('bands.booking.destroy', [props.band, props.booking]), {
      preserveScroll: true,
      preserveState:  false,
    });
  }
}

function cancelBooking() {
  if (confirm('Are you sure you want to cancel this booking?')) {
    form.post(route('Cancel Booking', [props.band, props.booking]), {
      preserveScroll: true,
      preserveState:  true,
    });
  }
}
</script>
