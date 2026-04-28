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
        @row-click="(e) => visitEditor(e.data)"
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
              @click.stop="visitEditor(data)"
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
  },
  props: {
    band: { type: Object, default: null },
    questionnaires: { type: Array, default: () => [] },
    availableBands: { type: Array, default: () => [] },
  },
  data() {
    return {
      dialogOpen: false,
      saving: false,
      submitted: false,
      errors: {},
      form: {
        name: '',
        description: '',
        band: null,
      },
    }
  },
  methods: {
    openNew() {
      this.submitted = false
      this.errors = {}
      this.form = {
        name: '',
        description: '',
        band: this.band && this.band.id
          ? this.availableBands.find((b) => b.id === this.band.id) || null
          : null,
      }
      this.dialogOpen = true
    },
    closeDialog() {
      this.saving = false
      this.dialogOpen = false
    },
    visitEditor(data) {
      const bandId = data.band_id ?? this.band?.id
      this.$inertia.visit(
        this.route('questionnaires.edit', { band: bandId, questionnaire: data.slug })
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
