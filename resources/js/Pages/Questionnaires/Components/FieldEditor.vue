<template>
  <div
    class="border rounded-lg p-3 mb-2 bg-white dark:bg-slate-800 transition-shadow"
    :class="{ 'shadow-md ring-2 ring-blue-400': selected }"
  >
    <div class="flex justify-between items-start gap-3">
      <div class="flex-1 min-w-0">
        <Dropdown
          v-if="isInputType"
          v-model="local.type"
          :options="typeOptions"
          option-label="label"
          option-value="type"
          placeholder="Field type"
          class="mb-2 text-sm"
          @change="emitChange"
        />
        <InputText
          v-model="local.label"
          :placeholder="isInputType ? 'Question label' : 'Header text'"
          class="w-full mb-1"
          @input="emitChange"
        />
        <InputText
          v-if="isInputType"
          v-model="local.help_text"
          placeholder="Help text (optional)"
          class="w-full text-sm text-gray-500 mb-2"
          @input="emitChange"
        />
        <div v-if="local.type === 'short_text'" class="text-xs text-gray-400 italic">Short answer (preview)</div>
        <div v-else-if="local.type === 'long_text'" class="text-xs text-gray-400 italic">Long answer (preview)</div>
        <div v-else-if="local.type === 'date'" class="text-xs text-gray-400 italic">Date picker (preview)</div>
        <div v-else-if="local.type === 'time'" class="text-xs text-gray-400 italic">Time picker (preview)</div>
        <div v-else-if="local.type === 'email'" class="text-xs text-gray-400 italic">Email (preview)</div>
        <div v-else-if="local.type === 'phone'" class="text-xs text-gray-400 italic">Phone (preview)</div>
        <div v-else-if="local.type === 'yes_no'" class="text-xs text-gray-400 italic">Yes / No (preview)</div>
        <div v-else-if="['dropdown','multi_select','checkbox_group'].includes(local.type)">
          <div class="text-xs text-gray-500 mb-1">Options</div>
          <div
            v-for="(opt, i) in localOptions"
            :key="i"
            class="flex gap-2 mb-1"
          >
            <InputText v-model="opt.label" placeholder="Label" class="text-sm" @input="syncOptions" />
            <InputText v-model="opt.value" placeholder="Value" class="text-sm" @input="syncOptions" />
            <Button icon="pi pi-times" text @click="removeOption(i)" />
          </div>
          <Button icon="pi pi-plus" label="Add option" text @click="addOption" />
        </div>

        <div v-if="selected && isInputType" class="mt-3 pt-3 border-t border-gray-100 dark:border-slate-700 space-y-2">
          <div class="flex items-center gap-2">
            <Checkbox v-model="local.required" binary @change="emitChange" />
            <label class="text-sm">Required</label>
          </div>

          <div>
            <label class="block text-xs text-gray-500 uppercase mb-1">Show this field if…</label>
            <div class="flex gap-2 items-center">
              <Dropdown
                v-model="visibilityDependsOn"
                :options="earlierFieldOptions"
                option-label="label"
                option-value="client_id"
                placeholder="(always show)"
                class="text-sm flex-1"
                show-clear
                @change="updateVisibility"
              />
              <Dropdown
                v-if="visibilityDependsOn"
                v-model="visibilityOperator"
                :options="operatorOptions"
                option-label="label"
                option-value="value"
                class="text-sm w-32"
                @change="updateVisibility"
              />
              <InputText
                v-if="visibilityDependsOn && needsValue"
                v-model="visibilityValue"
                placeholder="value"
                class="text-sm w-32"
                @input="updateVisibility"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs text-gray-500 uppercase mb-1">Maps to event</label>
            <Dropdown
              v-model="local.mapping_target"
              :options="filteredMappingOptions"
              option-label="label"
              option-value="key"
              placeholder="(no mapping)"
              show-clear
              class="text-sm"
              @change="emitChange"
            />
          </div>
        </div>
      </div>

      <div class="flex flex-col gap-1 flex-shrink-0">
        <Button icon="pi pi-bars" text class="cursor-grab handle" />
        <Button icon="pi pi-copy" text @click="$emit('duplicate')" />
        <Button icon="pi pi-trash" text severity="danger" @click="$emit('delete')" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, reactive } from 'vue'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'

const props = defineProps({
  modelValue: { type: Object, required: true },
  selected: { type: Boolean, default: false },
  earlierFields: { type: Array, default: () => [] },
  fieldTypeCatalog: { type: Array, required: true },
  mappingTargetCatalog: { type: Array, required: true },
})
const emit = defineEmits(['update:modelValue', 'duplicate', 'delete'])

const local = reactive({ ...props.modelValue })
const localOptions = ref(local.settings?.options ? [...local.settings.options] : [])

const operatorOptions = [
  { label: 'equals', value: 'equals' },
  { label: 'does not equal', value: 'not_equals' },
  { label: 'contains', value: 'contains' },
  { label: 'is empty', value: 'empty' },
  { label: 'is not empty', value: 'not_empty' },
]

const visibilityDependsOn = ref(local.visibility_rule?.depends_on ?? null)
const visibilityOperator = ref(local.visibility_rule?.operator ?? 'equals')
const visibilityValue = ref(local.visibility_rule?.value ?? '')

const isInputType = computed(() => {
  const def = props.fieldTypeCatalog.find(t => t.type === local.type)
  return def?.is_input ?? true
})

const typeOptions = computed(() => props.fieldTypeCatalog)

const earlierFieldOptions = computed(() =>
  props.earlierFields.map(f => ({ client_id: f.client_id, label: f.label || '(unnamed)' }))
)

const filteredMappingOptions = computed(() =>
  props.mappingTargetCatalog.filter(m => m.compatible_field_types.includes(local.type))
)

const needsValue = computed(() => !['empty', 'not_empty'].includes(visibilityOperator.value))

function emitChange() {
  emit('update:modelValue', { ...local })
}

function syncOptions() {
  local.settings = { ...(local.settings || {}), options: [...localOptions.value] }
  emitChange()
}

function addOption() {
  localOptions.value.push({ value: '', label: '' })
  syncOptions()
}

function removeOption(i) {
  localOptions.value.splice(i, 1)
  syncOptions()
}

function updateVisibility() {
  if (!visibilityDependsOn.value) {
    local.visibility_rule = null
  } else {
    local.visibility_rule = {
      depends_on: visibilityDependsOn.value,
      operator: visibilityOperator.value,
      value: needsValue.value ? visibilityValue.value : null,
    }
  }
  emitChange()
}

watch(() => props.modelValue, (val) => {
  Object.assign(local, val)
  if (val.settings?.options) {
    localOptions.value = [...val.settings.options]
  }
}, { deep: true })
</script>
