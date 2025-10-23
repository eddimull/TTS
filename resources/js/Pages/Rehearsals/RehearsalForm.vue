<template>
  <BreezeAuthenticatedLayout>
    <Container class="dark:bg-slate-600 md:container md:mx-auto">
      <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
          <div class="componentPanel overflow-auto shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="mb-6">
                <h2 class="text-2xl font-bold mb-2">
                  {{ rehearsal ? 'Edit Rehearsal' : 'Create Rehearsal' }}
                </h2>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                  Schedule: {{ schedule.name }}
                </div>
                <Link
                  :href="route('rehearsal-schedules.show', { band: band.id, rehearsal_schedule: schedule.id })"
                  class="text-blue-500 hover:text-blue-700 text-sm"
                >
                  ← Back to Schedule
                </Link>
              </div>

              <form @submit.prevent="submit">
                <!-- Event Information Section -->
                <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900 rounded-lg">
                  <h3 class="text-lg font-semibold mb-4">
                    Event Information
                  </h3>

                  <!-- Event Title -->
                  <div class="mb-4">
                    <Label
                      for="event_title"
                      value="Rehearsal Title *"
                    />
                    <Input
                      id="event_title"
                      v-model="form.event_title"
                      type="text"
                      class="mt-1 block w-full"
                      required
                      placeholder="e.g., Weekly Band Practice, Pre-Wedding Rehearsal"
                    />
                    <InputError
                      :message="form.errors.event_title"
                      class="mt-2"
                    />
                  </div>

                  <!-- Event Type -->
                  <div class="mb-4">
                    <Label
                      for="event_type_id"
                      value="Event Type *"
                    />
                    <select
                      id="event_type_id"
                      v-model="form.event_type_id"
                      class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                      required
                    >
                      <option value="">
                        Select event type...
                      </option>
                      <option
                        v-for="eventType in eventTypes"
                        :key="eventType.id"
                        :value="eventType.id"
                      >
                        {{ eventType.name }}
                      </option>
                    </select>
                    <InputError
                      :message="form.errors.event_type_id"
                      class="mt-2"
                    />
                  </div>

                  <!-- Date and Time -->
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                      <Label
                        for="event_date"
                        value="Date *"
                      />
                      <Input
                        id="event_date"
                        v-model="form.event_date"
                        type="date"
                        class="mt-1 block w-full"
                        required
                      />
                      <InputError
                        :message="form.errors.event_date"
                        class="mt-2"
                      />
                    </div>

                    <div>
                      <Label
                        for="event_time"
                        value="Time *"
                      />
                      <Input
                        id="event_time"
                        v-model="form.event_time"
                        type="time"
                        class="mt-1 block w-full"
                        required
                      />
                      <InputError
                        :message="form.errors.event_time"
                        class="mt-2"
                      />
                    </div>
                  </div>

                  <!-- Event Notes -->
                  <div class="mb-4">
                    <Label
                      for="event_notes"
                      value="Event Notes"
                    />
                    <TextArea
                      id="event_notes"
                      v-model="form.event_notes"
                      class="mt-1 block w-full"
                      rows="3"
                      placeholder="Notes that will appear in the event calendar"
                    />
                    <InputError
                      :message="form.errors.event_notes"
                      class="mt-2"
                    />
                  </div>
                </div>

                <!-- Venue Information Section -->
                <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                  <h3 class="text-lg font-semibold mb-4">
                    Venue Information
                  </h3>
                  <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Leave blank to use schedule defaults: {{ schedule.location_name || 'No default location' }}
                  </p>

                  <!-- Venue Name -->
                  <div class="mb-4">
                    <Label
                      for="venue_name"
                      value="Venue Name"
                    />
                    <Input
                      id="venue_name"
                      v-model="form.venue_name"
                      type="text"
                      class="mt-1 block w-full"
                      :placeholder="schedule.location_name || 'Custom venue name for this rehearsal'"
                    />
                    <InputError
                      :message="form.errors.venue_name"
                      class="mt-2"
                    />
                  </div>

                  <!-- Venue Address -->
                  <div class="mb-4">
                    <Label
                      for="venue_address"
                      value="Venue Address"
                    />
                    <TextArea
                      id="venue_address"
                      v-model="form.venue_address"
                      class="mt-1 block w-full"
                      rows="2"
                      :placeholder="schedule.location_address || 'Custom venue address for this rehearsal'"
                    />
                    <InputError
                      :message="form.errors.venue_address"
                      class="mt-2"
                    />
                  </div>
                </div>

                <!-- Rehearsal Notes -->
                <div class="mb-4">
                  <Label
                    for="notes"
                    value="Rehearsal Notes"
                  />
                  <TextArea
                    id="notes"
                    v-model="form.notes"
                    class="mt-1 block w-full"
                    rows="4"
                    placeholder="Additional notes specific to this rehearsal (setlist, focus areas, etc.)"
                  />
                  <InputError
                    :message="form.errors.notes"
                    class="mt-2"
                  />
                </div>

                <!-- Associated Bookings Section -->
                <div class="mb-6 p-4 bg-green-50 dark:bg-green-900 rounded-lg">
                  <h3 class="text-lg font-semibold mb-4">
                    Associated Bookings
                  </h3>
                  <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Link this rehearsal to specific bookings you're preparing for
                  </p>

                  <div
                    v-if="availableBookings.length === 0"
                    class="text-gray-500 dark:text-gray-400 italic"
                  >
                    No upcoming bookings available to associate
                  </div>

                  <div
                    v-else
                    class="space-y-2"
                  >
                    <label
                      v-for="booking in availableBookings"
                      :key="booking.id"
                      class="flex items-start p-3 bg-white dark:bg-gray-800 rounded cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                      <input
                        v-model="form.associated_bookings"
                        type="checkbox"
                        :value="booking.id"
                        class="mt-1 mr-3 form-checkbox h-5 w-5 text-blue-600"
                      >
                      <div class="flex-1">
                        <div class="font-semibold">
                          {{ booking.name }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                          {{ formatBookingDate(booking.date) }} - {{ booking.venue_name }}
                        </div>
                      </div>
                    </label>
                  </div>

                  <InputError
                    :message="form.errors.associated_bookings"
                    class="mt-2"
                  />
                </div>

                <!-- Cancelled Status -->
                <div class="mb-6">
                  <label class="flex items-center">
                    <Checkbox
                      v-model:checked="form.is_cancelled"
                      name="is_cancelled"
                    />
                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                      Mark this rehearsal as cancelled
                    </span>
                  </label>
                  <InputError
                    :message="form.errors.is_cancelled"
                    class="mt-2"
                  />
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-between">
                  <Link
                    :href="route('rehearsal-schedules.show', { band: band.id, rehearsal_schedule: schedule.id })"
                    class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                  >
                    Cancel
                  </Link>

                  <div class="flex gap-2">
                    <Button
                      v-if="rehearsal"
                      type="button"
                      class="bg-red-500 hover:bg-red-700"
                      :class="{ 'opacity-25': form.processing }"
                      :disabled="form.processing"
                      @click="confirmDelete"
                    >
                      Delete
                    </Button>
                    <Button
                      type="submit"
                      :class="{ 'opacity-25': form.processing }"
                      :disabled="form.processing"
                    >
                      {{ rehearsal ? 'Update Rehearsal' : 'Create Rehearsal' }}
                    </Button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </Container>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import { useForm, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { DateTime } from 'luxon';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Container from '@/Components/Container.vue';
import Button from '@/Components/Button.vue';
import Input from '@/Components/Input.vue';
import InputError from '@/Components/InputError.vue';
import Label from '@/Components/Label.vue';
import TextArea from '@/Components/TextArea.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    band: {
        type: Object,
        required: true,
    },
    schedule: {
        type: Object,
        required: true,
    },
    rehearsal: {
        type: Object,
        default: null,
    },
    eventTypes: {
        type: Array,
        default: () => [],
    },
});

