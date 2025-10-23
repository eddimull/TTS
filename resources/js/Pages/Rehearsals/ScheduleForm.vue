<template>
  <BreezeAuthenticatedLayout>
    <Container class="dark:bg-slate-600 md:container md:mx-auto">
      <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
          <div class="componentPanel overflow-auto shadow-sm sm:rounded-lg">
            <div class="p-6">
              <h2 class="text-2xl font-bold mb-6">
                {{ schedule ? 'Edit Rehearsal Schedule' : 'Create Rehearsal Schedule' }}
              </h2>

              <form @submit.prevent="submit">
                <!-- Name -->
                <div class="mb-4">
                  <Label
                    for="name"
                    value="Schedule Name *"
                  />
                  <Input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    placeholder="e.g., Weekly Practice, Pre-Tour Rehearsals"
                  />
                  <InputError
                    :message="form.errors.name"
                    class="mt-2"
                  />
                </div>

                <!-- Description -->
                <div class="mb-4">
                  <Label
                    for="description"
                    value="Description"
                  />
                  <TextArea
                    id="description"
                    v-model="form.description"
                    class="mt-1 block w-full"
                    rows="3"
                    placeholder="Optional description of this rehearsal schedule"
                  />
                  <InputError
                    :message="form.errors.description"
                    class="mt-2"
                  />
                </div>

                <!-- Frequency -->
                <div class="mb-4">
                  <Label
                    for="frequency"
                    value="Repeats *"
                  />
                  <select
                    id="frequency"
                    v-model="form.frequency"
                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                    required
                  >
                    <option value="">
                      Does not repeat
                    </option>
                    <option value="daily">
                      Daily
                    </option>
                    <option value="weekly">
                      Weekly
                    </option>
                    <option value="monthly">
                      Monthly
                    </option>
                    <option value="weekday">
                      Every weekday (Monday to Friday)
                    </option>
                    <option value="custom">
                      Custom...
                    </option>
                  </select>
                  <InputError
                    :message="form.errors.frequency"
                    class="mt-2"
                  />
                </div>

                <!-- Weekly: Day Selection -->
                <div
                  v-if="form.frequency === 'weekly'"
                  class="mb-4"
                >
                  <Label
                    value="Repeat on"
                  />
                  <div class="flex gap-2 mt-2">
                    <button
                      v-for="day in weekdays"
                      :key="day.value"
                      type="button"
                      :class="[
                        'w-10 h-10 rounded-full font-medium text-sm transition-colors',
                        isWeekdaySelected(day.value)
                          ? 'bg-blue-500 text-white'
                          : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                      ]"
                      @click="toggleWeekday(day.value)"
                    >
                      {{ day.short }}
                    </button>
                  </div>
                  <InputError
                    :message="form.errors.selected_days"
                    class="mt-2"
                  />
                </div>

                <!-- Monthly: Pattern Selection -->
                <div
                  v-if="form.frequency === 'monthly'"
                  class="mb-4"
                >
                  <Label
                    for="monthly_pattern"
                    value="Monthly on *"
                  />
                  <select
                    id="monthly_pattern"
                    v-model="form.monthly_pattern"
                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                  >
                    <option value="">
                      Select pattern...
                    </option>
                    <option value="day_of_month">
                      Day of month
                    </option>
                    <option value="first">
                      First
                    </option>
                    <option value="second">
                      Second
                    </option>
                    <option value="third">
                      Third
                    </option>
                    <option value="fourth">
                      Fourth
                    </option>
                    <option value="last">
                      Last
                    </option>
                  </select>
                  <InputError
                    :message="form.errors.monthly_pattern"
                    class="mt-2"
                  />
                </div>

                <!-- Monthly: Day of Month Number -->
                <div
                  v-if="form.frequency === 'monthly' && form.monthly_pattern === 'day_of_month'"
                  class="mb-4"
                >
                  <Label
                    for="day_of_month"
                    value="Day *"
                  />
                  <select
                    id="day_of_month"
                    v-model="form.day_of_month"
                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                  >
                    <option value="">
                      Select day...
                    </option>
                    <option
                      v-for="day in 31"
                      :key="day"
                      :value="day"
                    >
                      {{ day }}{{ getOrdinalSuffix(day) }}
                    </option>
                  </select>
                  <InputError
                    :message="form.errors.day_of_month"
                    class="mt-2"
                  />
                </div>

                <!-- Monthly: Weekday Selection -->
                <div
                  v-if="form.frequency === 'monthly' && form.monthly_pattern && form.monthly_pattern !== 'day_of_month'"
                  class="mb-4"
                >
                  <Label
                    for="monthly_weekday"
                    value="Weekday *"
                  />
                  <select
                    id="monthly_weekday"
                    v-model="form.monthly_weekday"
                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                  >
                    <option value="">
                      Select weekday...
                    </option>
                    <option value="monday">
                      Monday
                    </option>
                    <option value="tuesday">
                      Tuesday
                    </option>
                    <option value="wednesday">
                      Wednesday
                    </option>
                    <option value="thursday">
                      Thursday
                    </option>
                    <option value="friday">
                      Friday
                    </option>
                    <option value="saturday">
                      Saturday
                    </option>
                    <option value="sunday">
                      Sunday
                    </option>
                  </select>
                  <p
                    v-if="form.monthly_pattern && form.monthly_weekday"
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                  >
                    e.g., {{ formatMonthlyDescription() }}
                  </p>
                  <InputError
                    :message="form.errors.monthly_weekday"
                    class="mt-2"
                  />
                </div>

                <!-- Default Time -->
                <div class="mb-4">
                  <Label
                    for="default_time"
                    value="Default Time"
                  />
                  <Input
                    id="default_time"
                    v-model="form.default_time"
                    type="time"
                    class="mt-1 block w-full"
                    placeholder="19:00"
                  />
                  <InputError
                    :message="form.errors.default_time"
                    class="mt-2"
                  />
                </div>

                <!-- Default Location Name -->
                <div class="mb-4">
                  <Label
                    for="location_name"
                    value="Default Location Name"
                  />
                  <Input
                    id="location_name"
                    v-model="form.location_name"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="e.g., Studio A, Band Practice Space"
                  />
                  <InputError
                    :message="form.errors.location_name"
                    class="mt-2"
                  />
                </div>

                <!-- Default Location Address -->
                <div class="mb-4">
                  <Label
                    for="location_address"
                    value="Default Location Address"
                  />
                  <TextArea
                    id="location_address"
                    v-model="form.location_address"
                    class="mt-1 block w-full"
                    rows="2"
                    placeholder="Full address for default rehearsal location"
                  />
                  <InputError
                    :message="form.errors.location_address"
                    class="mt-2"
                  />
                </div>

                <!-- Notes -->
                <div class="mb-4">
                  <Label
                    for="notes"
                    value="Notes"
                  />
                  <TextArea
                    id="notes"
                    v-model="form.notes"
                    class="mt-1 block w-full"
                    rows="4"
                    placeholder="Additional notes about this rehearsal schedule"
                  />
                  <InputError
                    :message="form.errors.notes"
                    class="mt-2"
                  />
                </div>

                <!-- Active Status -->
                <div class="mb-6">
                  <label class="flex items-center">
                    <Checkbox
                      v-model:checked="form.active"
                      name="active"
                    />
                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                      Active Schedule
                    </span>
                  </label>
                  <InputError
                    :message="form.errors.active"
                    class="mt-2"
                  />
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-between">
                  <Link
                    :href="schedule 
                      ? route('rehearsal-schedules.show', { band: band.id, rehearsal_schedule: schedule.id })
                      : route('rehearsal-schedules.index', { band: band.id })"
                    class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                  >
                    Cancel
                  </Link>

                  <div class="flex gap-2">
                    <Button
                      v-if="schedule"
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
                      {{ schedule ? 'Update Schedule' : 'Create Schedule' }}
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
import { useForm, Link } from '@inertiajs/vue3';
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
        default: null,
    },
});

