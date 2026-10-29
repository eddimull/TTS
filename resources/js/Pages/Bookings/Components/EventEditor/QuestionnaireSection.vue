<template>
  <div v-if="instances?.length">
    <div
      v-for="instance in instances"
      :key="instance.id"
      class="border border-gray-200 dark:border-slate-600 rounded-lg p-3 mb-3 last:mb-0"
      :data-testid="`questionnaire-instance-${instance.id}`"
    >
      <!-- Header -->
      <div class="flex justify-between items-start gap-2 mb-2">
        <div class="min-w-0">
          <h4 class="font-medium text-gray-900 dark:text-gray-50">
            {{ instance.name }}
          </h4>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            Sent to {{ instance.recipient_name }} on {{ instance.sent_at }}<span v-if="instance.sent_by_name"> by {{ instance.sent_by_name }}</span>
            <span v-if="instance.submitted_at"> · Submitted {{ instance.submitted_at }}</span>
            <span
              class="ml-2 px-2 py-0.5 rounded text-xs uppercase"
              :class="statusClass(instance.status)"
            >{{ instance.status }}</span>
          </p>
        </div>
        <div class="flex-shrink-0">
          <Button
            icon="pi pi-ellipsis-h"
            text
            rounded
            size="small"
            aria-label="More questionnaire actions"
            aria-haspopup="true"
            data-testid="questionnaire-more"
            @click="toggleMenu($event, instance)"
          />
        </div>
      </div>

      <!-- Send to Event review (submitted / locked only) -->
      <div
        v-if="isReviewable(instance) && reviews[instance.id]"
        class="mt-3"
      >
        <div
          v-if="reviews[instance.id].loading && !reviews[instance.id].preview"
          class="text-sm text-gray-500 dark:text-gray-400 py-2"
        >
          <i class="pi pi-spin pi-spinner mr-2" />Loading answers…
        </div>
        <div
          v-else-if="reviews[instance.id].loadError"
          class="text-sm text-red-600 dark:text-red-400 py-2"
          role="alert"
        >
          {{ reviews[instance.id].loadError }}
          <button
            type="button"
            class="ml-2 underline"
            @click="loadPreview(instance)"
          >
            Retry
          </button>
        </div>

        <template v-else-if="reviews[instance.id].preview">
          <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
            <h5 class="text-base font-semibold text-gray-900 dark:text-gray-50">
              Send to Event
            </h5>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              {{ summaryLine(instance) }}
            </p>
          </div>

          <!-- Needs a look -->
          <div
            v-if="reviews[instance.id].preview.conflicts.length"
            class="mb-4"
            data-testid="questionnaire-conflicts"
          >
            <h6 class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400 mb-2">
              Needs a look
            </h6>
            <div class="space-y-2">
              <div
                v-if="songConflicts(instance).length"
                class="rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-3"
              >
                <p class="text-sm font-medium text-gray-900 dark:text-gray-50">
                  {{ songConflicts(instance).length === 1 ? '1 song is' : `${songConflicts(instance).length} songs are` }} on both lists
                </p>
                <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                  Picked as must-play and do-not-play
                </p>
                <div
                  v-for="(conflict, ci) in songConflicts(instance)"
                  :key="`song-${conflict.song_id}`"
                  class="flex items-center justify-between gap-3 py-1.5"
                  :class="{ 'border-t border-amber-200 dark:border-amber-800': ci > 0 }"
                  :data-testid="`song-conflict-${conflict.song_id}`"
                >
                  <div class="min-w-0 text-sm">
                    <span class="text-gray-900 dark:text-gray-50">{{ conflict.title }}</span>
                    <span
                      v-if="conflict.artist"
                      class="text-gray-500 dark:text-gray-400"
                    > · {{ conflict.artist }}</span>
                  </div>
                  <div
                    class="inline-flex rounded-md border border-gray-300 dark:border-slate-500 overflow-hidden flex-shrink-0"
                    role="group"
                    :aria-label="`Play ${conflict.title}?`"
                  >
                    <button
                      v-for="opt in songOptions"
                      :key="opt.value"
                      type="button"
                      class="px-3 py-1 text-xs font-medium transition-colors"
                      :class="segmentClass(reviews[instance.id].songs[conflict.song_id] === opt.value)"
                      :aria-pressed="reviews[instance.id].songs[conflict.song_id] === opt.value"
                      :data-testid="`song-${conflict.song_id}-${opt.value}`"
                      @click="reviews[instance.id].songs[conflict.song_id] = opt.value"
                    >
                      {{ opt.label }}
                    </button>
                  </div>
                </div>
              </div>

              <div
                v-for="conflict in venueConflicts(instance)"
                :key="`venue-${conflict.response_id}`"
                class="rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-3"
                data-testid="venue-conflict"
              >
                <p class="text-sm font-medium text-gray-900 dark:text-gray-50">
                  Venue name doesn't match
                </p>
                <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                  Booking says <strong>{{ conflict.booking_value }}</strong>, form says <strong>{{ conflict.form_value }}</strong>.
                </p>
                <div
                  class="inline-flex rounded-md border border-gray-300 dark:border-slate-500 overflow-hidden"
                  role="group"
                  aria-label="Which venue name to keep"
                >
                  <button
                    v-for="opt in venueOptions"
                    :key="opt.value"
                    type="button"
                    class="px-3 py-1 text-xs font-medium transition-colors"
                    :class="segmentClass(reviews[instance.id].venue === opt.value)"
                    :aria-pressed="reviews[instance.id].venue === opt.value"
                    :data-testid="`venue-${opt.value}`"
                    @click="reviews[instance.id].venue = opt.value"
                  >
                    {{ opt.label }}
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Groups -->
          <div
            v-for="group in reviews[instance.id].preview.groups"
            :key="group.key"
            class="mb-4"
            :data-testid="`group-${group.key}`"
          >
            <div class="flex items-center justify-between mb-1">
              <h6 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                → {{ group.label }}
              </h6>
              <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ checkedInGroup(instance, group) }} of {{ group.items.length }}
              </span>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-slate-600 divide-y divide-gray-200 dark:divide-slate-600">
              <div
                v-for="item in group.items"
                :key="item.response_id"
                class="flex flex-wrap sm:flex-nowrap items-start gap-x-3 gap-y-1 p-2.5"
                :data-testid="`item-${item.response_id}`"
              >
                <input
                  :id="`q-${instance.id}-item-${item.response_id}`"
                  v-model="reviews[instance.id].checked[item.response_id]"
                  type="checkbox"
                  class="form-checkbox mt-1 h-4 w-4 rounded text-blue-600 dark:bg-slate-600 dark:border-slate-500"
                  :data-testid="`check-${item.response_id}`"
                >
                <label
                  :for="`q-${instance.id}-item-${item.response_id}`"
                  class="flex-1 min-w-0 cursor-pointer"
                >
                  <span class="block text-sm font-medium text-gray-900 dark:text-gray-50">
                    {{ item.field_label }}
                    <span
                      v-if="item.status === 'applied'"
                      class="ml-1 text-xs font-normal text-emerald-600 dark:text-emerald-400"
                    >Sent</span>
                    <span
                      v-else-if="item.status === 'stale'"
                      class="ml-1 text-xs font-normal text-amber-600 dark:text-amber-400"
                    >Changed since sent</span>
                  </span>
                  <span class="block text-sm text-gray-700 dark:text-gray-300 break-words">
                    {{ item.value_display }}
                  </span>
                </label>
                <select
                  v-if="item.compatible_targets?.length > 1"
                  :value="itemTarget(instance, item)"
                  class="ml-7 sm:ml-0 flex-shrink-0 max-w-[12rem] text-xs py-1 pl-2 pr-7 border border-gray-300 dark:border-slate-500 rounded bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200"
                  :aria-label="`Send ${item.field_label} to…`"
                  :data-testid="`target-${item.response_id}`"
                  @change="setTarget(instance, item, $event.target.value)"
                >
                  <option
                    v-for="t in item.compatible_targets"
                    :key="t.key"
                    :value="t.key"
                  >
                    {{ t.label }}
                  </option>
                </select>
                <span
                  v-else
                  class="ml-7 sm:ml-0 flex-shrink-0 text-xs text-gray-500 dark:text-gray-400 mt-1"
                >{{ item.target_label }}</span>
              </div>
            </div>
          </div>

          <!-- Setlist requests (informational) -->
          <div
            v-if="hasSongRequests(instance)"
            class="mb-4"
            data-testid="song-requests"
          >
            <h6 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              → Setlist requests
            </h6>
            <div class="rounded-lg border border-gray-200 dark:border-slate-600 divide-y divide-gray-200 dark:divide-slate-600 text-sm">
              <div class="flex justify-between p-2.5">
                <span class="text-gray-900 dark:text-gray-50">Must play</span>
                <span class="text-gray-500 dark:text-gray-400">{{ pluralSongs(songRequests(instance).must_play) }}</span>
              </div>
              <div class="flex justify-between p-2.5">
                <span class="text-gray-900 dark:text-gray-50">Do not play</span>
                <span class="text-gray-500 dark:text-gray-400">{{ pluralSongs(songRequests(instance).do_not_play) }}</span>
              </div>
              <div
                v-if="songRequests(instance).write_ins?.length"
                class="p-2.5"
              >
                <p class="text-gray-900 dark:text-gray-50">
                  Must-play write-ins
                </p>
                <p class="text-gray-600 dark:text-gray-400 whitespace-pre-line">
                  {{ songRequests(instance).write_ins.join('\n') }}
                </p>
              </div>
              <div
                v-if="songRequests(instance).do_not_play_write_ins?.length"
                class="p-2.5"
              >
                <p class="text-gray-900 dark:text-gray-50">
                  Do-not-play write-ins
                </p>
                <p class="text-gray-600 dark:text-gray-400 whitespace-pre-line">
                  {{ songRequests(instance).do_not_play_write_ins.join('\n') }}
                </p>
              </div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
              Song picks already feed the setlist editor and live session.
            </p>
          </div>

          <!-- Contacts (informational) -->
          <div
            v-if="reviews[instance.id].preview.contacts.length"
            class="mb-4"
          >
            <h6 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              → Booking contacts
            </h6>
            <div class="rounded-lg border border-gray-200 dark:border-slate-600 divide-y divide-gray-200 dark:divide-slate-600 text-sm">
              <div
                v-for="(contact, i) in reviews[instance.id].preview.contacts"
                :key="i"
                class="p-2.5"
              >
                <p class="text-gray-900 dark:text-gray-50">
                  {{ contact.name || contact.email || contact.phone }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ contact.on_booking ? 'Already on the booking' : 'Not on the booking' }}<span v-if="contact.email"> · {{ contact.email }}</span><span v-if="contact.phone"> · {{ contact.phone }}</span>
                </p>
              </div>
            </div>
          </div>

          <!-- Not sent: answers without a destination -->
          <div
            v-if="reviews[instance.id].preview.unrouted.length"
            class="mb-4"
            data-testid="unrouted"
          >
            <h6 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
              Not sent
            </h6>
            <div class="rounded-lg border border-gray-200 dark:border-slate-600 divide-y divide-gray-200 dark:divide-slate-600">
              <div
                v-for="item in reviews[instance.id].preview.unrouted"
                :key="item.response_id"
                class="flex flex-wrap sm:flex-nowrap items-start gap-x-3 gap-y-1 p-2.5"
                :data-testid="`unrouted-${item.response_id}`"
              >
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 dark:text-gray-50">
                    {{ item.field_label }}
                  </p>
                  <p class="text-sm text-gray-700 dark:text-gray-300 break-words">
                    {{ item.value_display }}
                  </p>
                  <label
                    v-if="reviews[instance.id].unroutedTargets[item.response_id]"
                    class="mt-1 inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400"
                  >
                    <input
                      v-model="reviews[instance.id].remember[item.response_id]"
                      type="checkbox"
                      class="form-checkbox h-3.5 w-3.5 rounded text-blue-600 dark:bg-slate-600 dark:border-slate-500"
                      :data-testid="`remember-${item.response_id}`"
                    >
                    Remember for this questionnaire
                  </label>
                </div>
                <select
                  v-model="reviews[instance.id].unroutedTargets[item.response_id]"
                  class="flex-shrink-0 max-w-[12rem] text-xs py-1 pl-2 pr-7 border border-gray-300 dark:border-slate-500 rounded bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200"
                  :aria-label="`Send ${item.field_label} to…`"
                  :data-testid="`unrouted-target-${item.response_id}`"
                >
                  <option value="">
                    Don't send
                  </option>
                  <option
                    v-for="t in item.compatible_targets"
                    :key="t.key"
                    :value="t.key"
                  >
                    {{ t.label }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
            The full answers stay below. Nothing goes into Notes unless you send it there.
          </p>

          <p
            v-if="reviews[instance.id].sendError"
            class="text-sm text-red-600 dark:text-red-400 mb-2"
            role="alert"
          >
            {{ reviews[instance.id].sendError }}
          </p>

          <div class="flex flex-wrap items-center gap-3">
            <Button
              :label="sendLabel(instance)"
              icon="pi pi-send"
              size="small"
              :loading="reviews[instance.id].sending"
              :disabled="!canSend(instance)"
              data-testid="send-to-event"
              @click="send(instance)"
            />
            <span
              v-if="selection(instance).unresolved > 0"
              class="text-xs text-amber-700 dark:text-amber-400"
              data-testid="unresolved-note"
            >
              Resolve {{ selection(instance).unresolved }} {{ selection(instance).unresolved === 1 ? 'item' : 'items' }} first, or {{ selection(instance).unresolved === 1 ? "it's" : "they're" }} skipped
            </span>
          </div>
        </template>
      </div>

      <!-- All answers -->
      <details
        class="mt-3"
        :open="!isReviewable(instance)"
      >
        <summary class="cursor-pointer text-sm text-blue-600 dark:text-blue-400 select-none">
          All answers
        </summary>
        <div
          v-for="field in instance.fields"
          :key="field.id"
          class="text-sm py-2 border-t border-gray-200 dark:border-slate-600 first:border-t-0"
        >
          <h5
            v-if="field.type === 'header'"
            class="font-semibold text-base mt-2"
          >
            {{ field.label }}
          </h5>
          <div v-else-if="field.type !== 'instructions'">
            <p class="font-medium">
              {{ field.label }}
            </p>
            <p class="text-gray-700 dark:text-gray-200 break-words">
              {{ formatValue(instance.responses?.[field.id]?.value) || '(not answered)' }}
            </p>
          </div>
        </div>
      </details>
    </div>

    <Menu
      ref="menu"
      :model="menuItems"
      :popup="true"
    />

    <!-- Undo toast -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-2"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0 translate-y-2"
    >
      <div
        v-if="undoToast"
        class="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 w-[calc(100%-2rem)] max-w-md rounded-lg shadow-lg bg-gray-900 text-gray-50 dark:bg-slate-100 dark:text-slate-900 px-4 py-3 flex items-center gap-3"
        role="status"
        data-testid="undo-toast"
      >
        <span class="flex-1 text-sm">{{ undoToast.message }}</span>
        <button
          v-if="undoToast.applicationId"
          type="button"
          class="text-sm font-semibold text-blue-300 dark:text-blue-700 hover:underline disabled:opacity-50"
          :disabled="undoToast.busy"
          data-testid="undo-button"
          @click="undo"
        >
          Undo
        </button>
        <button
          type="button"
          class="text-gray-400 dark:text-slate-500 hover:text-gray-200 dark:hover:text-slate-700"
          aria-label="Dismiss"
          @click="dismissToast"
        >
          <i class="pi pi-times text-xs" />
        </button>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { reactive, ref, computed, watch, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import Button from 'primevue/button'
import Menu from 'primevue/menu'

const props = defineProps({
  eventId: { type: Number, required: true },
  instances: { type: Array, default: () => [] },
  // Async hook the editor provides to save pending edits before a send or
  // undo writes to the event.
  beforeSend: { type: Function, default: null },
})

const songOptions = [
  { value: 'must_play', label: 'Play' },
  { value: 'do_not_play', label: "Don't" },
]
const venueOptions = [
  { value: 'booking', label: "Keep booking's" },
  { value: 'form', label: "Use form's" },
]

// Per-instance review state, keyed by instance id. Entries are created by
// the instances watcher (before render), never from the template.
const reviews = reactive({})

function emptyReview() {
  return {
    loading: false,
    loadError: null,
    preview: null,
    checked: {},
    targets: {},
    unroutedTargets: {},
    remember: {},
    songs: {},
    venue: null,
    sending: false,
    sendError: null,
    seq: 0,
  }
}

function isReviewable(instance) {
  return instance.status === 'submitted' || instance.status === 'locked'
}

function statusClass(status) {
  return {
    'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200': status === 'sent',
    'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200': status === 'in_progress',
    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200': status === 'submitted',
    'bg-gray-200 text-gray-800 dark:bg-slate-600 dark:text-gray-100': status === 'locked',
  }
}

function segmentClass(active) {
  return active
    ? 'bg-blue-600 text-white'
    : 'bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-600'
}

function formatValue(v) {
  if (v === null || v === undefined || v === '') return null
  const decoded = (() => {
    try { return JSON.parse(v) } catch { return null }
  })()
  if (Array.isArray(decoded)) return decoded.join(', ')
  return v
}

function errorMessage(error, fallback) {
  const data = error?.response?.data
  if (data?.errors) {
    const first = Object.values(data.errors)[0]
    if (Array.isArray(first) && first.length) return first[0]
  }
  if (data?.message) return data.message
  if (error?.response?.status === 403) return "You don't have permission to do that."
  return fallback
}

// ---- Preview ---------------------------------------------------------------

async function loadPreview(instance) {
  const state = reviews[instance.id] ?? (reviews[instance.id] = emptyReview())
  // Only the latest request may write: overlapping loads (retry, reload,
  // post-send refresh) can resolve out of order.
  const seq = ++state.seq
  state.loading = true
  state.loadError = null
  try {
    const { data } = await axios.get(
      route('events.questionnaires.routing', { event: props.eventId, instance: instance.id })
    )
    if (seq === state.seq) resetSelection(state, data)
  } catch (error) {
    if (seq === state.seq) state.loadError = errorMessage(error, "Couldn't load the answers to send.")
  } finally {
    if (seq === state.seq) state.loading = false
  }
}

function resetSelection(state, preview) {
  state.preview = {
    groups: preview.groups ?? [],
    conflicts: preview.conflicts ?? [],
    song_requests: preview.song_requests ?? {},
    contacts: preview.contacts ?? [],
    unrouted: preview.unrouted ?? [],
  }
  state.checked = {}
  state.targets = {}
  state.unroutedTargets = {}
  state.remember = {}
  state.songs = {}
  state.venue = null
  state.sendError = null
  for (const group of state.preview.groups) {
    for (const item of group.items) {
      state.checked[item.response_id] = !!item.default_checked
    }
  }
  for (const item of state.preview.unrouted) {
    state.unroutedTargets[item.response_id] = ''
  }
  for (const conflict of state.preview.conflicts) {
    if (conflict.kind === 'song_both_lists' && conflict.resolution) {
      state.songs[conflict.song_id] = conflict.resolution
    } else if (conflict.kind === 'venue_mismatch' && conflict.resolution) {
      state.venue = conflict.resolution
    }
  }
}

// What the preview depends on: the instance's answers and their applied
// stamps. A props refresh that changes them (another user's send, the
// mobile app, append-to-notes) re-fetches that instance's preview.
function instanceSignature(instance) {
  const responses = Object.values(instance.responses ?? {})
    .map((r) => `${r?.response_id}:${r?.updated_at ?? ''}:${r?.applied_to_event_at ?? ''}`)
    .sort()
    .join(',')
  return `${instance.id}|${instance.status}|${responses}`
}

const loadedSignatures = {}

watch(
  () => props.instances.filter(isReviewable).map(instanceSignature).join(';'),
  () => {
    for (const instance of props.instances) {
      if (!isReviewable(instance)) continue
      if (!reviews[instance.id]) reviews[instance.id] = emptyReview()
      const signature = instanceSignature(instance)
      if (loadedSignatures[instance.id] === signature) continue
      loadedSignatures[instance.id] = signature
      loadPreview(instance)
    }
  },
  { immediate: true }
)

// ---- Selection -------------------------------------------------------------

function songConflicts(instance) {
  return reviews[instance.id].preview.conflicts.filter((c) => c.kind === 'song_both_lists')
}

function venueConflicts(instance) {
  return reviews[instance.id].preview.conflicts.filter((c) => c.kind === 'venue_mismatch')
}

function unresolvedCount(instance) {
  const state = reviews[instance.id]
  if (!state?.preview) return 0
  return state.preview.conflicts.filter((c) => {
    if (c.kind === 'song_both_lists') return !state.songs[c.song_id]
    if (c.kind === 'venue_mismatch') return !state.venue
    return false
  }).length
}

function itemTarget(instance, item) {
  return reviews[instance.id].targets[item.response_id] ?? item.target
}

function setTarget(instance, item, value) {
  const state = reviews[instance.id]
  if (value === item.target) {
    delete state.targets[item.response_id]
  } else {
    state.targets[item.response_id] = value
  }
  // Picking a destination is a decision to send it.
  state.checked[item.response_id] = true
}

function checkedInGroup(instance, group) {
  const state = reviews[instance.id]
  return group.items.filter((item) => state.checked[item.response_id]).length
}

/** The `items` body for the route call, in preview order. */
function selectedItems(instance) {
  const state = reviews[instance.id]
  if (!state?.preview) return []
  const items = []
  for (const group of state.preview.groups) {
    for (const item of group.items) {
      if (!state.checked[item.response_id]) continue
      items.push({ response_id: item.response_id, target: itemTarget(instance, item) })
    }
  }
  for (const item of state.preview.unrouted) {
    const target = state.unroutedTargets[item.response_id]
    if (!target) continue
    const entry = { response_id: item.response_id, target }
    if (state.remember[item.response_id]) entry.remember = true
    items.push(entry)
  }
  return items
}

/**
 * The `resolutions` body: only choices that differ from what the server
 * already has, so an earlier resolution isn't re-sent on every send.
 */
function selectedResolutions(instance) {
  const state = reviews[instance.id]
  if (!state?.preview) return {}
  const resolutions = {}
  const songs = {}
  for (const conflict of songConflicts(instance)) {
    const value = state.songs[conflict.song_id]
    if (value && value !== conflict.resolution) songs[String(conflict.song_id)] = value
  }
  if (Object.keys(songs).length) resolutions.songs = songs
  const venue = venueConflicts(instance)[0]
  if (venue && state.venue && state.venue !== venue.resolution) resolutions.venue = state.venue
  return resolutions
}

// One pass per instance per change, instead of re-walking the selection
// from every template binding.
const selections = computed(() => {
  const out = {}
  for (const instance of props.instances) {
    if (!reviews[instance.id]?.preview) continue
    out[instance.id] = {
      items: selectedItems(instance),
      resolutions: selectedResolutions(instance),
      unresolved: unresolvedCount(instance),
    }
  }
  return out
})

function selection(instance) {
  return selections.value[instance.id] ?? { items: [], resolutions: {}, unresolved: 0 }
}

function canSend(instance) {
  const state = reviews[instance.id]
  if (!state?.preview || state.sending) return false
  const { items, resolutions } = selection(instance)
  return items.length > 0 || Object.keys(resolutions).length > 0
}

function sendLabel(instance) {
  const { items, resolutions } = selection(instance)
  const n = items.length
  if (n === 0 && Object.keys(resolutions).length) return 'Save choices'
  return `Send ${n} ${n === 1 ? 'item' : 'items'} to event`
}

function summaryLine(instance) {
  const preview = reviews[instance.id].preview
  const count = preview.groups.reduce((sum, g) => sum + g.items.length, 0)
  const places = preview.groups.length
  const parts = [`${count} ${count === 1 ? 'item' : 'items'} → ${places} ${places === 1 ? 'place' : 'places'}`]
  if (preview.conflicts.length) {
    parts.push(`${preview.conflicts.length} need${preview.conflicts.length === 1 ? 's' : ''} a look`)
  }
  return parts.join(' · ')
}

function songRequests(instance) {
  return reviews[instance.id].preview.song_requests ?? {}
}

function hasSongRequests(instance) {
  const s = songRequests(instance)
  return (s.must_play ?? 0) > 0
    || (s.do_not_play ?? 0) > 0
    || s.write_ins?.length > 0
    || s.do_not_play_write_ins?.length > 0
}

function pluralSongs(n) {
  const count = n ?? 0
  return `${count} ${count === 1 ? 'song' : 'songs'}`
}

// ---- Send / undo -----------------------------------------------------------

/**
 * Re-render the page props so the editor picks up the event's new notes /
 * additional_data and the instances' applied stamps. Same path the old
 * Apply buttons took (EventList -> EventEditor prop watchers), so notes the
 * user is in the middle of typing are left alone and the server-driven
 * update doesn't trigger an autosave.
 */
function refreshEditor() {
  router.reload({ preserveScroll: true, preserveState: true })
}

const undoToast = ref(null)
let toastTimer = null

function showToast(toast) {
  clearTimeout(toastTimer)
  undoToast.value = toast
  toastTimer = setTimeout(() => { undoToast.value = null }, toast.applicationId ? 20000 : 6000)
}

function dismissToast() {
  clearTimeout(toastTimer)
  undoToast.value = null
}

onUnmounted(() => clearTimeout(toastTimer))

let sentRemember = false

async function send(instance) {
  const state = reviews[instance.id]
  if (!canSend(instance)) return
  state.sending = true
  state.sendError = null

  try {
    // Save the user's pending edits first so the routed values are written
    // on top of them, not overwritten by a later autosave.
    if (props.beforeSend) await props.beforeSend()
    const body = { items: selectedItems(instance) }
    const resolutions = selectedResolutions(instance)
    if (Object.keys(resolutions).length) body.resolutions = resolutions
    sentRemember = body.items.some((item) => item.remember)

    const { data } = await axios.post(
      route('events.questionnaires.route', { event: props.eventId, instance: instance.id }),
      body
    )
    const n = data.applied ?? body.items.length
    showToast({
      applicationId: data.application_id,
      eventId: props.eventId,
      instanceId: instance.id,
      message: n > 0 ? `Sent ${n} ${n === 1 ? 'item' : 'items'} to the event.` : 'Choices saved.',
      busy: false,
    })
    refreshEditor()
    await loadPreview(instance)
  } catch (error) {
    state.sendError = error?.response?.status === 403 && sentRemember
      ? "You can't change this questionnaire's template, so nothing was sent. Untick \"Remember for this questionnaire\" and send again."
      : errorMessage(error, "Couldn't send the answers. Try again.")
  } finally {
    state.sending = false
  }
}

async function undo() {
  const toast = undoToast.value
  if (!toast?.applicationId || toast.busy) return
  toast.busy = true
  try {
    if (props.beforeSend) await props.beforeSend()
    await axios.post(
      route('events.questionnaires.undo', { event: toast.eventId, application: toast.applicationId })
    )
    showToast({ applicationId: null, message: 'Send undone.', busy: false })
    refreshEditor()
    const instance = props.instances.find((i) => i.id === toast.instanceId)
    if (instance) await loadPreview(instance)
  } catch (error) {
    showToast({
      applicationId: null,
      message: errorMessage(error, "Couldn't undo the send."),
      busy: false,
    })
  }
}

// ---- ⋯ menu ------------------------------------------------------------------

const menu = ref(null)
const menuInstance = ref(null)

const menuItems = computed(() => [
  {
    label: 'Append all to notes',
    icon: 'pi pi-file-edit',
    command: () => menuInstance.value && appendToNotes(menuInstance.value),
  },
  ...(menuInstance.value && isReviewable(menuInstance.value)
    ? [{
        label: 'Reload answers',
        icon: 'pi pi-refresh',
        command: () => menuInstance.value && loadPreview(menuInstance.value),
      }]
    : []),
])

function toggleMenu(event, instance) {
  menuInstance.value = instance
  menu.value?.toggle(event)
}

function appendToNotes(instance) {
  router.post(
    route('events.questionnaires.append_to_notes', {
      event: props.eventId,
      instance: instance.id,
    }),
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        if (isReviewable(instance)) loadPreview(instance)
      },
    }
  )
}
</script>
