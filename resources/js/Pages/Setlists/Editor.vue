<template>
  <breeze-authenticated-layout>
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <Link
            :href="route('events.show', event.key)"
            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
          >
            <i class="pi pi-arrow-left text-lg" />
          </Link>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-50 leading-tight">
            Setlist — {{ event.title }}
          </h2>
        </div>
        <div class="flex items-center gap-2" v-if="canWrite">
          <Button
            label="Generate with AI"
            icon="pi pi-sparkles"
            :loading="generating"
            :disabled="saving"
            @click="openGenerateDialog"
          />
          <Button
            label="Save"
            icon="pi pi-check"
            :loading="saving"
            :disabled="generating || !localSetlist"
            @click="saveSetlist"
          />
          <Link :href="route('setlists.live', event.key)">
            <Button
              label="Go Live"
              icon="pi pi-play"
              severity="danger"
            />
          </Link>
        </div>
      </div>
    </template>

    <Container>
      <!-- Event summary -->
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow p-4 mb-4">
        <div class="flex flex-wrap gap-3 text-sm text-gray-600 dark:text-gray-400">
          <span v-if="event.type"><i class="pi pi-tag mr-1" />{{ event.type.name }}</span>
          <span><i class="pi pi-calendar mr-1" />{{ formatDate(event.date) }}</span>
          <span v-if="event.time"><i class="pi pi-clock mr-1" />{{ formatTime(event.time) }}</span>
          <span v-if="event.roster_members?.length">
            <i class="pi pi-users mr-1" />{{ event.roster_members.length }} musicians
          </span>
        </div>
        <div
          v-if="event.notes"
          class="mt-2 text-xs text-gray-500 dark:text-gray-400 line-clamp-2"
          v-html="event.notes"
        />
      </div>

      <!-- No setlist yet -->
      <div
        v-if="!localSetlist && !generating"
        class="bg-white dark:bg-slate-800 rounded-lg shadow p-12 text-center"
      >
        <i class="pi pi-list-check text-5xl text-gray-300 dark:text-gray-600 mb-4" />
        <p class="text-gray-500 dark:text-gray-400 mb-4">No setlist yet.</p>
        <Button
          v-if="canWrite"
          label="Generate with AI"
          icon="pi pi-sparkles"
          @click="generateSetlist"
        />
      </div>

      <!-- Generating spinner -->
      <div
        v-else-if="generating"
        class="bg-white dark:bg-slate-800 rounded-lg shadow p-12 text-center"
      >
        <i class="pi pi-spin pi-spinner text-4xl text-blue-500 mb-4" />
        <p class="text-gray-500 dark:text-gray-400">AI is building your setlist…</p>
      </div>

      <!-- Setlist editor -->
      <div v-else class="bg-white dark:bg-slate-800 rounded-lg shadow overflow-hidden">
        <!-- Status bar -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-2">
            <Tag
              :value="localSetlist.status === 'ready' ? 'Ready' : 'Draft'"
              :severity="localSetlist.status === 'ready' ? 'success' : 'secondary'"
            />
            <span class="text-sm text-gray-500 dark:text-gray-400">
              {{ localSetlist.songs.length }} songs
              <span v-if="totalDuration"> · ~{{ totalDuration }} min</span>
            </span>
            <span v-if="localSetlist.generated_at" class="text-xs text-gray-400 dark:text-gray-500">
              · AI generated {{ formatRelative(localSetlist.generated_at) }}
            </span>
          </div>
          <div class="flex items-center gap-2" v-if="canWrite">
            <Button
              v-if="localSetlist.status === 'draft'"
              label="Mark Ready"
              size="small"
              severity="success"
              outlined
              @click="markReady"
            />
            <Button
              label="Add Song"
              icon="pi pi-plus"
              size="small"
              outlined
              @click="openAddDialog"
            />
            <Button
              icon="pi pi-refresh"
              size="small"
              severity="danger"
              text
              v-tooltip.top="'Clear setlist'"
              @click="confirmClear"
            />
          </div>
        </div>

        <!-- Song list -->
        <draggable
          v-model="localSetlist.songs"
          item-key="id"
          handle=".drag-handle"
          :disabled="!canWrite"
          @end="onDragEnd"
        >
          <template #item="{ element, index }">
            <div
              class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
            >
              <!-- Position -->
              <span class="w-6 text-center text-sm text-gray-400 dark:text-gray-500 flex-shrink-0">
                {{ index + 1 }}
              </span>

              <!-- Drag handle -->
              <span
                v-if="canWrite"
                class="drag-handle cursor-move text-gray-300 dark:text-gray-600 hover:text-gray-500 dark:hover:text-gray-400 flex-shrink-0"
              >
                <i class="pi pi-bars" />
              </span>

              <!-- Song info -->
              <div class="flex-1 min-w-0">
                <div class="font-medium text-gray-900 dark:text-gray-50 truncate">
                  {{ element.title }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400 flex flex-wrap gap-2">
                  <span v-if="element.artist">{{ element.artist }}</span>
                  <span v-if="element.song_key" class="text-xs bg-gray-100 dark:bg-slate-600 px-1.5 py-0.5 rounded">
                    {{ element.song_key }}
                  </span>
                  <span v-if="element.genre" class="text-xs text-gray-400">{{ element.genre }}</span>
                  <span v-if="element.bpm" class="text-xs text-gray-400">{{ element.bpm }} BPM</span>
                  <span v-if="element.lead_singer" class="text-xs text-gray-400">
                    <i class="pi pi-microphone text-xs" /> {{ element.lead_singer }}
                  </span>
                </div>
                <div v-if="element.notes" class="text-xs text-blue-500 dark:text-blue-400 mt-0.5">
                  {{ element.notes }}
                </div>
              </div>

              <!-- Actions -->
              <div v-if="canWrite" class="flex items-center gap-1 flex-shrink-0">
                <Button
                  icon="pi pi-pencil"
                  text
                  rounded
                  size="small"
                  @click="editEntry(element, index)"
                />
                <Button
                  icon="pi pi-trash"
                  text
                  rounded
                  size="small"
                  severity="danger"
                  @click="removeEntry(index)"
                />
              </div>
            </div>
          </template>
        </draggable>

        <div
          v-if="localSetlist.songs.length === 0"
          class="text-center py-10 text-gray-400 dark:text-gray-500"
        >
          No songs yet. Add songs or generate with AI.
        </div>
      </div>
    </Container>

    <!-- AI generate dialog -->
    <Dialog
      v-model:visible="generateDialogVisible"
      header="Generate Setlist with AI"
      :style="{ width: '480px' }"
      modal
    >
      <div class="flex flex-col gap-3 pt-2">
        <p class="text-sm text-gray-500 dark:text-gray-400">
          AI will build a setlist using the event type, roster, notes, and your song library.
          Add any extra context below.
        </p>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Additional context <span class="text-gray-400 font-normal">(optional)</span>
          </label>
          <Textarea
            v-model="aiContext"
            class="w-full"
            rows="4"
            placeholder="e.g. Keep energy high throughout. The bride hates country music. End with something slow."
            auto-resize
          />
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" outlined @click="generateDialogVisible = false" />
        <Button label="Generate" icon="pi pi-sparkles" :loading="generating" @click="generateSetlist" />
      </template>
    </Dialog>

    <ConfirmDialog />

    <!-- Add / Edit song dialog -->
    <Dialog
      v-model:visible="entryDialogVisible"
      :header="editingIndex !== null ? 'Edit Entry' : 'Add Song'"
      :style="{ width: '500px' }"
      modal
    >
      <div class="flex flex-col gap-4 pt-2">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Song from library
          </label>
          <Dropdown
            v-model="entryForm.song_id"
            :options="songs"
            option-label="label"
            option-value="id"
            placeholder="Select a song"
            show-clear
            filter
            class="w-full"
            @change="onSongSelect"
          />
        </div>

        <div class="text-center text-xs text-gray-400">— or enter custom song —</div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
          <InputText v-model="entryForm.custom_title" class="w-full" placeholder="Song title" :disabled="!!entryForm.song_id" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Artist</label>
          <InputText v-model="entryForm.custom_artist" class="w-full" placeholder="Artist" :disabled="!!entryForm.song_id" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes for this slot</label>
          <InputText v-model="entryForm.notes" class="w-full" placeholder="e.g. Play extended version" />
        </div>
      </div>

      <template #footer>
        <Button label="Cancel" severity="secondary" outlined @click="entryDialogVisible = false" />
        <Button label="Add" @click="confirmEntry" :disabled="!entryForm.song_id && !entryForm.custom_title" />
      </template>
    </Dialog>
  </breeze-authenticated-layout>
</template>

<script>
import { Link, router } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import { DateTime } from 'luxon';

export default {
  components: { Link, draggable },

  props: {
    event: { type: Object, required: true },
    setlist: { type: Object, default: null },
    songs: { type: Array, default: () => [] },
    canWrite: { type: Boolean, default: false },
  },

  data() {
    return {
      localSetlist: this.setlist ? JSON.parse(JSON.stringify(this.setlist)) : null,
      generating: false,
      saving: false,
      generateDialogVisible: false,
      aiContext: '',
      entryDialogVisible: false,
      editingIndex: null,
      entryForm: this.emptyEntryForm(),
    };
  },

  computed: {
    songOptions() {
      return this.songs.map(s => ({
        ...s,
        label: s.artist ? `${s.title} – ${s.artist}` : s.title,
      }));
    },

    totalDuration() {
      // Rough estimate: avg 3.5 min/song
      const count = this.localSetlist?.songs?.length ?? 0;
      if (!count) return null;
      return Math.round(count * 3.5);
    },
  },

  methods: {
    emptyEntryForm() {
      return { song_id: null, custom_title: '', custom_artist: '', notes: '' };
    },

    formatDate(d) {
      if (!d) return '';
      return DateTime.fromISO(d).toFormat('MMM d, yyyy');
    },

    formatTime(t) {
      if (!t) return '';
      const dt = DateTime.fromFormat(t, 'HH:mm:ss');
      return dt.isValid ? dt.toFormat('h:mm a') : t;
    },

    formatRelative(iso) {
      if (!iso) return '';
      return DateTime.fromISO(iso).toRelative();
    },

    openGenerateDialog() {
      this.aiContext = '';
      this.generateDialogVisible = true;
    },

    async generateSetlist() {
      this.generating = true;
      this.generateDialogVisible = false;
      try {
        const { data } = await axios.post(route('setlists.generate', this.event.key), {
          context: this.aiContext || null,
        });
        this.localSetlist = data;
      } catch (err) {
        this.$toast?.add({
          severity: 'error',
          summary: 'Generation failed',
          detail: err.response?.data?.error ?? 'Could not generate setlist.',
          life: 6000,
        });
      } finally {
        this.generating = false;
      }
    },

    async saveSetlist() {
      if (!this.localSetlist) return;
      this.saving = true;
      try {
        const { data } = await axios.put(route('setlists.update', this.event.key), {
          songs: this.localSetlist.songs.map(s => ({
            song_id: s.song_id ?? null,
            custom_title: s.custom_title ?? null,
            custom_artist: s.custom_artist ?? null,
            notes: s.notes ?? null,
          })),
          status: this.localSetlist.status,
        });
        this.localSetlist = data;
        this.$toast?.add({ severity: 'success', summary: 'Saved', life: 2000 });
      } catch (err) {
        const detail = err.response?.data?.message
          ?? JSON.stringify(err.response?.data?.errors ?? err.response?.data ?? 'Save failed');
        this.$toast?.add({ severity: 'error', summary: 'Save failed', detail, life: 6000 });
      } finally {
        this.saving = false;
      }
    },

    async markReady() {
      if (!this.localSetlist) return;
      this.localSetlist.status = 'ready';
      await this.saveSetlist();
    },

    onDragEnd() {
      // Positions are implicit from array order; no extra work needed
    },

    openAddDialog() {
      this.editingIndex = null;
      this.entryForm = this.emptyEntryForm();
      this.entryDialogVisible = true;
    },

    editEntry(element, index) {
      this.editingIndex = index;
      this.entryForm = {
        song_id: element.song_id ?? null,
        custom_title: element.custom_title ?? '',
        custom_artist: element.custom_artist ?? '',
        notes: element.notes ?? '',
      };
      this.entryDialogVisible = true;
    },

    onSongSelect(e) {
      const song = this.songs.find(s => s.id === e.value);
      if (song) {
        this.entryForm.custom_title = '';
        this.entryForm.custom_artist = '';
      }
    },

    confirmEntry() {
      const song = this.songs.find(s => s.id === this.entryForm.song_id);
      const entry = {
        id: Date.now(), // temp client-side id for draggable key
        song_id: this.entryForm.song_id ?? null,
        custom_title: this.entryForm.custom_title || null,
        custom_artist: this.entryForm.custom_artist || null,
        title: song ? song.title : this.entryForm.custom_title,
        artist: song ? song.artist : this.entryForm.custom_artist,
        song_key: song?.song_key ?? null,
        genre: song?.genre ?? null,
        bpm: song?.bpm ?? null,
        lead_singer: song?.lead_singer ?? null,
        notes: this.entryForm.notes || null,
      };

      if (!this.localSetlist) {
        this.localSetlist = { id: null, status: 'draft', generated_at: null, songs: [] };
      }

      if (this.editingIndex !== null) {
        this.localSetlist.songs.splice(this.editingIndex, 1, entry);
      } else {
        this.localSetlist.songs.push(entry);
      }

      this.entryDialogVisible = false;
    },

    removeEntry(index) {
      this.localSetlist.songs.splice(index, 1);
    },

    confirmClear() {
      this.$confirm.require({
        message: 'Clear the entire setlist? This cannot be undone.',
        header: 'Clear Setlist',
        icon: 'pi pi-exclamation-triangle',
        acceptSeverity: 'danger',
        acceptLabel: 'Clear',
        rejectLabel: 'Cancel',
        accept: () => this.clearSetlist(),
      });
    },

    async clearSetlist() {
      this.saving = true;
      try {
        await axios.put(route('setlists.update', this.event.key), {
          songs: [],
          status: 'draft',
        });
        this.localSetlist = { id: this.localSetlist?.id, status: 'draft', generated_at: null, songs: [] };
        this.$toast?.add({ severity: 'info', summary: 'Setlist cleared', life: 2000 });
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Failed to clear setlist', life: 4000 });
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>