const form = useForm({
    name: props.schedule?.name || '',
    description: props.schedule?.description || '',
    frequency: props.schedule?.frequency || '',
    day_of_week: props.schedule?.day_of_week || '',
    selected_days: props.schedule?.selected_days || [],
    day_of_month: props.schedule?.day_of_month || '',
    monthly_pattern: props.schedule?.monthly_pattern || '',
    monthly_weekday: props.schedule?.monthly_weekday || '',
    default_time: props.schedule?.default_time || '',
    location_name: props.schedule?.location_name || '',
    location_address: props.schedule?.location_address || '',
    notes: props.schedule?.notes || '',
    active: props.schedule?.active ?? true,
});

const weekdays = [
    { value: 'sunday', short: 'S' },
    { value: 'monday', short: 'M' },
    { value: 'tuesday', short: 'T' },
    { value: 'wednesday', short: 'W' },
    { value: 'thursday', short: 'T' },
    { value: 'friday', short: 'F' },
    { value: 'saturday', short: 'S' },
];

const toggleWeekday = (day) => {
    if (!form.selected_days) {
        form.selected_days = [];
    }
    
    const index = form.selected_days.indexOf(day);
    if (index > -1) {
        form.selected_days.splice(index, 1);
    } else {
        form.selected_days.push(day);
    }
};

const isWeekdaySelected = (day) => {
    return form.selected_days && form.selected_days.includes(day);
};

const getOrdinalSuffix = (day) => {
    if (day > 3 && day < 21) return 'th';
    switch (day % 10) {
        case 1: return 'st';
        case 2: return 'nd';
        case 3: return 'rd';
        default: return 'th';
    }
};

const formatMonthlyDescription = () => {
    if (!form.monthly_pattern || !form.monthly_weekday) return '';
    
    const weekday = form.monthly_weekday.charAt(0).toUpperCase() + form.monthly_weekday.slice(1);
    const pattern = form.monthly_pattern.charAt(0).toUpperCase() + form.monthly_pattern.slice(1);
    
    return `${pattern} ${weekday} of every month`;
};

const submit = () => {
    if (props.schedule) {
        form.put(route('rehearsal-schedules.update', {
            band: props.band.id,
            rehearsal_schedule: props.schedule.id
        }));
    } else {
        form.post(route('rehearsal-schedules.store', {
            band: props.band.id
        }));
    }
};

const confirmDelete = () => {
    if (confirm('Are you sure you want to delete this rehearsal schedule? This will also delete all associated rehearsals.')) {
        form.delete(route('rehearsal-schedules.destroy', {
            band: props.band.id,
            rehearsal_schedule: props.schedule.id
        }));
    }
};
</script>
