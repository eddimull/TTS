<template>
  <Container class="dark:bg-slate-600 md:container md:mx-auto">
      <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
          <div class="componentPanel overflow-auto shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="mb-6">
                <h2 class="text-2xl font-bold mb-2">
                  {{ lodging ? 'Edit Lodging' : 'Create Lodging' }}
                </h2>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                  {{ band.name }}
                </div>
              </div>

              <form @submit.prevent="submit">
                <!-- Name -->
                <div class="mb-4">
                  <Label
                    for="name"
                    value="Name *"
                  />
                  <Input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                  />
                  <InputError
                    :message="form.errors.name"
                    class="mt-2"
                  />
                </div>

                <!-- Address -->
                <div class="mb-4">
                  <LocationAutocomplete
                    v-model="form.address"
                    name="address"
                    label="Address"
                    placeholder="Search for a hotel or address"
                    @location-selected="onLocationSelected"
                  />
                  <InputError
                    :message="form.errors.address"
                    class="mt-2"
                  />
                </div>

                <!-- Check-in / Check-out -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                  <div>
                    <Label value="Check-in" />
                    <div class="grid grid-cols-2 gap-2">
                      <Input
                        v-model="checkInDate"
                        type="date"
                        class="mt-1 block w-full"
                      />
                      <Input
                        v-model="checkInTime"
                        type="time"
                        class="mt-1 block w-full"
                      />
                    </div>
                    <InputError
                      :message="form.errors.check_in_at"
                      class="mt-2"
                    />
                  </div>

                  <div>
                    <Label value="Check-out" />
                    <div class="grid grid-cols-2 gap-2">
                      <Input
                        v-model="checkOutDate"
                        type="date"
                        class="mt-1 block w-full"
                      />
                      <Input
                        v-model="checkOutTime"
                        type="time"
                        class="mt-1 block w-full"
                      />
                    </div>
                    <InputError
                      :message="form.errors.check_out_at"
                      class="mt-2"
                    />
                  </div>
                </div>

                <!-- Notes -->
                <div class="mb-4">
                  <TextArea
                    v-model="form.notes"
                    name="notes"
                    label="Notes"
                  />
                  <InputError
                    :message="form.errors.notes"
                    class="mt-2"
                  />
                </div>

                <!-- Booking / Event pickers -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                  <div>
                    <LinkPicker
                      v-model="form.booking_id"
                      :options="bookings"
                      :check-in="composedCheckIn"
                      :check-out="composedCheckOut"
                      label="Linked Booking"
                    />
                    <InputError
                      :message="form.errors.booking_id"
                      class="mt-2"
                    />
                  </div>

                  <div>
                    <LinkPicker
                      v-model="form.event_id"
                      :options="events"
                      :check-in="composedCheckIn"
                      :check-out="composedCheckOut"
                      label="Linked Event"
                    />
                    <InputError
                      :message="form.errors.event_id"
                      class="mt-2"
                    />
                  </div>
                </div>

                <!-- Rooms -->
                <div class="mb-6 p-4 bg-gray-50 dark:bg-slate-700 rounded-lg">
                  <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">
                      Rooms
                    </h3>
                    <Button
                      type="button"
                      variant="secondary"
                      size="sm"
                      data-testid="add-room"
                      @click="addRoom"
                    >
                      Add room
                    </Button>
                  </div>

                  <div
                    v-if="form.rooms.length === 0"
                    class="text-sm text-gray-500 dark:text-gray-400"
                  >
                    No rooms added yet.
                  </div>

                  <div
                    v-for="(room, index) in form.rooms"
                    :key="room.id ?? `new-${index}`"
                    data-testid="room-row"
                    class="grid grid-cols-1 md:grid-cols-[1fr_1fr_1fr_auto] gap-2 mb-3 items-start"
                  >
                    <Input
                      v-model="room.label"
                      type="text"
                      placeholder="Room label (e.g. King 204)"
                      class="w-full"
                    />
                    <Input
                      v-model="room.confirmation_number"
                      type="text"
                      placeholder="Confirmation #"
                      class="w-full"
                    />
                    <Input
                      v-model="room.notes"
                      type="text"
                      placeholder="Notes"
                      class="w-full"
                    />
                    <button
                      type="button"
                      class="text-red-600 hover:text-red-800 dark:text-red-400 px-2 py-2"
                      aria-label="Remove room"
                      @click="removeRoom(index)"
                    >
                      &times;
                    </button>
                  </div>
                </div>

                <!-- Attachments -->
                <div
                  v-if="lodging"
                  class="mb-6 p-4 bg-gray-50 dark:bg-slate-700 rounded-lg"
                >
                  <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">
                      Attachments
                    </h3>
                    <div>
                      <input
                        ref="fileInput"
                        type="file"
                        multiple
                        class="hidden"
                        @change="handleFileSelect"
                      >
                      <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="triggerFilePicker"
                      >
                        Add files
                      </Button>
                    </div>
                  </div>

                  <div
                    v-if="attachments.length === 0"
                    class="text-sm text-gray-500 dark:text-gray-400"
                  >
                    No attachments yet.
                  </div>

                  <div
                    v-else
                    class="space-y-2"
                  >
                    <div
                      v-for="attachment in attachments"
                      :key="attachment.id"
                      class="flex items-center justify-between p-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-gray-600 rounded"
                    >
                      <a
                        :href="attachment.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 truncate"
                      >
                        {{ attachment.filename }}
                      </a>
                      <button
                        type="button"
                        class="text-red-600 hover:text-red-800 dark:text-red-400 text-sm ml-2"
                        @click="deleteAttachment(attachment.id)"
                      >
                        Delete
                      </button>
                    </div>
                  </div>
                </div>
                <div
                  v-else
                  class="mb-6 p-4 bg-gray-50 dark:bg-slate-700 rounded-lg text-sm text-gray-500 dark:text-gray-400"
                >
                  Save first to add images.
                </div>

                <!-- Actions -->
                <div class="flex justify-between items-center">
                  <div class="flex gap-2">
                    <Button
                      type="submit"
                      variant="info"
                      :disabled="form.processing"
                    >
                      {{ lodging ? 'Save Changes' : 'Create Lodging' }}
                    </Button>
                    <Link
                      :href="cancelHref"
                      class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-sm inline-flex items-center"
                    >
                      Cancel
                    </Link>
                  </div>
                  <Button
                    v-if="lodging"
                    type="button"
                    variant="danger"
                    @click="confirmDelete"
                  >
                    Delete
                  </Button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </Container>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import axios from 'axios';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Container from '@/Components/Container.vue';
