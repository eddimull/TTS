<template>
  <breeze-authenticated-layout>
    <template #header>
      <div class="flex justify-between items-center">
        <div>
          <Link
            :href="route('questionnaires.index')"
            class="text-sm text-gray-500 dark:text-gray-400 hover:underline"
          >← Back to questionnaires</Link>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-50 leading-tight mt-1">
            {{ questionnaire.name }}
            <span
              v-if="questionnaire.archived_at"
              class="ml-2 text-xs uppercase text-gray-500"
            >Archived</span>
          </h2>
        </div>
        <div class="flex gap-2">
          <Link :href="route('questionnaires.preview', { band: band.id, questionnaire: questionnaire.slug })">
            <Button label="Preview" icon="pi pi-eye" outlined />
          </Link>
          <Link :href="route('questionnaires.edit', { band: band.id, questionnaire: questionnaire.slug })">
            <Button label="Edit" icon="pi pi-pencil" outlined />
          </Link>
          <Button
            label="Send"
            icon="pi pi-send"
            :disabled="bookings.length === 0 || questionnaire.archived_at"
            @click="openSendDialog"
          />
        </div>
      </div>
    </template>

    <Container>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Left column: template summary -->
        <div class="lg:col-span-1">
          <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-50 mb-3 flex items-center">
              <i class="pi pi-info-circle mr-2" />
              About this template
            </h3>
            <dl class="space-y-2 text-sm">
              <div>
                <dt class="text-xs uppercase text-gray-500">Description</dt>
                <dd class="text-gray-700 dark:text-gray-200">
                  {{ questionnaire.description || '(none)' }}
                </dd>
              </div>
              <div>
                <dt class="text-xs uppercase text-gray-500">Fields</dt>
                <dd class="text-gray-700 dark:text-gray-200">{{ fieldCount }}</dd>
              </div>
              <div>
                <dt class="text-xs uppercase text-gray-500">Times sent</dt>
                <dd class="text-gray-700 dark:text-gray-200">{{ instances.length }}</dd>
              </div>
            </dl>
          </div>
        </div>

        <!-- Right column: sent instances -->
        <div class="lg:col-span-2">
          <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4">
            <div class="flex items-center justify-between mb-3">
              <h3 class="text-base font-semibold text-gray-900 dark:text-gray-50 flex items-center">
                <i class="pi pi-send mr-2" />
                Sent
              </h3>
              <span class="text-xs text-gray-500">{{ instances.length }} total</span>
            </div>

            <div
              v-if="instances.length === 0"
              class="text-sm text-gray-500 dark:text-gray-400 py-6 text-center"
            >
              This template hasn't been sent yet.
              <span v-if="bookings.length > 0">
                Click <strong>Send</strong> above to send it to a booking contact.
              </span>
            </div>

            <div v-else class="space-y-2">
              <div
                v-for="instance in instances"
                :key="instance.id"
                class="border border-gray-200 dark:border-slate-600 rounded p-3"
              >
                <div class="flex items-center justify-between gap-3">
                  <div class="min-w-0 flex-1">
                    <Link
                      :href="route('Booking Details', [band.id, instance.booking.id])"
                      class="font-medium text-blue-600 dark:text-blue-300 hover:underline"
                    >
                      {{ instance.booking.name }}
                    </Link>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                      Recipient: {{ instance.recipient_name }}
                      <span v-if="instance.booking.date"> · Event {{ instance.booking.date }}</span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                      Sent {{ instance.sent_at }}
                      <span v-if="instance.submitted_at"> · Submitted {{ instance.submitted_at }}</span>
                    </div>
                  </div>
                  <span
                    class="text-xs uppercase px-2 py-0.5 rounded flex-shrink-0"
                    :class="{
                      'bg-blue-100 text-blue-800': instance.status === 'sent',
                      'bg-amber-100 text-amber-800': instance.status === 'in_progress',
                      'bg-emerald-100 text-emerald-800': instance.status === 'submitted',
                      'bg-gray-200 text-gray-800': instance.status === 'locked',
                    }"
                  >{{ instance.status.replace('_', ' ') }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Send dialog -->
      <Dialog
        v-model:visible="sendDialogOpen"
        :style="{ width: '500px' }"
        header="Send Questionnaire"
        modal
      >
        <div class="flex flex-col space-y-4">
          <p class="text-sm text-gray-600 dark:text-gray-300">
            Pick a booking and a recipient. The recipient receives the email; any contact on
            the booking can edit the responses.
          </p>
          <div>
            <label class="block text-sm font-medium mb-1">Booking</label>
            <Select
              v-model="sendForm.booking"
              :options="bookings"
              option-label="label"
              :option-disabled="(opt) => opt.already_sent"
              placeholder="Select a booking"
              filter
              class="w-full"
              @change="onBookingChanged"
            >
              <template #option="slotProps">
                <div class="flex flex-col">
                  <span :class="{ 'text-gray-400': slotProps.option.already_sent }">
                    {{ slotProps.option.name }}
                    <span
                      v-if="slotProps.option.already_sent"
                      class="ml-1 text-xs italic"
                    >(already sent)</span>
                  </span>
                  <span class="text-xs text-gray-500">{{ slotProps.option.date || 'no date' }}</span>
                </div>
              </template>
            </Select>
            <small v-if="sendErrors.questionnaire_id" class="text-red-600">{{ sendErrors.questionnaire_id }}</small>
          </div>

          <div v-if="sendForm.booking">
            <label class="block text-sm font-medium mb-1">Recipient</label>
            <Select
              v-if="recipientOptions.length > 0"
              v-model="sendForm.recipient"
              :options="recipientOptions"
              option-label="name"
              placeholder="Select a recipient"
              class="w-full"
            />
            <p v-else class="text-sm text-amber-700 dark:text-amber-400">
              This booking has no contacts. Add a contact to the booking first.
            </p>
            <small v-if="sendErrors.recipient_contact_id" class="text-red-600">{{ sendErrors.recipient_contact_id }}</small>
          </div>
        </div>
        <template #footer>
          <Button label="Cancel" text @click="sendDialogOpen = false" />
          <Button
            :label="sending ? 'Sending…' : 'Send'"
            :disabled="sending || !sendForm.booking || !sendForm.recipient"
            @click="sendQuestionnaire"
          />
        </template>
      </Dialog>
    </Container>
  </breeze-authenticated-layout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import Container from '@/Components/Container.vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'

const props = defineProps({
  band: { type: Object, required: true },
  questionnaire: { type: Object, required: true },
  fieldCount: { type: Number, default: 0 },
  instances: { type: Array, default: () => [] },
  bookings: { type: Array, default: () => [] },
})

// Augment booking labels for the Select
onMounted(() => {
  props.bookings.sort((a, b) => new Date(a.date || 0) - new Date(b.date || 0));
  props.bookings.forEach(b => {
    b.label = b.date ? `${b.name} — ${b.date}` : b.name
  })
})

const sendDialogOpen = ref(false)
const sending = ref(false)
const sendErrors = reactive({})
const sendForm = reactive({
  booking: null,
  recipient: null,
})

const recipientOptions = computed(() => sendForm.booking?.contacts ?? [])

function onBookingChanged() {
  sendForm.recipient = (sendForm.booking?.contacts ?? []).find(c => c.is_primary)
    ?? sendForm.booking?.contacts?.[0]
    ?? null
}

function openSendDialog() {
  sendForm.booking = null
  sendForm.recipient = null
  Object.keys(sendErrors).forEach(k => delete sendErrors[k])
  sendDialogOpen.value = true
}

function sendQuestionnaire() {
  if (!sendForm.booking || !sendForm.recipient) return

  sending.value = true
  Object.keys(sendErrors).forEach(k => delete sendErrors[k])

  router.post(
    route('bookings.questionnaires.send', { band: props.band.id, booking: sendForm.booking.id }),
    {
      questionnaire_id: props.questionnaire.id,
      recipient_contact_id: sendForm.recipient.id,
    },
    {
      preserveScroll: true,
      onError: (e) => {
        Object.assign(sendErrors, e)
        sending.value = false
      },
      onSuccess: () => {
        sending.value = false
        sendDialogOpen.value = false
        // back() in the controller returns to this page; Inertia auto-refreshes props.
      },
    }
  )
}
</script>
