<template>
  <breeze-authenticated-layout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-50 leading-tight">
          Song List
        </h2>
        <div class="flex items-center gap-3">
          <Dropdown
            v-if="availableBands && availableBands.length > 1"
            :model-value="selectedBand"
            :options="availableBands"
            option-label="name"
            placeholder="Select Band"
            class="w-48"
            @change="changeBand"
          />
          <Button
            v-if="canWrite"
            icon="pi pi-plus"
            label="Add Song"
            @click="openCreateDialog"
          />
        </div>
      </div>
    </template>

    <Container>

      <!-- No band state -->
      <div
        v-if="!band"
        class="text-center py-16 bg-white dark:bg-slate-800 rounded-lg shadow"
      >
        <i class="pi pi-music text-5xl text-gray-300 dark:text-gray-600 mb-4" />
        <p class="text-gray-500 dark:text-gray-400">
          No band available. Please create or join a band first.
        </p>
      </div>

      <div v-else class="bg-white dark:bg-slate-800 rounded-lg shadow overflow-hidden">
        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 p-4 border-b border-gray-200 dark:border-gray-700">
          <span class="p-input-icon-left flex-1">
            <i class="pi pi-search" />
            <InputText
              v-model="globalFilter"
              placeholder="Search songs..."
              class="w-full"
            />
          </span>
          <div class="flex items-center gap-2">
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer">
              <Checkbox v-model="showInactiveOnly" :binary="true" />
              Show inactive
            </label>
          </div>
        </div>

        <!-- DataTable -->
        <DataTable
          :value="filteredSongs"
          :loading="saving"
          paginator
          :rows="25"
          :rows-per-page-options="[10, 25, 50, 100]"
          sort-field="title"
          :sort-order="1"
          class="p-datatable-sm"
          striped-rows
          responsive-layout="scroll"
        >
          <template #empty>
            <div class="text-center py-12 text-gray-500 dark:text-gray-400">
              <i class="pi pi-music text-4xl mb-3" />
              <p class="mt-2">No songs found.</p>
              <Button
                v-if="canWrite"
                label="Add the first song"
                class="mt-4"
                @click="openCreateDialog"
              />
            </div>
          </template>

          <Column field="title" header="Title" sortable />
          <Column field="artist" header="Artist" sortable />
          <Column field="song_key" header="Key" sortable style="width: 90px" />
          <Column field="genre" header="Genre" sortable style="width: 130px" />
          <Column field="bpm" header="BPM" sortable style="width: 80px" />
          <Column header="Lead Singer" style="width: 160px">
            <template #body="{ data }">
              {{ data.lead_singer?.display_name ?? '—' }}
            </template>
          </Column>
          <Column header="Transition To" style="width: 180px">
            <template #body="{ data }">
              <span v-if="data.transition_song">
                {{ data.transition_song.title }}
                <span v-if="data.transition_song.artist" class="text-gray-400 text-xs">
                  ({{ data.transition_song.artist }})
                </span>
              </span>
              <span v-else>—</span>
            </template>
          </Column>
          <Column field="notes" header="Notes" style="max-width: 200px">
            <template #body="{ data }">
              <span
                v-if="data.notes"
                class="truncate block max-w-xs text-sm text-gray-600 dark:text-gray-400"
                :title="data.notes"
              >{{ data.notes }}</span>
              <span v-else>—</span>
            </template>
          </Column>
          <Column header="Active" style="width: 80px">
            <template #body="{ data }">
              <Tag
                :value="data.active ? 'Active' : 'Inactive'"
                :severity="data.active ? 'success' : 'secondary'"
              />
            </template>
          </Column>
          <Column v-if="canWrite" style="width: 80px" body-class="text-right">
            <template #body="{ data }">
              <Button
                icon="pi pi-pencil"
                text
                rounded
                size="small"
                @click="openEditDialog(data)"
              />
            </template>
          </Column>
        </DataTable>
      </div>

    <!-- Create / Edit Dialog -->
    <Dialog
      v-model:visible="dialogVisible"
      :header="editingSong ? 'Edit Song' : 'Add Song'"
      :style="{ width: '600px' }"
      modal
      @hide="resetForm"
    >
      <div class="flex flex-col gap-4 pt-2">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <!-- Title -->
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Title <span class="text-red-500">*</span>
            </label>
            <InputText v-model="form.title" class="w-full" placeholder="Song title" />
          </div>

          <!-- Artist -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Artist</label>
            <InputText v-model="form.artist" class="w-full" placeholder="Artist / band name" />
          </div>

          <!-- Key -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Key</label>
            <div class="flex gap-2">
              <Dropdown
                v-model="form.keyNote"
                :options="keyNotes"
                placeholder="Note"
                show-clear
                class="flex-1"
              />
              <Dropdown
                v-model="form.keyMode"
                :options="keyModes"
                placeholder="maj/min"
                class="w-28"
              />
            </div>
          </div>

          <!-- Genre -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Genre</label>
            <Dropdown
              v-model="form.genre"
              :options="allGenres"
              placeholder="Select a genre"
              show-clear
              editable
              class="w-full"
            />
          </div>

          <!-- BPM -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">BPM</label>
            <InputNumber
              v-model="form.bpm"
              class="w-full"
              :min="1"
              :max="999"
              placeholder="Tempo"
            />
          </div>

          <!-- Lead Singer -->
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Lead Singer</label>
            <Dropdown
              v-model="form.lead_singer_id"
              :options="rosterMemberOptions"
              option-label="display_name"
              option-value="id"
              placeholder="Instrumental / none"
              show-clear
              class="w-full"
            />
          </div>

          <!-- Transition Song -->
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Transition To</label>
            <Dropdown
              v-model="form.transition_song_id"
              :options="otherSongs"
              option-label="label"
              option-value="id"
              placeholder="No transition"
              show-clear
              filter
              class="w-full"
            />
          </div>

          <!-- Notes -->
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
            <Textarea v-model="form.notes" class="w-full" rows="3" placeholder="Any notes about this song..." />
          </div>

          <!-- Active -->
          <div class="sm:col-span-2 flex items-center gap-2">
            <Checkbox v-model="form.active" :binary="true" input-id="activeCheck" />
            <label for="activeCheck" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Active</label>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-between">
          <Button
            v-if="editingSong"
            label="Delete"
            severity="danger"
            outlined
            :loading="deleting"
            @click="confirmDelete"
          />
          <div class="flex gap-2 ml-auto">
            <Button label="Cancel" severity="secondary" outlined @click="dialogVisible = false" />
            <Button
              :label="editingSong ? 'Update' : 'Save'"
              :loading="saving"
              @click="saveSong"
            />
          </div>
        </div>
      </template>
    </Dialog>

    <!-- Delete confirmation -->
    <ConfirmDialog />
    </Container>
  </breeze-authenticated-layout>

