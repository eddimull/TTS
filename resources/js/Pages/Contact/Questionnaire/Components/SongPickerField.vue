<template>
  <div>
    <div class="flex items-center justify-between mb-2">
      <p class="text-xs uppercase font-medium" :class="purposeBadgeClass">
        {{ purposeLabel }}
      </p>
      <span class="text-xs text-gray-500 dark:text-gray-400">
        {{ selectedCount }} selected
      </span>
    </div>

    <div class="border border-gray-200 dark:border-slate-600 rounded">
      <div class="p-2 border-b border-gray-200 dark:border-slate-600">
        <InputText
          v-model="search"
          placeholder="Search songs by title, artist, or genre…"
          class="w-full"
          :disabled="disabled"
        />
      </div>
      <div
        v-if="songs.length === 0"
        class="p-4 text-sm text-gray-500 dark:text-gray-400 italic"
      >
        Your band's song catalog is empty.
      </div>
      <div
        v-else-if="filteredSongs.length === 0"
        class="p-4 text-sm text-gray-500 dark:text-gray-400 italic"
      >
        No songs match "{{ search }}".
      </div>
      <ul
        v-else
        class="max-h-72 overflow-y-auto"
      >
        <li
          v-for="song in filteredSongs"
          :key="song.id"
          class="border-b last:border-b-0 border-gray-100 dark:border-slate-700"
        >
          <label
            class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700/40"
          >
            <Checkbox
              :model-value="isSelected(song.id)"
              :binary="true"
              :disabled="disabled"
              @update:model-value="toggle(song.id)"
            />
            <div class="min-w-0 flex-1">
              <div class="text-sm text-gray-900 dark:text-gray-100 truncate">
                {{ song.title }}
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                <span v-if="song.artist">{{ song.artist }}</span>
                <span v-if="song.artist && song.genre"> · </span>
                <span v-if="song.genre">{{ song.genre }}</span>
              </div>
            </div>
          </label>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import InputText from 'primevue/inputtext'
import Checkbox from 'primevue/checkbox'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  songs: { type: Array, default: () => [] },
  purpose: { type: String, default: 'general' }, // 'must_play' | 'do_not_play' | 'general'
  disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'change'])

const search = ref('')

const filteredSongs = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.songs
  return props.songs.filter((s) =>
    [s.title, s.artist, s.genre].some(
      (v) => typeof v === 'string' && v.toLowerCase().includes(q)
    )
  )
})

const selectedCount = computed(() => (props.modelValue || []).length)

function isSelected(id) {
  return (props.modelValue || []).includes(id)
}

function toggle(id) {
  const current = (props.modelValue || []).slice()
  const idx = current.indexOf(id)
  if (idx >= 0) {
    current.splice(idx, 1)
  } else {
    current.push(id)
  }
  emit('update:modelValue', current)
  emit('change', current)
}

const purposeLabel = computed(() => {
  if (props.purpose === 'must_play') return 'Must play'
  if (props.purpose === 'do_not_play') return 'Do not play'
  return 'Preferences'
})

const purposeBadgeClass = computed(() => {
  if (props.purpose === 'must_play') return 'text-emerald-700 dark:text-emerald-400'
  if (props.purpose === 'do_not_play') return 'text-red-700 dark:text-red-400'
  return 'text-gray-600 dark:text-gray-300'
})
</script>