// Get bookings from page props if available
const page = usePage();
const availableBookings = computed(() => {
    // You may need to pass bookings from the controller
    // For now, returning empty array - controller needs to be updated
    return page.props.bookings || [];
});

// Initialize form with rehearsal data or defaults
const getEventData = () => {
    if (props.rehearsal?.events?.[0]) {
        const event = props.rehearsal.events[0];
        return {
            event_title: event.title || '',
            event_type_id: event.event_type_id || '',
            event_date: event.date || '',
            event_time: event.time?.substring(0, 5) || '', // HH:mm format
            event_notes: event.notes || '',
        };
    }
    return {
        event_title: '',
        event_type_id: '',
        event_date: '',
        event_time: '',
        event_notes: '',
    };
};

const getAssociatedBookings = () => {
    if (props.rehearsal?.associations) {
        return props.rehearsal.associations
            .filter(a => a.associable_type === 'App\\Models\\Bookings')
            .map(a => a.associable_id);
    }
    return [];
};

const form = useForm({
    venue_name: props.rehearsal?.venue_name || '',
    venue_address: props.rehearsal?.venue_address || '',
    notes: props.rehearsal?.notes || '',
    additional_data: props.rehearsal?.additional_data || {},
    is_cancelled: props.rehearsal?.is_cancelled || false,
    ...getEventData(),
    associated_bookings: getAssociatedBookings(),
});

const submit = () => {
    if (props.rehearsal) {
        form.put(route('rehearsals.update', {
            band: props.band.id,
            rehearsal_schedule: props.schedule.id,
            rehearsal: props.rehearsal.id,
        }));
    } else {
        form.post(route('rehearsals.store', {
            band: props.band.id,
            rehearsal_schedule: props.schedule.id,
        }));
    }
};

const confirmDelete = () => {
    if (confirm('Are you sure you want to delete this rehearsal? This will also remove it from the event calendar.')) {
        form.delete(route('rehearsals.destroy', {
            band: props.band.id,
            rehearsal_schedule: props.schedule.id,
            rehearsal: props.rehearsal.id,
        }));
    }
};

const formatBookingDate = (date) => {
    if (!date) return 'N/A';
    return DateTime.fromISO(date).toLocaleString(DateTime.DATE_FULL);
};
</script>
