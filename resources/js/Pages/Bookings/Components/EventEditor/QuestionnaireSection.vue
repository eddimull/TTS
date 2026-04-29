<template>
  <div v-if="instances?.length" class="bg-white dark:bg-slate-800 rounded-xl shadow p-4 mb-4">
    <h3 class="text-lg font-semibold mb-3">Questionnaires</h3>

    <div
      v-for="instance in instances"
      :key="instance.id"
      class="border rounded-lg p-3 mb-3"
    >
      <div class="flex justify-between items-start mb-2">
        <div>
          <h4 class="font-medium">{{ instance.name }}</h4>
          <p class="text-xs text-gray-500">
            Sent to {{ instance.recipient_name }} on {{ instance.sent_at }}
            <span v-if="instance.submitted_at"> · Submitted {{ instance.submitted_at }}</span>
            <span class="ml-2 px-2 py-0.5 rounded text-xs uppercase"
              :class="{
                'bg-blue-100 text-blue-800': instance.status === 'sent',
                'bg-amber-100 text-amber-800': instance.status === 'in_progress',
                'bg-emerald-100 text-emerald-800': instance.status === 'submitted',
                'bg-gray-200 text-gray-800': instance.status === 'locked',
              }"
            >{{ instance.status }}</span>
          </p>
        </div>
        <div class="flex gap-2">
          <Button
            v-if="hasUnappliedMappings(instance)"
            label="Apply all pending"
            size="small"
            @click="applyAll(instance)"
          />
          <Button
            label="Append all to notes"
            size="small"
            outlined
            @click="appendToNotes(instance)"
          />
        </div>
      </div>

      <div v-for="field in instance.fields" :key="field.id" class="text-sm py-2 border-t dark:border-slate-700">
        <h5 v-if="field.type === 'header'" class="font-semibold text-base mt-2">{{ field.label }}</h5>
        <div v-else-if="field.type !== 'instructions'" class="flex justify-between items-start gap-3">
          <div class="flex-1 min-w-0">
            <p class="font-medium">{{ field.label }}</p>
            <p class="text-gray-700 dark:text-gray-200 break-words">{{ formatValue(instance.responses[field.id]?.value) || '(not answered)' }}</p>
            <p v-if="field.mapping_label" class="text-xs text-gray-500 italic mt-1">
              ↪ maps to: {{ field.mapping_label }}
            </p>
          </div>
          <div v-if="field.mapping_target && instance.responses[field.id]?.value">
            <Button
              v-if="needsApply(instance.responses[field.id])"
              label="Apply"
              size="small"
              @click="applyOne(instance, instance.responses[field.id].response_id)"
            />
            <span
              v-else
              class="text-xs text-emerald-600"
            >Applied</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import Button from 'primevue/button'

const props = defineProps({
  eventId: { type: Number, required: true },
  instances: { type: Array, default: () => [] },
})

function formatValue(v) {
  if (v === null || v === undefined || v === '') return null
  const decoded = (() => {
    try { return JSON.parse(v) } catch { return null }
  })()
  if (Array.isArray(decoded)) return decoded.join(', ')
  return v
}

function needsApply(response) {
  if (!response.applied_to_event_at) return true
  return new Date(response.updated_at) > new Date(response.applied_to_event_at)
}

function hasUnappliedMappings(instance) {
  return instance.fields.some(
    (f) => f.mapping_target && instance.responses[f.id]?.value && needsApply(instance.responses[f.id])
  )
}

function applyOne(instance, responseId) {
  router.post(
    route('events.questionnaires.apply_response', {
      event: props.eventId,
      instance: instance.id,
      response: responseId,
    }),
    {},
    { preserveScroll: true }
  )
}

function applyAll(instance) {
  router.post(
    route('events.questionnaires.apply_all', {
      event: props.eventId,
      instance: instance.id,
    }),
    {},
    { preserveScroll: true }
  )
}

function appendToNotes(instance) {
  router.post(
    route('events.questionnaires.append_to_notes', {
      event: props.eventId,
      instance: instance.id,
    }),
    {},
    { preserveScroll: true }
  )
}
</script>
