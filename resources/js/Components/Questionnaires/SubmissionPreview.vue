<template>
  <Dialog
    v-model:visible="isVisible"
    :style="{ width: '600px' }"
    :header="instance ? instance.name : 'Questionnaire'"
    modal
    dismissable-mask
  >
    <div v-if="instance" class="space-y-4">
      <div class="text-xs uppercase text-gray-500 dark:text-gray-400">
        <span>Recipient: {{ instance.recipient_name || '—' }}</span>
        <span v-if="instance.sent_at"> · Sent {{ instance.sent_at }}</span>
        <span v-if="instance.submitted_at"> · Submitted {{ instance.submitted_at }}</span>
        <span class="ml-2 px-2 py-0.5 rounded text-[10px]"
          :class="{
            'bg-blue-100 text-blue-800': instance.status === 'sent',
            'bg-amber-100 text-amber-800': instance.status === 'in_progress',
            'bg-emerald-100 text-emerald-800': instance.status === 'submitted',
            'bg-gray-200 text-gray-800': instance.status === 'locked',
          }"
        >{{ instance.status?.replace('_', ' ') }}</span>
      </div>

      <div v-if="!instance.fields?.length" class="text-sm text-gray-500 italic py-4">
        This instance has no fields.
      </div>

      <div v-else class="space-y-4">
        <div v-for="field in instance.fields" :key="field.id">
          <h3
            v-if="field.type === 'header'"
            class="text-base font-semibold border-b dark:border-slate-600 pb-1 mt-4 first:mt-0"
          >
            {{ field.label }}
          </h3>
          <p
            v-else-if="field.type === 'instructions'"
            class="text-xs italic text-gray-500 dark:text-gray-400"
          >
            {{ field.label }}
          </p>
          <div v-else>
            <div class="text-sm font-medium text-gray-700 dark:text-gray-200">
              {{ field.label }}
              <span v-if="field.required" class="text-red-600">*</span>
            </div>
            <div
              class="mt-0.5 text-sm whitespace-pre-wrap break-words"
              :class="{
                'text-gray-900 dark:text-gray-100': hasAnswer(field.id),
                'text-gray-400 italic': !hasAnswer(field.id),
              }"
            >
              {{ formatAnswer(field) }}
            </div>
          </div>
        </div>
      </div>
    </div>
    <template #footer>
      <Button label="Close" text @click="isVisible = false" />
    </template>
  </Dialog>
</template>

<script setup>
import { computed } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  instance: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const isVisible = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})

function hasAnswer(fieldId) {
  const v = props.instance?.responses?.[fieldId]?.value
  return v !== null && v !== undefined && v !== ''
}

function formatAnswer(field) {
  const raw = props.instance?.responses?.[field.id]?.value
  if (raw === null || raw === undefined || raw === '') {
    return '(not answered)'
  }
  // Decode JSON-encoded multi-values
  try {
    const decoded = JSON.parse(raw)
    if (Array.isArray(decoded)) {
      if (field.type === 'song_picker') {
        if (decoded.length === 0) return '(none selected)'
        const lookup = props.instance?.song_lookup ?? {}
        return decoded
          .map((id) => {
            const song = lookup[id]
            if (!song) return `(removed song #${id})`
            return song.artist ? `${song.title} — ${song.artist}` : song.title
          })
          .join(', ')
      }
      // For dropdown/multi-select with options, prefer label over value when possible
      const opts = field.settings?.options ?? []
      return decoded
        .map((v) => {
          const opt = opts.find((o) => o.value === v)
          return opt?.label ?? v
        })
        .join(', ')
    }
  } catch (_) {
    // not JSON, fall through
  }
  if (field.type === 'yes_no') {
    return raw === 'yes' ? 'Yes' : raw === 'no' ? 'No' : raw
  }
  if (field.type === 'dropdown') {
    const opt = (field.settings?.options ?? []).find((o) => o.value === raw)
    return opt?.label ?? raw
  }
  return raw
}
</script>
