<template>
  <Dialog
    :visible="visible"
    @update:visible="emit('update:visible', $event)"
    header="Edit Node Label"
    :modal="true"
    :closable="true"
    :style="{ width: '400px' }"
  >
    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Custom Label</label>
        <InputText
          v-model="localLabel"
          placeholder="Enter custom label..."
          class="w-full"
          autofocus
          @keyup.enter="handleSave"
        />
        <small class="text-gray-500 dark:text-gray-400 mt-1 block">
          Leave empty to use default node label
        </small>
      </div>
    </div>

    <template #footer>
      <Button
        label="Cancel"
        severity="secondary"
        @click="emit('update:visible', false)"
      />
      <Button
        label="Save"
        @click="handleSave"
      />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'

const props = defineProps({
  visible: Boolean,
  currentLabel: String
})

const emit = defineEmits(['update:visible', 'save'])

const localLabel = ref('')

watch(() => props.visible, (newVal) => {
  if (newVal) {
    localLabel.value = props.currentLabel || ''
  }
})

const handleSave = () => {
  emit('save', localLabel.value.trim() || null)
  emit('update:visible', false)
}
</script>
