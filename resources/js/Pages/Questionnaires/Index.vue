<template>
  <Layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-50 leading-tight">
        Questionnaires — {{ band.name }}
      </h2>
    </template>

    <Container>
      <div class="card bg-white dark:bg-slate-800 rounded-xl shadow p-4">
        <Toolbar class="p-mb-4 border-b-2 border-gray-100 dark:border-slate-700">
          <template #start>
            <Button
              icon="pi pi-plus"
              label="New Questionnaire"
              class="mr-2"
              severity="secondary"
              @click="dialogOpen = true"
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
      </div>

      <Dialog
        v-model:visible="dialogOpen"
        :style="{ width: '450px' }"
        header="New Questionnaire"
        modal
      >
        <div class="p-fluid space-y-3">
          <div>
            <label for="name" class="block text-sm">Name</label>
            <InputText id="name" v-model="form.name" autofocus />
            <small v-if="errors.name" class="text-red-600">{{ errors.name }}</small>
          </div>
          <div>
            <label for="description" class="block text-sm">Description (optional)</label>
            <Textarea id="description" v-model="form.description" rows="3" />
          </div>
        </div>
        <template #footer>
          <Button label="Cancel" text @click="dialogOpen = false" />
          <Button :label="saving ? 'Creating…' : 'Create'" :disabled="saving" @click="save" />
        </template>
      </Dialog>
    </Container>
  </Layout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import Toolbar from 'primevue/toolbar'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'

const props = defineProps({
  band: { type: Object, required: true },
  questionnaires: { type: Array, default: () => [] },
})

const dialogOpen = ref(false)
const saving = ref(false)
const errors = reactive({})
const form = reactive({ name: '', description: '' })

function visitEditor(data) {
  router.visit(route('questionnaires.edit', { band: props.band.id, questionnaire: data.slug }))
}

function save() {
  saving.value = true
  router.post(
    route('questionnaires.store'),
    { name: form.name, description: form.description, band_id: props.band.id },
    {
      preserveState: true,
      onError: (e) => {
        Object.assign(errors, e)
        saving.value = false
      },
      onSuccess: () => {
        dialogOpen.value = false
        saving.value = false
        form.name = ''
        form.description = ''
      },
    }
  )
}

function archive(data) {
  router.post(route('questionnaires.archive', { band: props.band.id, questionnaire: data.slug }))
}

function restore(data) {
  router.post(route('questionnaires.restore', { band: props.band.id, questionnaire: data.slug }))
}
</script>