import Button from '@/Components/Button.vue';
import Input from '@/Components/Input.vue';
import InputError from '@/Components/InputError.vue';
import Label from '@/Components/Label.vue';
import TextArea from '@/Components/TextArea.vue';
import LocationAutocomplete from '@/Components/LocationAutocomplete.vue';
import LinkPicker from '@/Components/Lodging/LinkPicker.vue';

defineOptions({
    layout: BreezeAuthenticatedLayout,
});

const props = defineProps({
    band: { type: Object, required: true },
    lodging: { type: Object, default: null },
    bookings: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
});

const splitDateTime = (value) => {
    if (!value) return ['', ''];
    const [datePart, timePart] = value.split(' ');
    return [datePart ?? '', timePart ? timePart.slice(0, 5) : ''];
};

const [initialCheckInDate, initialCheckInTime] = splitDateTime(props.lodging?.check_in_at);
const [initialCheckOutDate, initialCheckOutTime] = splitDateTime(props.lodging?.check_out_at);

const checkInDate = ref(initialCheckInDate);
const checkInTime = ref(initialCheckInTime);
const checkOutDate = ref(initialCheckOutDate);
const checkOutTime = ref(initialCheckOutTime);

const attachments = ref(props.lodging?.attachments ? [...props.lodging.attachments] : []);

const cancelHref = computed(() => props.lodging ? route('lodgings.show', props.lodging.id) : route('lodgings.index'));

const form = useForm({
    name:         props.lodging?.name || '',
    address:      props.lodging?.address || '',
    latitude:     props.lodging?.latitude ?? null,
    longitude:    props.lodging?.longitude ?? null,
    check_in_at:  props.lodging?.check_in_at || '',
    check_out_at: props.lodging?.check_out_at || '',
    notes:        props.lodging?.notes || '',
    booking_id:   props.lodging?.booking?.id ?? null,
    event_id:     props.lodging?.event?.id ?? null,
    rooms:        props.lodging?.rooms?.map(r => ({ ...r })) || [],
});

const composeDateTime = (d, t) => (d && t) ? `${d} ${t}:00` : '';

const composedCheckIn = computed(() => composeDateTime(checkInDate.value, checkInTime.value) || null);
const composedCheckOut = computed(() => composeDateTime(checkOutDate.value, checkOutTime.value) || null);

const onLocationSelected = (payload) => {
    const r = payload.result ?? payload;
    form.address   = r.formatted_address ?? form.address;
    form.latitude  = r.geometry?.location?.lat ?? null;
    form.longitude = r.geometry?.location?.lng ?? null;
};

const addRoom = () => form.rooms.push({ label: '', confirmation_number: '', notes: '' });
const removeRoom = (i) => form.rooms.splice(i, 1);

const submit = () => {
    form.check_in_at = composeDateTime(checkInDate.value, checkInTime.value);
    form.check_out_at = composeDateTime(checkOutDate.value, checkOutTime.value);

    if (props.lodging) {
        form.patch(route('lodgings.update', props.lodging.id));
    } else {
        form.post(route('bands.lodgings.store', { band: props.band.id }));
    }
};

const confirmDelete = () => {
    if (confirm('Are you sure you want to delete this lodging?')) {
        form.delete(route('lodgings.destroy', props.lodging.id));
    }
};

// ── Attachments (edit mode only) ────────────────────────────────────────
const fileInput = ref(null);

const triggerFilePicker = () => {
    fileInput.value?.click();
};

const handleFileSelect = async (event) => {
    const files = Array.from(event.target.files);
    event.target.value = '';
    if (!props.lodging || files.length === 0) return;

    const formData = new FormData();
    files.forEach(file => formData.append('files[]', file));

    try {
        const url = route('lodgings.attachments.upload', props.lodging.id);
        const response = await axios.post(url, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        attachments.value = response.data.attachments;
    } catch (error) {
        console.error('Failed to upload attachments:', error);
        alert('Failed to upload files. Please try again.');
    }
};

const deleteAttachment = async (attachmentId) => {
    if (!confirm('Are you sure you want to delete this attachment?')) return;

    try {
        await axios.delete(route('lodgings.attachments.destroy', attachmentId));
        attachments.value = attachments.value.filter(a => a.id !== attachmentId);
    } catch (error) {
        console.error('Failed to delete attachment:', error);
        alert('Failed to delete attachment. Please try again.');
    }
};
</script>
