<template>
  <breeze-authenticated-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-50 leading-tight">
        Questionnaires
      </h2>
    </template>

    <Container>
      <Toolbar class="p-mb-4 border-b-2">
        <template #start>
          <Button
            icon="pi pi-plus"
            label="New Questionnaire"
            class="mr-2"
            severity="secondary"
            text
            @click="openNew"
          />
        </template>
      </Toolbar>

      <DataTable
        :value="questionnaires"
        striped-rows
        row-hover
        responsive-layout="scroll"
        @row-click="(e) => visitTemplate(e.data)"
      >
        <Column field="name" header="Name" sortable />
        <Column field="instances_count" header="Times sent" sortable />
        <Column header="Status">
          <template #body="{ data }">
            <span v-if="data.archived_at" class="text-xs uppercase text-gray-500">Archived</span>
            <span v-else class="text-xs uppercase text-emerald-600">Active</span>
          </template>
        </Column>
        <Column header="Actions">
          <template #body="{ data }">
            <Button
              icon="pi pi-pencil"
              text
              @click.stop="visitTemplate(data)"
            />
            <Button
              v-if="!data.archived_at"
              icon="pi pi-inbox"
              text
              @click.stop="archive(data)"
            />
            <Button
              v-else
              icon="pi pi-undo"
              text
              @click.stop="restore(data)"
            />
          </template>
        </Column>
        <template #empty>
          No questionnaires yet. Click "New Questionnaire" to create your first one.
        </template>
      </DataTable>

      <Dialog
        v-model:visible="dialogOpen"
        :style="{ width: '450px' }"
        header="New Questionnaire"
        :modal="true"
      >
        <div class="flex flex-col space-y-4">
          <div
            v-if="presets.length > 0"
            class="flex flex-col"
          >
            <label class="mb-2 font-medium">Start from</label>
            <SelectButton
              v-model="form.presetKey"
              :options="presetOptions"
              option-label="label"
              option-value="value"
              :allow-empty="false"
              @change="onPresetChanged"
            />
            <small
              v-if="selectedPreset"
              class="text-gray-500 dark:text-gray-400 mt-2 leading-snug"
            >{{ selectedPreset.description }} — {{ selectedPreset.field_count }} fields included.</small>
            <small
              v-else
              class="text-gray-500 dark:text-gray-400 mt-2"
            >Build the questionnaire from scratch.</small>

            <div
              v-if="selectedPreset"
              class="mt-2"
            >
              <button
                type="button"
                class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                @click="showPresetFields = !showPresetFields"
              >
                {{ showPresetFields ? 'Hide fields' : 'Preview fields' }}
              </button>
              <ul
                v-if="showPresetFields"
                class="mt-2 ml-1 max-h-64 overflow-y-auto border border-gray-200 dark:border-slate-600 rounded p-2 text-sm"
              >
                <li
                  v-for="(field, i) in selectedPreset.fields"
                  :key="i"
                  class="py-0.5"
                  :class="{ 'font-semibold mt-2 first:mt-0 text-gray-800 dark:text-gray-100': field.type === 'header', 'italic text-gray-500 dark:text-gray-400 text-xs': field.type === 'instructions' }"
                >
                  <span v-if="field.type === 'header'">— {{ field.label }} —</span>
                  <span v-else-if="field.type === 'instructions'">{{ field.label }}</span>
                  <span v-else>
                    {{ field.label }}
                    <span v-if="field.required" class="text-red-600">*</span>
                    <span class="text-xs text-gray-400 ml-1">({{ formatType(field.type) }})</span>
                  </span>
                </li>
              </ul>
            </div>
          </div>

          <div class="flex flex-col">
            <label for="name" class="mb-2 font-medium">Name</label>
            <InputText
              id="name"
              v-model.trim="form.name"
              autofocus
              class="w-full"
              :class="{ 'p-invalid': submitted && !form.name }"
            />
            <small
              v-if="submitted && !form.name"
              class="text-red-500 mt-1"
            >Name is required.</small>
            <small
              v-if="errors.name"
              class="text-red-500 mt-1"
            >{{ errors.name }}</small>
          </div>

          <div class="flex flex-col">
            <label for="description" class="mb-2 font-medium">Description (optional)</label>
            <Textarea
              id="description"
              v-model="form.description"
              rows="3"
              class="w-full"
            />
          </div>

          <div class="flex flex-col">
            <label for="bandSelection" class="mb-2 font-medium">Band</label>
            <Select
              id="bandSelection"
              v-model="form.band"
              :options="availableBands"
              option-label="name"
              placeholder="Select a Band"
              class="w-full"
              :class="{ 'p-invalid': submitted && !form.band }"
            >
              <template #value="slotProps">
                <div v-if="slotProps.value && slotProps.value.id">
                  <span>{{ slotProps.value.name }}</span>
                </div>
                <span v-else>{{ slotProps.placeholder }}</span>
              </template>
            </Select>
            <small
              v-if="submitted && !form.band"
              class="text-red-500 mt-1"
            >Please select a band.</small>
            <small
              v-if="errors.band_id"
              class="text-red-500 mt-1"
            >{{ errors.band_id }}</small>
          </div>
        </div>
        <template #footer>
          <Button label="Cancel" text @click="closeDialog" />
          <Button
            :label="saving ? 'Creating…' : 'Create'"
            :disabled="saving"
            @click="save"
          />
        </template>
      </Dialog>
    </Container>
  </breeze-authenticated-layout>
