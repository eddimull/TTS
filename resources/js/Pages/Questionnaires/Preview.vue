<template>
  <breeze-authenticated-layout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-50">
          Preview: {{ questionnaire.name }}
        </h2>
        <Link :href="route('questionnaires.edit', { band: band.id, questionnaire: questionnaire.slug })">
          <Button label="Back to editor" outlined icon="pi pi-arrow-left" />
        </Link>
      </div>
    </template>

    <Container>
      <div class="max-w-2xl mx-auto bg-white dark:bg-slate-800 rounded-xl shadow p-6">
        <h2 class="text-2xl font-bold mb-2">{{ questionnaire.name }}</h2>
        <p v-if="questionnaire.description" class="text-gray-600 dark:text-gray-300 mb-6">
          {{ questionnaire.description }}
        </p>

        <div v-for="field in fields" :key="field.id" class="mb-5">
          <h3 v-if="field.type === 'header'" class="text-xl font-semibold mt-6 mb-2 border-b pb-1">
            {{ field.label }}
          </h3>
          <p v-else-if="field.type === 'instructions'" class="text-sm text-gray-600 italic">
            {{ field.label }}
          </p>
          <div v-else>
            <label class="block font-medium mb-1">
              {{ field.label }}
              <span v-if="field.required" class="text-red-600">*</span>
            </label>
            <p v-if="field.help_text" class="text-xs text-gray-500 mb-1">{{ field.help_text }}</p>
            <InputText v-if="field.type === 'short_text'" disabled placeholder="Short answer" class="w-full" />
            <Textarea v-else-if="field.type === 'long_text'" disabled placeholder="Long answer" rows="3" class="w-full" />
            <InputText v-else-if="field.type === 'date'" type="date" disabled class="w-full" />
            <InputText v-else-if="field.type === 'time'" type="time" disabled class="w-full" />
            <InputText v-else-if="field.type === 'email'" type="email" disabled placeholder="email@example.com" class="w-full" />
            <InputText v-else-if="field.type === 'phone'" disabled placeholder="555-0123" class="w-full" />
            <Dropdown v-else-if="field.type === 'dropdown'" disabled :options="field.settings?.options ?? []" option-label="label" class="w-full" />
            <MultiSelect v-else-if="field.type === 'multi_select'" disabled :options="field.settings?.options ?? []" option-label="label" class="w-full" />
            <div v-else-if="field.type === 'checkbox_group'">
              <div v-for="opt in (field.settings?.options ?? [])" :key="opt.value" class="flex items-center gap-2">
                <Checkbox disabled binary />
                <label>{{ opt.label }}</label>
              </div>
            </div>
            <div v-else-if="field.type === 'yes_no'" class="flex gap-3">
              <RadioButton disabled value="yes" /><label>Yes</label>
              <RadioButton disabled value="no" /><label>No</label>
            </div>
          </div>
        </div>
      </div>
    </Container>
  </breeze-authenticated-layout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Dropdown from 'primevue/dropdown'
import MultiSelect from 'primevue/multiselect'
import Checkbox from 'primevue/checkbox'
import RadioButton from 'primevue/radiobutton'
import Button from 'primevue/button'

defineProps({
  band: Object,
  questionnaire: Object,
  fields: { type: Array, default: () => [] },
})
</script>
