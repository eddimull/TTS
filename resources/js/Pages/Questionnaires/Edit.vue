<template>
  <breeze-authenticated-layout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-50">
          Edit: {{ form.name }}
        </h2>
        <div class="flex gap-2">
          <Link :href="route('questionnaires.preview', { band: band.id, questionnaire: questionnaire.slug })">
            <Button label="Preview" outlined icon="pi pi-eye" />
          </Link>
          <Button
            :label="saving ? 'Saving…' : 'Save'"
            :disabled="saving || !isDirty"
            icon="pi pi-save"
            @click="save"
          />
        </div>
      </div>
    </template>

    <Container>
      <div class="card bg-white dark:bg-slate-800 rounded-xl shadow p-4 mb-4">
        <label class="block text-sm uppercase text-gray-500 mb-1">Name</label>
        <InputText v-model="form.name" class="w-full mb-3" @input="markDirty" />
        <label class="block text-sm uppercase text-gray-500 mb-1">Description</label>
        <Textarea v-model="form.description" rows="2" class="w-full" @input="markDirty" />
      </div>

      <div class="card bg-white dark:bg-slate-900 rounded-xl shadow p-4">
        <h3 class="text-lg font-semibold mb-3">Fields</h3>
        <draggable
          v-model="form.fields"
          item-key="client_id"
          handle=".handle"
          @end="markDirty"
        >
          <template #item="{ element, index }">
            <FieldEditor
              :model-value="element"
              :selected="selectedIdx === index"
              :earlier-fields="form.fields.slice(0, index)"
              :field-type-catalog="fieldTypeCatalog"
              :mapping-target-catalog="mappingTargetCatalog"
              @update:model-value="updateField(index, $event)"
              @duplicate="duplicateField(index)"
              @delete="deleteField(index)"
              @click="selectedIdx = index"
            />
          </template>
        </draggable>

        <Button
          label="Add field"
          icon="pi pi-plus"
          outlined
          class="mt-3"
          @click="addField"
        />
      </div>
    </Container>
  </breeze-authenticated-layout>
</template>

<script setup>
import { ref, reactive, computed, onBeforeMount } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import draggable from 'vuedraggable'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import FieldEditor from './Components/FieldEditor.vue'

const props = defineProps({
  band: Object,
  questionnaire: Object,
  fields: { type: Array, default: () => [] },
  fieldTypeCatalog: { type: Array, required: true },
  mappingTargetCatalog: { type: Array, required: true },
})

const form = reactive({
  name: props.questionnaire.name,
  description: props.questionnaire.description ?? '',
  fields: props.fields.map((f, i) => ({
    ...f,
    client_id: `id-${f.id}`,
    position: (i + 1) * 10,
  })),
})

const selectedIdx = ref(null)
const saving = ref(false)
const isDirty = ref(false)

function markDirty() {
  isDirty.value = true
}

function nextClientId() {
  return `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
}

function addField() {
  form.fields.push({
    id: null,
    client_id: nextClientId(),
    type: 'short_text',
    label: '',
    help_text: '',
    required: false,
    position: (form.fields.length + 1) * 10,
    settings: null,
    visibility_rule: null,
    mapping_target: null,
  })
  selectedIdx.value = form.fields.length - 1
  markDirty()
}

function updateField(idx, value) {
  form.fields[idx] = value
  markDirty()
}

function duplicateField(idx) {
  const copy = JSON.parse(JSON.stringify(form.fields[idx]))
  copy.id = null
  copy.client_id = nextClientId()
  form.fields.splice(idx + 1, 0, copy)
  markDirty()
}

function deleteField(idx) {
  form.fields.splice(idx, 1)
  if (selectedIdx.value === idx) selectedIdx.value = null
  markDirty()
}

function save() {
  saving.value = true
  // recompute positions with gaps
  form.fields.forEach((f, i) => { f.position = (i + 1) * 10 })

  router.put(
    route('questionnaires.update', { band: props.band.id, questionnaire: props.questionnaire.slug }),
    {
      name: form.name,
      description: form.description,
      fields: form.fields,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        saving.value = false
        isDirty.value = false
      },
      onError: () => { saving.value = false },
    }
  )
}

onBeforeMount(() => {
  window.addEventListener('beforeunload', (e) => {
    if (isDirty.value) {
      e.preventDefault()
      e.returnValue = ''
    }
  })
})
</script>
