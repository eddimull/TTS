<template>
  <breeze-authenticated-layout>
    <template #header>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <Link
            :href="route('setlists.show', event.key)"
            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
          >
            <i class="pi pi-arrow-left text-lg" />
          </Link>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-50 leading-tight">
            Live — {{ event.title }}
          </h2>
        </div>
        <div class="flex items-center gap-2">
          <Tag
            :value="sessionStatus"
            :severity="statusSeverity"
          />
          <Button
            v-if="localIsCaptain && localSession?.status === 'active'"
            label="Take a Break"
            icon="pi pi-pause"
            severity="warn"
            outlined
            size="small"
            :loading="startingBreak"
            @click="takeBreak"
          />
          <Button
            v-if="localIsCaptain && (localSession?.status === 'active' || localSession?.status === 'break')"
            label="End Show"
            icon="pi pi-stop"
            severity="danger"
            outlined
            size="small"
            @click="confirmEnd"
          />
        </div>
      </div>
    </template>

    <Container>

      <!-- No session yet -->
      <div
        v-if="!localSession"
        class="bg-white dark:bg-slate-800 rounded-lg shadow p-12 text-center"
      >
        <i class="pi pi-music text-5xl text-gray-300 dark:text-gray-600 mb-4" />
        <p class="text-gray-500 dark:text-gray-400 mb-4">No live session started yet.</p>
        <Button
          v-if="canWrite"
          label="Start Session"
          icon="pi pi-play"
          @click="startSession"
          :loading="starting"
        />
      </div>

      <!-- Session completed -->
      <div
        v-else-if="localSession.status === 'completed'"
        class="bg-white dark:bg-slate-800 rounded-lg shadow p-12 text-center"
      >
        <i class="pi pi-check-circle text-5xl text-green-400 mb-4" />
        <p class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-1">Show complete!</p>
        <p class="text-gray-500 dark:text-gray-400">{{ playedCount }} songs performed.</p>
      </div>

      <!-- Set break -->
      <div v-else-if="localSession.status === 'break'" class="space-y-4">

        <!-- Break display (all viewers) -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow p-8 text-center">
          <i class="pi pi-pause-circle text-5xl text-amber-400 mb-4" />
          <p class="text-xs font-semibold uppercase tracking-widest text-amber-500 mb-2">Set Break</p>
          <p class="text-5xl font-mono font-bold text-gray-900 dark:text-gray-50">{{ formatElapsed(breakElapsed) }}</p>
        </div>

        <!-- Captain break controls -->
        <div v-if="localIsCaptain" class="bg-white dark:bg-slate-800 rounded-lg shadow overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
            <i class="pi pi-sparkles text-purple-500" />
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
              Coming Back With
            </p>
          </div>

          <!-- Song locked in -->
          <div v-if="breakSongDetails" class="p-4">
            <div class="flex items-center gap-3 mb-4">
              <div class="flex-1">
                <p class="font-bold text-gray-900 dark:text-gray-50">{{ breakSongDetails.title }}</p>
                <p v-if="breakSongDetails.artist" class="text-sm text-gray-500 dark:text-gray-400">{{ breakSongDetails.artist }}</p>
                <div class="flex flex-wrap gap-2 mt-2">
                  <Tag v-if="breakSongDetails.song_key" :value="breakSongDetails.song_key" severity="info" rounded />
                  <Tag v-if="breakSongDetails.genre" :value="breakSongDetails.genre" severity="secondary" rounded />
                  <Tag v-if="breakSongDetails.bpm" :value="breakSongDetails.bpm + ' BPM'" severity="secondary" rounded />
                  <Tag v-if="breakSongDetails.lead_singer" :value="breakSongDetails.lead_singer" icon="pi pi-microphone" severity="secondary" rounded />
                </div>
              </div>
              <Button icon="pi pi-times" severity="secondary" text rounded @click="clearBreakSong" />
            </div>
            <Button
              icon="pi pi-play"
              label="Resume Show"
              :loading="resumingBreak"
              @click="resumeFromBreak"
            />
          </div>

          <!-- No song locked in yet — show suggestion or pick -->
          <div v-else class="p-4">
            <!-- Loading suggestion -->
            <div v-if="loadingBreakSuggestion && !breakSuggestion" class="text-center py-4">
              <i class="pi pi-spin pi-spinner text-2xl text-purple-400 mb-2" />
              <p class="text-sm text-gray-500 dark:text-gray-400">Finding a banger to come back with…</p>
            </div>

            <!-- Suggestion available -->
            <div v-else-if="breakSuggestion" class="mb-4" :class="{ 'opacity-60': loadingBreakSuggestion }">
              <div class="flex items-start gap-4 mb-4">
                <div class="flex-1">
                  <p class="text-xl font-bold text-gray-900 dark:text-gray-50">{{ breakSuggestion.title }}</p>
                  <p v-if="breakSuggestion.artist" class="text-gray-500 dark:text-gray-400">{{ breakSuggestion.artist }}</p>
                  <div class="flex flex-wrap gap-2 mt-2">
                    <Tag v-if="breakSuggestion.song_key" :value="breakSuggestion.song_key" severity="info" rounded />
                    <Tag v-if="breakSuggestion.genre" :value="breakSuggestion.genre" severity="secondary" rounded />
                    <BpmMetronome v-if="breakSuggestion.bpm" :bpm="breakSuggestion.bpm" />
                    <Tag v-if="breakSuggestion.lead_singer" :value="breakSuggestion.lead_singer" icon="pi pi-microphone" severity="secondary" rounded />
                  </div>
                </div>
              </div>
              <div class="flex flex-wrap gap-2">
                <Button icon="pi pi-check" label="Use This" @click="lockInBreakSuggestion" />
                <Button icon="pi pi-refresh" label="Try Another" severity="secondary" outlined :loading="loadingBreakSuggestion" @click="fetchBreakSuggestion" />
                <Button icon="pi pi-list" label="Pick Manually" severity="secondary" text @click="breakManualPickVisible = true" />
              </div>
            </div>

            <!-- Get suggestion prompt -->
            <div v-else class="text-center py-2">
              <Button icon="pi pi-sparkles" label="Get AI Suggestion" severity="contrast" outlined @click="fetchBreakSuggestion" />
              <Button icon="pi pi-list" label="Pick Manually" severity="secondary" text class="ml-2" @click="breakManualPickVisible = true" />
            </div>
          </div>
        </div>

        <!-- Viewer message -->
        <div v-else class="bg-white dark:bg-slate-800 rounded-lg shadow p-6 text-center text-gray-500 dark:text-gray-400">
          <i class="pi pi-clock text-2xl mb-2" />
          <p>The band will be back shortly.</p>
        </div>
      </div>

      <!-- Active session -->
      <div v-else class="space-y-4">

        <!-- Current song — large display -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow p-6 text-center">
          <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">
            Now Playing
          </p>
          <div v-if="currentSong">
            <h1 class="text-4xl sm:text-5xl font-bold text-gray-900 dark:text-gray-50 mb-2">
              {{ currentSong.title }}
            </h1>
            <p v-if="currentSong.artist" class="text-xl text-gray-500 dark:text-gray-400 mb-3">
              {{ currentSong.artist }}
            </p>
            <div class="flex flex-wrap justify-center gap-2 mb-4">
              <Tag v-if="currentSong.song_key" :value="currentSong.song_key" severity="info" rounded />
              <Tag v-if="currentSong.genre" :value="currentSong.genre" severity="secondary" rounded />
              <BpmMetronome v-if="currentSong.bpm" :bpm="currentSong.bpm" />
              <Tag v-if="currentSong.lead_singer" :value="currentSong.lead_singer" icon="pi pi-microphone" severity="secondary" rounded />
              <Tag v-if="currentSong.is_off_setlist" value="Off Setlist" severity="warn" rounded />
            </div>

            <!-- Crowd reaction display -->
            <div v-if="currentSong.crowd_reaction" class="mb-3">
              <Tag
                :value="currentSong.crowd_reaction === 'positive' ? '👍 Crowd loves it' : currentSong.crowd_reaction === 'negative' ? '👎 Mixed reaction' : 'Neutral'"
                :severity="currentSong.crowd_reaction === 'positive' ? 'success' : currentSong.crowd_reaction === 'negative' ? 'danger' : 'secondary'"
                rounded
              />
            </div>
          </div>
          <div v-else class="text-gray-400 dark:text-gray-500 text-xl py-4">
            Waiting for first song…
          </div>
        </div>

        <!-- Captain controls (when a song is playing) -->
        <div v-if="localIsCaptain && currentSong" class="bg-white dark:bg-slate-800 rounded-lg shadow p-4">
          <div class="flex flex-wrap justify-center gap-3">
            <!-- Thumbs up/down -->
            <Button
              icon="pi pi-thumbs-up"
              label="Crowd Loves It"
              severity="success"
              outlined
              @click="react('positive')"
            />
            <Button
              icon="pi pi-thumbs-down"
              label="Mixed Reaction"
              severity="danger"
              outlined
              @click="react('negative')"
            />

            <Divider layout="vertical" />

            <!-- Navigation -->
            <Button
              icon="pi pi-forward"
              label="Next Song"
              @click="playNext"
              :loading="actioning"
            />
            <Button
              icon="pi pi-step-forward"
              label="Skip"
              severity="secondary"
              outlined
              @click="skip"
            />
            <Button
              icon="pi pi-ban"
              label="Skip & Remove"
              severity="warning"
              outlined
              @click="skipRemove"
            />

            <Divider layout="vertical" />

            <!-- Off setlist -->
            <Button
              icon="pi pi-plus-circle"
              label="Off Setlist"
              severity="info"
              outlined
              @click="offSetlistDialogVisible = true"
            />

            <Divider layout="vertical" />

            <!-- Break -->
            <Button
              icon="pi pi-pause"
              label="Take a Break"
              severity="warn"
              outlined
              :loading="startingBreak"
              @click="takeBreak"
            />
          </div>
        </div>

        <!-- AI Suggestion card (captain only, shown when no current song or after hitting Next) -->
        <div v-if="localIsCaptain && showSuggestionPanel" class="bg-white dark:bg-slate-800 rounded-lg shadow overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
            <i class="pi pi-sparkles text-purple-500" />
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
              AI Suggestion
            </p>
          </div>

          <!-- Loading state (no prior suggestion to show) -->
          <div v-if="loadingSuggestion && !suggestion" class="p-6 text-center">
            <i class="pi pi-spin pi-spinner text-2xl text-purple-400 mb-2" />
            <p class="text-sm text-gray-500 dark:text-gray-400">Picking the best next song…</p>
          </div>

          <!-- Suggestion available (or loading a replacement — keep showing current) -->
          <div v-else-if="suggestion" class="p-4" :class="{ 'opacity-60': loadingSuggestion }">
            <div class="flex items-start gap-4 mb-4">
              <div class="flex-1">
                <p class="text-xl font-bold text-gray-900 dark:text-gray-50">{{ suggestion.title }}</p>
                <p v-if="suggestion.artist" class="text-gray-500 dark:text-gray-400">{{ suggestion.artist }}</p>
                <div class="flex flex-wrap gap-2 mt-2">
                  <Tag v-if="suggestion.forced_transition" value="Required Transition" icon="pi pi-arrow-right" severity="warn" rounded />
                  <Tag v-if="suggestion.song_key" :value="suggestion.song_key" severity="info" rounded />
                  <Tag v-if="suggestion.genre" :value="suggestion.genre" severity="secondary" rounded />
                  <BpmMetronome v-if="suggestion.bpm" :bpm="suggestion.bpm" />
                  <Tag v-if="suggestion.lead_singer" :value="suggestion.lead_singer" icon="pi pi-microphone" severity="secondary" rounded />
                </div>
              </div>
            </div>
            <div class="flex flex-wrap gap-2">
              <Button
                icon="pi pi-check"
                label="Play This"
                @click="acceptSuggestion"
                :loading="acceptingSuggestion"
              />
              <Button
                v-if="!suggestion.forced_transition"
                icon="pi pi-refresh"
                label="Try Another"
                severity="secondary"
                outlined
                @click="fetchSuggestion"
                :loading="loadingSuggestion"
              />
              <Button
                v-if="!suggestion.forced_transition"
                icon="pi pi-list"
                label="Pick Manually"
                severity="secondary"
                text
                @click="manualPickDialogVisible = true"
              />
            </div>
          </div>

          <!-- No suggestion (all songs used) -->
          <div v-else-if="suggestionExhausted" class="p-6 text-center">
            <i class="pi pi-check-circle text-3xl text-green-400 mb-2" />
            <p class="text-sm text-gray-500 dark:text-gray-400">All available songs have been played!</p>
            <Button
              label="End Show"
              icon="pi pi-stop"
              severity="danger"
              outlined
              size="small"
              class="mt-3"
              @click="confirmEnd"
            />
          </div>

          <!-- Get suggestion prompt -->
          <div v-else class="p-6 text-center">
            <Button
              icon="pi pi-sparkles"
              label="Get AI Suggestion"
              severity="contrast"
              outlined
              @click="fetchSuggestion"
            />
          </div>
        </div>

        <!-- Up next -->
        <div v-if="nextSong || autoQueuing" class="bg-white dark:bg-slate-800 rounded-lg shadow p-4">
          <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">
            Up Next
          </p>
          <div v-if="nextSong" class="flex items-center gap-3">
            <div class="flex-1">
              <p class="font-semibold text-gray-900 dark:text-gray-50">{{ nextSong.title }}</p>
              <p v-if="nextSong.artist" class="text-sm text-gray-500 dark:text-gray-400">{{ nextSong.artist }}</p>
            </div>
            <div class="flex items-center gap-2">
              <Tag v-if="nextSong.song_key" :value="nextSong.song_key" severity="info" rounded />
              <Tag v-if="nextSong.bpm" :value="nextSong.bpm + ' BPM'" severity="secondary" rounded />
              <Button
                v-if="localIsCaptain"
                icon="pi pi-refresh"
                severity="secondary"
                text
                rounded
                size="small"
                :loading="swappingNext"
                v-tooltip.top="'Swap for AI suggestion'"
                @click="swapNextSong"
              />
            </div>
          </div>
          <div v-else class="flex items-center gap-2 text-gray-400 dark:text-gray-500">
            <i class="pi pi-spin pi-spinner text-sm" />
            <span class="text-sm">Finding next song…</span>
          </div>
        </div>

        <!-- Full queue -->
        <div v-if="localSession.queue.length > 0" class="bg-white dark:bg-slate-800 rounded-lg shadow overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
              Full Set
            </p>
            <span class="text-xs text-gray-400">{{ remainingCount }} remaining</span>
          </div>
          <div
            v-for="entry in localSession.queue"
            :key="entry.id"
            class="flex items-center gap-3 px-4 py-2 border-b border-gray-100 dark:border-gray-700 last:border-0"
            :class="{
              'bg-blue-50 dark:bg-blue-900/20': entry.position === localSession.current_position && entry.status === 'pending',
              'opacity-40': entry.status === 'played' || entry.status === 'removed',
            }"
          >
            <template v-if="entry.type === 'break'">
              <span class="w-5 text-xs text-center text-gray-400">—</span>
              <div class="flex-1 flex items-center gap-2 text-amber-500 dark:text-amber-400">
                <i class="pi pi-pause text-xs" />
                <span class="text-sm font-medium">Set Break</span>
              </div>
              <i v-if="entry.status === 'played'" class="pi pi-check text-green-500 text-xs" />
            </template>
            <template v-else>
              <span class="w-5 text-xs text-center text-gray-400">{{ entry.position }}</span>
              <div class="flex-1 min-w-0">
                <span class="text-sm font-medium text-gray-900 dark:text-gray-50 truncate block">{{ entry.title }}</span>
                <span v-if="entry.artist" class="text-xs text-gray-400 truncate block">{{ entry.artist }}</span>
              </div>
              <div class="flex items-center gap-1">
                <Tag v-if="entry.is_off_setlist" value="+" severity="warn" rounded class="text-xs" />
                <i v-if="entry.status === 'played'" class="pi pi-check text-green-500 text-xs" />
                <i v-if="entry.status === 'skipped'" class="pi pi-step-forward text-gray-400 text-xs" />
                <i v-if="entry.status === 'removed'" class="pi pi-ban text-red-400 text-xs" />
                <i v-if="entry.crowd_reaction === 'positive'" class="pi pi-thumbs-up text-green-500 text-xs" />
                <i v-if="entry.crowd_reaction === 'negative'" class="pi pi-thumbs-down text-red-400 text-xs" />
              </div>
            </template>
          </div>
        </div>

        <!-- Captain management (captain only) -->
        <div v-if="localIsCaptain" class="bg-white dark:bg-slate-800 rounded-lg shadow p-4">
          <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">
            Captains
          </p>
          <div class="flex flex-wrap gap-2">
            <Tag
              v-for="captain in localSession.captains"
              :key="captain.user_id"
              :value="captain.name"
              icon="pi pi-star"
              severity="warn"
              rounded
            />
            <Button
              label="Promote Someone"
              icon="pi pi-user-plus"
              size="small"
              outlined
              @click="promoteDialogVisible = true"
            />
          </div>
        </div>

      </div>
    </Container>

    <!-- Off setlist song picker -->
    <Dialog
      v-model:visible="offSetlistDialogVisible"
      header="Play Off-Setlist Song"
      :style="{ width: '480px' }"
      modal
    >
      <div class="pt-2">
        <Dropdown
          v-model="offSetlistSongId"
          :options="songOptions"
          option-label="label"
          option-value="id"
          placeholder="Choose a song"
          filter
          class="w-full"
        />
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" outlined @click="offSetlistDialogVisible = false" />
        <Button label="Add Next" icon="pi pi-plus" :disabled="!offSetlistSongId" @click="addOffSetlist" />
      </template>
    </Dialog>

    <!-- Manual song pick dialog -->
    <Dialog
      v-model:visible="manualPickDialogVisible"
      header="Pick Next Song"
      :style="{ width: '480px' }"
      modal
    >
      <div class="pt-2">
        <Dropdown
          v-model="manualPickSongId"
          :options="songOptions"
          option-label="label"
          option-value="id"
          placeholder="Choose a song"
          filter
          class="w-full"
        />
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" outlined @click="manualPickDialogVisible = false" />
        <Button label="Add to Set" icon="pi pi-plus" :disabled="!manualPickSongId" @click="acceptManualPick" />
      </template>
    </Dialog>

    <!-- Promote captain dialog -->
    <Dialog
      v-model:visible="promoteDialogVisible"
      header="Promote to Captain"
      :style="{ width: '400px' }"
      modal
    >
      <div class="pt-2">
        <Dropdown
          v-model="promoteUserId"
          :options="promotableUsers"
          option-label="name"
          option-value="id"
          placeholder="Select a band member"
          class="w-full"
        />
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" outlined @click="promoteDialogVisible = false" />
        <Button label="Promote" icon="pi pi-star" :disabled="!promoteUserId" @click="promote" />
      </template>
    </Dialog>

    <!-- Break manual song pick dialog -->
    <Dialog
      v-model:visible="breakManualPickVisible"
      header="Pick Song to Come Back With"
      :style="{ width: '480px' }"
      modal
    >
      <div class="pt-2">
        <Dropdown
          v-model="breakManualSongId"
          :options="songOptions"
          option-label="label"
          option-value="id"
          placeholder="Choose a song"
          filter
          class="w-full"
        />
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" outlined @click="breakManualPickVisible = false" />
        <Button label="Lock In" icon="pi pi-lock" :disabled="!breakManualSongId" @click="acceptBreakManualPick" />
      </template>
    </Dialog>

    <ConfirmDialog />
  </breeze-authenticated-layout>