</template>

<script>
import { router } from '@inertiajs/vue3';

export default {
  props: {
    band: { type: Object, default: null },
    songs: { type: Array, default: () => [] },
    rosterMembers: { type: Array, default: () => [] },
    genres: { type: Array, default: () => [] },
    availableBands: { type: Array, default: () => [] },
    canWrite: { type: Boolean, default: false },
  },

  data() {
    return {
      globalFilter: '',
      showInactiveOnly: false,
      dialogVisible: false,
      saving: false,
      deleting: false,
      editingSong: null,
      form: this.emptyForm(),
      keyNotes: ['A', 'A#', 'B', 'C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#'],
      keyModes: ['maj', 'min'],
    };
  },

  computed: {
    selectedBand() {
      return this.availableBands.find(b => b.id === this.band?.id) ?? null;
    },

    filteredSongs() {
      let list = this.songs;

      if (!this.showInactiveOnly) {
        list = list.filter(s => s.active);
      }

      if (this.globalFilter) {
        const q = this.globalFilter.toLowerCase();
        list = list.filter(s =>
          s.title?.toLowerCase().includes(q) ||
          s.artist?.toLowerCase().includes(q) ||
          s.genre?.toLowerCase().includes(q) ||
          s.lead_singer?.display_name?.toLowerCase().includes(q)
        );
      }

      return list;
    },

    allGenres() {
      const fromSongs = this.songs.map(s => s.genre).filter(Boolean);
      return [...new Set([...this.genres, ...fromSongs])].sort();
    },

    rosterMemberOptions() {
      return this.rosterMembers;
    },

    otherSongs() {
      return this.songs
        .filter(s => !this.editingSong || s.id !== this.editingSong.id)
        .map(s => ({
          id: s.id,
          label: s.artist ? `${s.title} – ${s.artist}` : s.title,
        }));
    },
  },

  methods: {
    emptyForm() {
      return {
        title: '',
        artist: '',
        keyNote: null,
        keyMode: null,
        genre: '',
        bpm: null,
        notes: '',
        lead_singer_id: null,
        transition_song_id: null,
        active: true,
      };
    },

    parseSongKey(songKey) {
      if (!songKey) return { keyNote: null, keyMode: null };
      const parts = songKey.trim().split(/\s+/);
      return {
        keyNote: parts[0] ?? null,
        keyMode: parts[1] ?? null,
      };
    },

    changeBand(e) {
      router.get(route('songs.index'), { band_id: e.value.id }, { preserveState: false });
    },

    openCreateDialog() {
      this.editingSong = null;
      this.form = this.emptyForm();
      this.dialogVisible = true;
    },

    openEditDialog(song) {
      this.editingSong = song;
      const { keyNote, keyMode } = this.parseSongKey(song.song_key);
      this.form = {
        title: song.title ?? '',
        artist: song.artist ?? '',
        keyNote,
        keyMode,
        genre: song.genre ?? '',
        bpm: song.bpm ?? null,
        notes: song.notes ?? '',
        lead_singer_id: song.lead_singer_id ?? null,
        transition_song_id: song.transition_song_id ?? null,
        active: song.active ?? true,
      };
      this.dialogVisible = true;
    },

    resetForm() {
      this.editingSong = null;
      this.form = this.emptyForm();
    },

    async saveSong() {
      if (!this.form.title.trim()) return;

      this.saving = true;
      const payload = {
        ...this.form,
        song_key: this.form.keyNote
          ? [this.form.keyNote, this.form.keyMode].filter(Boolean).join(' ')
          : null,
      };
      delete payload.keyNote;
      delete payload.keyMode;

      try {
        if (this.editingSong) {
          await axios.patch(route('songs.update', this.editingSong.id), payload);
        } else {
          await axios.post(route('songs.store'), { ...payload, band_id: this.band.id });
        }
        this.dialogVisible = false;
        router.reload({ only: ['songs'] });
      } catch (err) {
        this.$toast?.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.message ?? 'Save failed', life: 5000 });
      } finally {
        this.saving = false;
      }
    },

    confirmDelete() {
      this.$confirm.require({
        message: `Delete "${this.editingSong?.title}"? This cannot be undone.`,
        header: 'Delete Song',
        icon: 'pi pi-exclamation-triangle',
        accept: () => this.deleteSong(),
      });
    },

    async deleteSong() {
      this.deleting = true;
      try {
        await axios.delete(route('songs.destroy', this.editingSong.id));
        this.dialogVisible = false;
        router.reload({ only: ['songs'] });
      } catch (err) {
        this.$toast?.add({ severity: 'error', summary: 'Error', detail: 'Delete failed', life: 5000 });
      } finally {
        this.deleting = false;
      }
    },
  },
};
</script>
