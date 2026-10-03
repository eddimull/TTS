<template>
  <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-4">
    <!-- Summary header (always visible); toggles the full catalog -->
    <button
      type="button"
      data-test="toggle"
      class="w-full flex items-center gap-3 px-4 py-3 text-left"
      :aria-expanded="open ? 'true' : 'false'"
      @click="open = !open"
    >
      <i class="pi pi-star-fill text-amber-500 flex-shrink-0" />
      <div class="flex-1 min-w-0">
        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
          Client requests
        </div>
        <div class="text-sm text-gray-900 dark:text-gray-100 truncate">{{ summary }}</div>
      </div>
      <span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">
        {{ open ? 'Hide catalog' : 'Show full catalog' }}
      </span>
      <i :class="['pi flex-shrink-0 text-gray-400', open ? 'pi-chevron-up' : 'pi-chevron-down']" />
    </button>

    <!-- Whole active catalog, alphabetical, with the client's marks -->
    <div v-if="open" class="border-t border-gray-100 dark:border-gray-700 max-h-[28rem] overflow-y-auto">
      <div
        v-for="song in sortedSongs"
        :key="song.id"
        data-test="catalog-row"
        class="flex items-start gap-3 px-4 py-2.5 border-b border-gray-100 dark:border-gray-700 last:border-b-0"
      >
        <span class="w-5 flex-shrink-0 pt-0.5 text-center">
          <i
            v-if="statusFor(song.id) === 'must_play'"
            data-test="must-play-star"
            class="pi pi-star-fill text-amber-500 text-sm"
            aria-label="Must play"
          />
        </span>
        <div class="flex-1 min-w-0">
          <div
            data-test="song-title"
            :class="[
              'font-medium truncate',
              statusFor(song.id) === 'do_not_play'
                ? 'line-through text-gray-400 dark:text-gray-500'
                : 'text-gray-900 dark:text-gray-50',
            ]"
          >
            {{ song.title }}
          </div>
          <div
            v-if="song.artist"
            :class="[
              'text-sm truncate',
              statusFor(song.id) === 'do_not_play'
                ? 'line-through text-gray-400 dark:text-gray-500'
                : 'text-gray-500 dark:text-gray-400',
            ]"
          >
            {{ song.artist }}
          </div>
          <div v-if="statusFor(song.id) === 'must_play'" class="text-xs font-semibold text-amber-600 dark:text-amber-400">
            Must play
          </div>
          <div v-else-if="statusFor(song.id) === 'do_not_play'" class="text-xs font-semibold text-red-600 dark:text-red-400">
            Do not play
          </div>
        </div>
        <span v-if="song.song_key" class="text-sm text-gray-400 dark:text-gray-500 flex-shrink-0">
          {{ song.song_key }}
        </span>
      </div>
    </div>
  </div>
</template>

<script>
import { DateTime } from 'luxon';
import { clientSongStatus } from '@/Utils/clientSongRequests';

/**
 * Collapsible card for the setlist editor: summarises the client's must-play /
 * do-not-play questionnaire picks and, when expanded, lists the band's whole
 * active catalog with those songs marked. Web counterpart of the mobile
 * "Catalog" segment.
 */
export default {
  props: {
    songs: { type: Array, default: () => [] },
    requests: { type: Object, required: true },
  },

  data() {
    return { open: false };
  },

  computed: {
    sortedSongs() {
      return [...this.songs].sort((a, b) =>
        (a.title || '').localeCompare(b.title || '', undefined, { sensitivity: 'base' })
      );
    },

    summary() {
      const ids = new Set(this.songs.map(s => s.id));
      const must = (this.requests.must_play || []).filter(id => ids.has(id)).length;
      const skip = (this.requests.do_not_play || []).filter(id => ids.has(id)).length;
      const source = this.requests.source;
      const parts = [];
      if (must > 0) parts.push(`${must} must play`);
      if (skip > 0) parts.push(`${skip} do not play`);
      if (source?.name) parts.push(`from ${source.name}`);
      if (source?.submitted_at) {
        const d = DateTime.fromISO(source.submitted_at);
        if (d.isValid) parts.push(`submitted ${d.toFormat('MMM d')}`);
      }
      return parts.join(', ');
    },
  },

  methods: {
    statusFor(songId) {
      return clientSongStatus(this.requests, songId);
    },
  },
};
</script>