</template>

<script>
import { h } from 'vue';
import { Link } from '@inertiajs/vue3';

const BpmMetronome = {
  props: { bpm: { type: Number, required: true } },
  render() {
    const swingMs = Math.round(120000 / this.bpm);
    return h('span', {
      class: 'inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 select-none',
    }, [
      h('span', { class: 'bpm-track' }, [
        h('span', {
          class: 'bpm-pendulum',
          style: { animationDuration: `${swingMs}ms` },
        }),
      ]),
      ` ${this.bpm} BPM`,
    ]);
  },
};

export default {
  components: { Link, BpmMetronome },

  props: {
    event: { type: Object, required: true },
    session: { type: Object, default: null },
    songs: { type: Array, default: () => [] },
    isCaptain: { type: Boolean, default: false },
    currentUserId: { type: Number, required: true },
    canWrite: { type: Boolean, default: false },
  },

  data() {
    return {
      localSession: this.session ? JSON.parse(JSON.stringify(this.session)) : null,
      localIsCaptain: this.isCaptain,
      starting: false,
      actioning: false,
      offSetlistDialogVisible: false,
      offSetlistSongId: null,
      manualPickDialogVisible: false,
      manualPickSongId: null,
      promoteDialogVisible: false,
      promoteUserId: null,
      channel: null,
      // AI suggestion state
      suggestion: null,
      loadingSuggestion: false,
      acceptingSuggestion: false,
      autoQueuing: false,
      suggestionExhausted: false,
      // Whether to show the suggestion panel now
      aiMode: false,
      // Whether this session was started without a pre-built queue (dynamic mode)
      isDynamic: this.session ? !!this.session.is_dynamic : false,
      // Swap "Up Next" state
      swappingNext: false,
      // Break state
      startingBreak: false,
      resumingBreak: false,
      breakSong: null,
      breakSongDetails: null,
      breakSuggestion: null,
      loadingBreakSuggestion: false,
      breakManualPickVisible: false,
      breakManualSongId: null,
      breakElapsed: 0,
      breakTimer: null,
    };
  },

  computed: {
    currentSong() {
      if (!this.localSession) return null;
      return this.localSession.queue.find(
        e => e.position === this.localSession.current_position && e.status === 'pending' && e.type !== 'break'
      ) ?? null;
    },

    nextSong() {
      if (!this.localSession) return null;
      return this.localSession.queue.find(
        e => e.status === 'pending' && e.position > this.localSession.current_position && e.type !== 'break'
      ) ?? null;
    },

    remainingCount() {
      return this.localSession?.queue.filter(e => e.status === 'pending').length ?? 0;
    },

    playedCount() {
      return this.localSession?.queue.filter(e => e.status === 'played').length ?? 0;
    },

    sessionStatus() {
      if (!this.localSession) return 'Not Started';
      return { active: 'Live', paused: 'Paused', break: 'On Break', completed: 'Completed', pending: 'Pending' }[this.localSession.status] ?? this.localSession.status;
    },

    statusSeverity() {
      if (!this.localSession) return 'secondary';
      return { active: 'danger', paused: 'warn', break: 'warn', completed: 'success', pending: 'info' }[this.localSession.status] ?? 'secondary';
    },

    songOptions() {
      return this.songs.map(s => ({
        ...s,
        label: s.artist ? `${s.title} – ${s.artist}` : s.title,
      }));
    },

    promotableUsers() {
      return [];
    },

    showSuggestionPanel() {
      if (!this.localIsCaptain || !this.isDynamic) return false;
      return this.aiMode;
    },
  },

  mounted() {
    if (this.localSession) {
      this.subscribeToChannel();
      // Dynamic session — show suggestion panel if no current song is playing
      if (this.isDynamic && !this.currentSong && this.localSession.status === 'active') {
        this.aiMode = true;
        if (this.localIsCaptain) this.fetchSuggestion();
      }
      // Restore break timer if session is already on break
      if (this.localSession.status === 'break' && this.localSession.break_started_at) {
        this.startBreakTimer(this.localSession.break_started_at);
        if (this.localIsCaptain) this.fetchBreakSuggestion();
      }
    }
  },

  beforeUnmount() {
    this.unsubscribeFromChannel();
    clearInterval(this.breakTimer);
  },

  watch: {
    // Whenever next song disappears (captain advanced past last queued song), auto-queue the next one
    nextSong(val, oldVal) {
      if (!this.isDynamic || !this.localIsCaptain) return;
      if (!val && oldVal && this.currentSong) {
        this.fetchAndAutoAccept();
      }
    },

    // When a current song appears but there's nothing queued next, auto-queue
    currentSong(val) {
      if (!this.isDynamic || !this.localIsCaptain) return;
      if (val && !this.nextSong && !this.autoQueuing) {
        this.fetchAndAutoAccept();
      }
    },
  },

  methods: {
    subscribeToChannel() {
      if (!this.localSession || !window.Echo) return;

      this.channel = window.Echo.private(`setlist.${this.localSession.id}`)
        .subscribed(() => {
          console.log('[Setlist] Channel subscribed successfully');
        })
        .error((err) => {
          console.error('[Setlist] Channel auth error:', err);
          this.$toast?.add({
            severity: 'warn',
            summary: 'Live updates unavailable',
            detail: 'Could not connect to live channel. Refresh to retry.',
            life: 6000,
          });
        })
        .listen('SetlistQueueAdvanced', (e) => {
          this.localSession.current_position = e.current_position;
          this.localSession.queue.forEach(entry => {
            if (entry.status === 'pending' && entry.position < e.current_position) {
              entry.status = 'played';
            }
          });
          if (e.current_song) {
            const entry = this.localSession.queue.find(q => q.id === e.current_song.id);
            if (entry) Object.assign(entry, e.current_song);
          }
        })
        .listen('SetlistQueueUpdated', (e) => {
          this.localSession = {
            ...this.localSession,
            queue: e.queue,
            current_position: e.current_position ?? this.localSession.current_position,
          };
          this.autoQueuing = false;
        })
        .listen('SetlistQueueingNext', () => {
          if (!this.localIsCaptain) this.autoQueuing = true;
        })
        .listen('SetlistSessionStateChanged', (e) => {
          this.localSession.status = e.status;
          this.localSession.current_position = e.current_position;
          if (e.status === 'break') {
            this.startBreakTimer(e.break_started_at);
            if (this.localIsCaptain) this.fetchBreakSuggestion();
          } else if (e.status === 'active' && e.break_started_at === null) {
            this.stopBreakTimer();
            this.breakSong = null;
            this.breakSongDetails = null;
            this.breakSuggestion = null;
          }
        })
        .listen('SetlistCaptainChanged', (e) => {
          if (e.action === 'promoted' && e.user_id === this.currentUserId) {
            this.localIsCaptain = true;
          }
          if (e.action === 'demoted' && e.user_id === this.currentUserId) {
            this.localIsCaptain = false;
          }
        });
    },

    unsubscribeFromChannel() {
      if (this.channel && this.localSession) {
        window.Echo.leave(`setlist.${this.localSession.id}`);
        this.channel = null;
      }
    },

    async startSession() {
      this.starting = true;
      try {
        const { data } = await axios.post(route('setlists.session.start', this.event.key));
        this.localSession = data;
        this.localIsCaptain = true;
        this.subscribeToChannel();
        // If no queue was seeded, enter dynamic AI mode
        if (data.queue.length === 0) {
          this.isDynamic = true;
          this.aiMode = true;
          this.fetchSuggestion();
        }
      } catch (err) {
        this.$toast?.add({
          severity: 'error',
          summary: 'Could not start session',
          detail: err.response?.data?.error ?? 'Unknown error.',
          life: 5000,
        });
      } finally {
        this.starting = false;
      }
    },

    confirmEnd() {
      this.$confirm.require({
        message: 'End the show? The session cannot be restarted.',
        header: 'End Show',
        icon: 'pi pi-exclamation-triangle',
        acceptSeverity: 'danger',
        acceptLabel: 'End Show',
        rejectLabel: 'Cancel',
        accept: () => this.endSession(),
      });
    },

    async endSession() {
      try {
        await axios.delete(route('setlists.session.end', this.event.key));
        this.localSession.status = 'completed';
        this.stopBreakTimer();
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Could not end session', life: 3000 });
      }
    },

    async swapNextSong() {
      if (this.swappingNext) return;
      this.swappingNext = true;
      try {
        const excludeId = this.nextSong?.song_id ?? null;
        const params = excludeId ? { exclude: excludeId } : {};
        const { data } = await axios.get(route('setlists.suggest', this.localSession.id), { params });
        if (!data.suggestion) return;
        await axios.post(route('setlists.replaceNext', this.localSession.id), {
          song_id: data.suggestion.song_id,
        });
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Could not swap song', life: 3000 });
      } finally {
        this.swappingNext = false;
      }
    },

    async takeBreak() {
      this.startingBreak = true;
      try {
        await axios.post(route('setlists.break.start', this.localSession.id));
        this.localSession.status = 'break';
        const now = new Date().toISOString();
        this.localSession.break_started_at = now;
        this.startBreakTimer(now);
        this.fetchBreakSuggestion();
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Could not start break', life: 3000 });
      } finally {
        this.startingBreak = false;
      }
    },

    async resumeFromBreak() {
      if (!this.breakSong) return;
      this.resumingBreak = true;
      try {
        await axios.post(route('setlists.break.resume', this.localSession.id), {
          song_id: this.breakSong,
        });
        this.localSession.status = 'active';
        this.stopBreakTimer();
        this.breakSong = null;
        this.breakSongDetails = null;
        this.breakSuggestion = null;
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Could not resume session', life: 3000 });
      } finally {
        this.resumingBreak = false;
      }
    },

    async fetchBreakSuggestion() {
      if (this.loadingBreakSuggestion) return;
      this.loadingBreakSuggestion = true;
      const excludeId = this.breakSuggestion?.song_id ?? null;
      try {
        const params = excludeId ? { exclude: excludeId } : {};
        const { data } = await axios.get(route('setlists.suggest', this.localSession.id), { params });
        this.breakSuggestion = data.suggestion ?? null;
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Could not fetch suggestion', life: 3000 });
      } finally {
        this.loadingBreakSuggestion = false;
      }
    },

    lockInBreakSuggestion() {
      if (!this.breakSuggestion) return;
      this.breakSong = this.breakSuggestion.song_id;
      this.breakSongDetails = { ...this.breakSuggestion };
    },

    clearBreakSong() {
      this.breakSong = null;
      this.breakSongDetails = null;
    },

    acceptBreakManualPick() {
      if (!this.breakManualSongId) return;
      const song = this.songs.find(s => s.id === this.breakManualSongId);
      if (song) {
        this.breakSong = song.id;
        this.breakSongDetails = { ...song };
      }
      this.breakManualPickVisible = false;
      this.breakManualSongId = null;
    },

    startBreakTimer(isoTimestamp) {
      clearInterval(this.breakTimer);
      const start = new Date(isoTimestamp).getTime();
      this.breakElapsed = Math.floor((Date.now() - start) / 1000);
      this.breakTimer = setInterval(() => {
        this.breakElapsed = Math.floor((Date.now() - start) / 1000);
      }, 1000);
    },

    stopBreakTimer() {
      clearInterval(this.breakTimer);
      this.breakTimer = null;
      this.breakElapsed = 0;
    },

    formatElapsed(seconds) {
      const m = Math.floor(seconds / 60).toString().padStart(2, '0');
      const s = (seconds % 60).toString().padStart(2, '0');
      return `${m}:${s}`;
    },

    advanceLocalQueue(newStatus) {
      const current = this.localSession.queue.find(
        e => e.position === this.localSession.current_position && e.status === 'pending'
      );
      if (current) current.status = newStatus;

      const next = this.localSession.queue.find(
        e => e.status === 'pending' && e.position > this.localSession.current_position
      );
      if (next) {
        this.localSession.current_position = next.position;
      } else if (this.isDynamic) {
        // Dynamic session — ask AI for the next song
        this.suggestion = null;
        this.suggestionExhausted = false;
        this.aiMode = true;
        this.fetchSuggestion();
      } else {
        this.localSession.status = 'completed';
      }
    },

    async playNext() {
      this.actioning = true;
      this.advanceLocalQueue('played');
      try {
        await axios.post(route('setlists.captain.next', this.localSession.id));
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Action failed', life: 3000 });
      } finally {
        this.actioning = false;
      }
    },

    async react(reaction) {
      const current = this.currentSong;
      if (!current) return;
      current.crowd_reaction = reaction;
      try {
        await axios.post(route('setlists.captain.reaction', this.localSession.id), {
          queue_entry_id: current.id,
          reaction,
        });
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Action failed', life: 3000 });
      }
    },

    async skip() {
      this.actioning = true;
      this.advanceLocalQueue('skipped');
      try {
        await axios.post(route('setlists.captain.skip', this.localSession.id));
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Action failed', life: 3000 });
      } finally {
        this.actioning = false;
      }
    },

    async skipRemove() {
      this.actioning = true;
      this.advanceLocalQueue('removed');
      try {
        await axios.post(route('setlists.captain.skipRemove', this.localSession.id));
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Action failed', life: 3000 });
      } finally {
        this.actioning = false;
      }
    },

    async fetchAndAutoAccept() {
      if (this.autoQueuing) return;
      this.autoQueuing = true;
      try {
        // Signal to all viewers that we're finding the next song
        axios.post(route('setlists.queuingNext', this.localSession.id)).catch(() => {});
        const { data } = await axios.get(route('setlists.suggest', this.localSession.id));
        if (!data.suggestion) return;
        await axios.post(route('setlists.acceptSuggestion', this.localSession.id), {
          song_id: data.suggestion.song_id,
        });
      } catch {
        // silently fail — captain can still manually add via Off Setlist
      } finally {
        this.autoQueuing = false;
      }
    },

    async fetchSuggestion() {
      if (this.loadingSuggestion) return;
      this.loadingSuggestion = true;
      const excludeId = this.suggestion?.song_id ?? null;
      this.suggestionExhausted = false;
      try {
        const params = excludeId ? { exclude: excludeId } : {};
        const { data } = await axios.get(route('setlists.suggest', this.localSession.id), { params });
        if (data.suggestion) {
          this.suggestion = { ...data.suggestion, forced_transition: !!data.forced_transition };
        } else {
          this.suggestion = null;
          this.suggestionExhausted = true;
        }
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Could not fetch suggestion', life: 3000 });
      } finally {
        this.loadingSuggestion = false;
      }
    },

    async acceptSuggestion() {
      if (!this.suggestion) return;
      this.acceptingSuggestion = true;
      try {
        const { data } = await axios.post(route('setlists.acceptSuggestion', this.localSession.id), {
          song_id: this.suggestion.song_id,
        });
        // Only set current_position when there was no song playing yet (first song of session)
        if (this.aiMode) {
          this.localSession.current_position = data.entry.position;
        }
        // Clear suggestion and exit AI mode panel
        this.suggestion = null;
        this.aiMode = false;
        // Immediately queue the next song in the background
        this.fetchAndAutoAccept();
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Could not accept suggestion', life: 3000 });
      } finally {
        this.acceptingSuggestion = false;
      }
    },

    async acceptManualPick() {
      if (!this.manualPickSongId) return;
      try {
        const { data } = await axios.post(route('setlists.acceptSuggestion', this.localSession.id), {
          song_id: this.manualPickSongId,
        });
        if (this.aiMode) {
          this.localSession.current_position = data.entry.position;
        }
        this.suggestion = null;
        this.aiMode = false;
        this.manualPickDialogVisible = false;
        this.manualPickSongId = null;
        this.fetchAndAutoAccept();
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Could not add song', life: 3000 });
      }
    },

    async addOffSetlist() {
      if (!this.offSetlistSongId) return;
      try {
        await axios.post(route('setlists.captain.offSetlist', this.localSession.id), {
          song_id: this.offSetlistSongId,
        });
        this.offSetlistDialogVisible = false;
        this.offSetlistSongId = null;
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Action failed', life: 3000 });
      }
    },

    async promote() {
      if (!this.promoteUserId) return;
      try {
        await axios.post(route('setlists.captain.promote', this.localSession.id), {
          user_id: this.promoteUserId,
        });
        this.promoteDialogVisible = false;
        this.promoteUserId = null;
      } catch {
        this.$toast?.add({ severity: 'error', summary: 'Could not promote', life: 3000 });
      }
    },
  },
};
</script>

<style>
@keyframes bpm-pendulum {
  0%   { left: 0%;   background-color: #ef4444; }
  4%   { background-color: #ffffff; }
  8%   { background-color: #ef4444; }
  50%  { left: calc(100% - 8px); background-color: #ef4444; }
  54%  { background-color: #ffffff; }
  58%  { background-color: #ef4444; }
  100% { left: 0%; }
}
.bpm-track {
  position: relative;
  display: inline-block;
  width: 2.25rem;
  height: 8px;
  vertical-align: middle;
  background: rgba(107, 114, 128, 0.25);
  border-radius: 4px;
}
.bpm-pendulum {
  position: absolute;
  top: 0;
  left: 0;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #ef4444;
  animation: bpm-pendulum linear infinite;
  will-change: left, background-color;
}
</style>
