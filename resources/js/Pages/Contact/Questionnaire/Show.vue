<template>
  <ContactLayout>
    <div class="max-w-2xl mx-auto py-8 px-4">
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl p-6 md:p-8">
        <div class="mb-6">
          <p class="text-sm text-gray-500 mb-1">{{ booking.band_name }} — {{ booking.name }} · {{ booking.date }}</p>
          <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-50">{{ instance.name }}</h1>
          <p v-if="instance.description" class="text-gray-600 dark:text-gray-300 mt-2">
            {{ instance.description }}
          </p>
        </div>

        <div
          v-if="instance.is_locked"
          class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900"
        >
          This questionnaire has been locked by the band. Contact them if you need to make changes.
        </div>
        <div
          v-else-if="instance.status === 'submitted'"
          class="mb-6 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900"
        >
          Submitted on {{ instance.submitted_at }}. You can still update your answers below.
        </div>
        <div
          v-else
          class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-900"
        >
          Save your answers as you go. Click <strong>Submit</strong> when finished.
        </div>

        <form @submit.prevent="submit">
          <template v-for="field in fields" :key="field.id">
            <div v-if="isVisible(field.id)" class="mb-5">
              <h3 v-if="field.type === 'header'" class="text-xl font-semibold mt-6 mb-2 border-b dark:border-slate-700 pb-1">
                {{ field.label }}
              </h3>
              <p v-else-if="field.type === 'instructions'" class="text-sm text-gray-600 dark:text-gray-300 italic">
                {{ field.label }}
              </p>
              <div v-else>
                <label class="block font-medium mb-1 dark:text-gray-100">
                  {{ field.label }}
                  <span v-if="field.required" class="text-red-600">*</span>
                </label>
                <p v-if="field.help_text" class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ field.help_text }}</p>

                <InputText
                  v-if="field.type === 'short_text'"
                  v-model="answers[field.id]"
                  :disabled="instance.is_locked"
                  class="w-full"
                  @blur="saveField(field)"
                />
                <Textarea
                  v-else-if="field.type === 'long_text'"
                  v-model="answers[field.id]"
                  :disabled="instance.is_locked"
                  rows="3"
                  class="w-full"
                  @blur="saveField(field)"
                />
                <InputText
                  v-else-if="field.type === 'date'"
                  v-model="answers[field.id]"
                  type="date"
                  :disabled="instance.is_locked"
                  class="w-full"
                  @blur="saveField(field)"
                />
                <InputText
                  v-else-if="field.type === 'time'"
                  v-model="answers[field.id]"
                  type="time"
                  :disabled="instance.is_locked"
                  class="w-full"
                  @blur="saveField(field)"
                />
                <InputText
                  v-else-if="field.type === 'email'"
                  v-model="answers[field.id]"
                  type="email"
                  :disabled="instance.is_locked"
                  class="w-full"
                  @blur="saveField(field)"
                />
                <InputText
                  v-else-if="field.type === 'phone'"
                  v-model="answers[field.id]"
                  :disabled="instance.is_locked"
                  class="w-full"
                  @blur="saveField(field)"
                />
                <Select
                  v-else-if="field.type === 'dropdown'"
                  v-model="answers[field.id]"
                  :options="field.settings?.options ?? []"
                  option-label="label"
                  option-value="value"
                  :disabled="instance.is_locked"
                  class="w-full"
                  @change="saveField(field)"
                />
                <MultiSelect
                  v-else-if="field.type === 'multi_select'"
                  v-model="answers[field.id]"
                  :options="field.settings?.options ?? []"
                  option-label="label"
                  option-value="value"
                  :disabled="instance.is_locked"
                  class="w-full"
                  @change="saveField(field)"
                />
                <div v-else-if="field.type === 'checkbox_group'" class="flex flex-col gap-2">
                  <div v-for="opt in (field.settings?.options ?? [])" :key="opt.value" class="flex items-center gap-2">
                    <Checkbox
                      v-model="answers[field.id]"
                      :value="opt.value"
                      :disabled="instance.is_locked"
                      @change="saveField(field)"
                    />
                    <label>{{ opt.label }}</label>
                  </div>
                </div>
                <div v-else-if="field.type === 'yes_no'" class="flex gap-4">
                  <div class="flex items-center gap-2">
                    <RadioButton
                      v-model="answers[field.id]"
                      value="yes"
                      :disabled="instance.is_locked"
                      @change="saveField(field)"
                    /><label>Yes</label>
                  </div>
                  <div class="flex items-center gap-2">
                    <RadioButton
                      v-model="answers[field.id]"
                      value="no"
                      :disabled="instance.is_locked"
                      @change="saveField(field)"
                    /><label>No</label>
                  </div>
                </div>

                <p
                  v-if="savedField === field.id"
                  class="text-xs text-emerald-600 mt-1 transition-opacity duration-500"
                >
                  Saved
                </p>
                <p
                  v-if="errors[field.id]"
                  class="text-xs text-red-600 mt-1"
                >
                  {{ errors[field.id] }}
                </p>
              </div>
            </div>
          </template>

          <div v-if="!instance.is_locked" class="mt-8 flex justify-end">
            <Button
              type="submit"
              :label="submitting ? (instance.status === 'submitted' ? 'Updating…' : 'Submitting…') : (instance.status === 'submitted' ? 'Update' : 'Submit')"
              :disabled="submitting"
              size="large"
            />
          </div>
        </form>
      </div>
    </div>
  </ContactLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import ContactLayout from '@/Layouts/ContactLayout.vue'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import Checkbox from 'primevue/checkbox'
