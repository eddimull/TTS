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
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
              <h3 class="text-base font-semibold text-gray-900 dark:text-gray-50 flex items-center">
                <i class="pi pi-send mr-2" />
                Sent
              </h3>
              <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">

                <IconField class="w-full md:w-auto" :class="{ 'flex-1': search }">
                  <InputIcon class="pi pi-search" />
                  <InputText
                    class="w-full"
                    name="search questionnaire instances"
                    v-model="search"
                    placeholder="Search"
                  />
                </IconField>
                                <MultiSelect
                  class="w-full md:w-auto"
                  v-model="statusFilter"
                  :options="statusOptions"
                  option-label="label"
                  option-value="value"
                  placeholder="Filter status"
                  display="chip"
                />
              </div>
            </div>

            <DataTable
              :value="filteredInstances"
              :paginator="instances.length > 10"
              :rows="10"
              :rows-per-page-options="[10, 25, 50]"
              striped-rows
              row-hover
              sort-field="sent_at_iso"
              :sort-order="-1"
              responsive-layout="scroll"
              data-key="id"
            >
              <Column field="booking.name" header="Booking" sortable>
                <template #body="{ data }">
                  <Link
                    :href="route('Booking Details', [band.id, data.booking.id])"
                    class="font-medium text-blue-600 dark:text-blue-300 hover:underline"
                  >
                    {{ data.booking.name }}
                  </Link>
                  <div
                    v-if="data.booking.date"
                    class="text-xs text-gray-500 dark:text-gray-400"
                  >
                    Event {{ data.booking.date }}
                  </div>
                </template>
              </Column>
              <Column field="recipient_name" header="Recipient" sortable class="hidden md:table-cell" header-class="hidden md:table-cell"/>
              <Column field="sent_at_iso" header="Sent" sortable>
                <template #body="{ data }">
                  {{ data.sent_at }}
                </template>
              </Column>
              <Column field="submitted_at_iso" header="Submitted" sortable class="hidden md:table-cell" header-class="hidden md:table-cell">
                <template #body="{ data }">
                  {{ data.submitted_at || '—' }}
                </template>
              </Column>
              <Column field="status" header="Status" sortable>
                <template #body="{ data }">
                  <span
                    class="text-xs uppercase px-2 py-0.5 rounded"
                    :class="{
                      'bg-blue-100 text-blue-800': data.status === 'sent',
                      'bg-amber-100 text-amber-800': data.status === 'in_progress',
                      'bg-emerald-100 text-emerald-800': data.status === 'submitted',
                      'bg-gray-200 text-gray-800': data.status === 'locked',
                    }"
                  >{{ data.status.replace('_', ' ') }}</span>
                </template>
              </Column>
              <Column header="" :style="{ width: '4rem' }">
                <template #body="{ data }">
                  <Button
                    v-tooltip.bottom="'View answers'"
                    icon="pi pi-eye"
                    text
                    size="small"
                    data-test="preview-instance"
                    @click="openSubmissionPreview(data)"
                  />
                </template>
              </Column>
              <template #empty>
                <div class="text-sm text-gray-500 dark:text-gray-400 py-6 text-center">
                  <template v-if="instances.length === 0">
                    This template hasn't been sent yet.
                    <span v-if="bookings.length > 0">
                      Click <strong>Send</strong> above to send it to a booking contact.
                    </span>
                  </template>
                  <template v-else>
                    No matches for the current filters.
                  </template>
                </div>
              </template>
            </DataTable>
          </div>
        </div>
      </div>

      <!-- Submission Preview Modal -->
      <SubmissionPreview
        v-model="previewOpen"
        :instance="previewInstance"
      />

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
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import MultiSelect from 'primevue/multiselect'
import SubmissionPreview from '@/Components/Questionnaires/SubmissionPreview.vue'

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

const search = ref('')
const statusOptions = [
  { label: 'Sent', value: 'sent' },
  { label: 'In progress', value: 'in_progress' },
  { label: 'Submitted', value: 'submitted' },
  { label: 'Locked', value: 'locked' },
]
const statusFilter = ref([])

const filteredInstances = computed(() => {
  const term = search.value.trim().toLowerCase()
  const statuses = statusFilter.value
  return props.instances.filter((i) => {
    if (statuses.length > 0 && !statuses.includes(i.status)) return false
    if (!term) return true
    return (
      (i.booking?.name ?? '').toLowerCase().includes(term)
      || (i.recipient_name ?? '').toLowerCase().includes(term)
    )
  })
})

const previewOpen = ref(false)
const previewInstance = ref(null)
function openSubmissionPreview(instance) {
  previewInstance.value = instance
  previewOpen.value = true
}

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