</template>

<script>
import Toolbar from 'primevue/toolbar'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'

export default {
  components: {
    Toolbar,
    DataTable,
    Column,
    Dialog,
    InputText,
    Textarea,
    Button,
    Select,
    SelectButton,
  },
  props: {
    band: { type: Object, default: null },
    questionnaires: { type: Array, default: () => [] },
    availableBands: { type: Array, default: () => [] },
    presets: { type: Array, default: () => [] },
  },
  data() {
    return {
      dialogOpen: false,
      saving: false,
      submitted: false,
      errors: {},
      showPresetFields: false,
      form: {
        name: '',
        description: '',
        band: null,
        presetKey: '',
      },
    }
  },
  computed: {
    presetOptions() {
      return [
        { label: 'Blank', value: '' },
        ...this.presets.map(p => ({ label: p.name, value: p.key })),
      ]
    },
    selectedPreset() {
      return this.form.presetKey
        ? this.presets.find(p => p.key === this.form.presetKey) ?? null
        : null
    },
  },
  methods: {
    openNew() {
      this.submitted = false
      this.errors = {}
      this.showPresetFields = false
      this.form = {
        name: '',
        description: '',
        band: this.band && this.band.id
          ? this.availableBands.find((b) => b.id === this.band.id) || null
          : null,
        presetKey: '',
      }
      this.dialogOpen = true
    },
    onPresetChanged() {
      this.showPresetFields = false
      const preset = this.selectedPreset
      if (preset) {
        // Pre-fill name and description from the preset (only if untouched)
        if (!this.form.name) this.form.name = preset.name
        if (!this.form.description) this.form.description = preset.description
      }
    },
    formatType(type) {
      const map = {
        short_text: 'short text',
        long_text: 'long text',
        date: 'date',
        time: 'time',
        email: 'email',
        phone: 'phone',
        dropdown: 'dropdown',
        multi_select: 'multi-select',
        checkbox_group: 'checkboxes',
        yes_no: 'yes/no',
      }
      return map[type] ?? type
    },
    closeDialog() {
      this.saving = false
      this.dialogOpen = false
    },
    visitTemplate(data) {
      const bandId = data.band_id ?? this.band?.id
      this.$inertia.visit(
        this.route('questionnaires.show', { band: bandId, questionnaire: data.slug })
      )
    },
    save() {
      this.submitted = true
      if (!this.form.name || !this.form.band) {
        return
      }
      this.saving = true
      this.errors = {}
      this.$inertia.post(
        this.route('questionnaires.store'),
        {
          name: this.form.name,
          description: this.form.description,
          band_id: this.form.band.id,
          preset_key: this.form.presetKey || null,
        },
        {
          preserveState: true,
          onError: (e) => {
            this.errors = e
            this.saving = false
          },
          onSuccess: () => {
            this.closeDialog()
            this.submitted = false
          },
        }
      )
    },
    archive(data) {
      const bandId = data.band_id ?? this.band?.id
      this.$inertia.post(
        this.route('questionnaires.archive', { band: bandId, questionnaire: data.slug })
      )
    },
    restore(data) {
      const bandId = data.band_id ?? this.band?.id
      this.$inertia.post(
        this.route('questionnaires.restore', { band: bandId, questionnaire: data.slug })
      )
    },
  },
}
</script>