import RadioButton from 'primevue/radiobutton'
import Button from 'primevue/button'
import { isFieldVisible } from './visibility.js'

const props = defineProps({
  booking: { type: Object, required: true },
  instance: { type: Object, required: true },
  fields: { type: Array, default: () => [] },
  responses: { type: Object, default: () => ({}) },
})

// Initialize answers from server-provided responses, defaulting types appropriately
const answers = reactive({})
props.fields.forEach((f) => {
  const fromServer = props.responses[f.id]
  if (fromServer !== undefined && fromServer !== null) {
    answers[f.id] = fromServer
  } else if (f.type === 'multi_select' || f.type === 'checkbox_group') {
    answers[f.id] = []
  } else {
    answers[f.id] = ''
  }
})

const submitting = ref(false)
const savedField = ref(null)
const errors = reactive({})

function isVisible(fieldId) {
  return isFieldVisible(fieldId, props.fields, answers)
}

async function saveField(field) {
  if (props.instance.is_locked) return
  errors[field.id] = null

  try {
    await axios.patch(
      route('portal.booking.questionnaire.respond', {
        booking: props.booking.id,
        instance: props.instance.id,
      }),
      {
        instance_field_id: field.id,
        value: answers[field.id],
      }
    )
    savedField.value = field.id
    setTimeout(() => {
      if (savedField.value === field.id) savedField.value = null
    }, 1500)
  } catch (err) {
    errors[field.id] = 'Could not save. We will retry on your next change.'
  }
}

function submit() {
  if (props.instance.is_locked) return
  submitting.value = true
  router.post(
    route('portal.booking.questionnaire.submit', {
      booking: props.booking.id,
      instance: props.instance.id,
    }),
    {},
    {
      onError: (e) => {
        submitting.value = false
        if (e.fields) {
          ;(Array.isArray(e.fields) ? e.fields : [e.fields]).forEach((id) => {
            errors[id] = 'This field is required.'
          })
          // Scroll to first error
          const firstId = Array.isArray(e.fields) ? e.fields[0] : e.fields
          const el = document.querySelector(`[data-field-id="${firstId}"]`)
          if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
        }
      },
      onSuccess: () => {
        submitting.value = false
      },
    }
  )
}
</script>
