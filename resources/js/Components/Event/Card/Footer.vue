<template>
  <div
    v-if="!isRehearsal"
    class="mt-4 pt-3 flex items-center justify-center gap-6 border-t border-gray-200"
  >
    <a :href="advanceLink" class="flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="h-5 w-5"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
        />
      </svg>
      Advance
    </a>
    <a :href="setlistLink" class="flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
      <i class="pi pi-list-check h-5 w-5" />
      Setlist
    </a>
    <a v-if="liveLink" :href="liveLink" class="flex items-center gap-1 text-sm font-semibold text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 animate-pulse">
      <i class="pi pi-circle-fill text-xs" />
      Join Live
    </a>
  </div>
  <div 
    v-else-if="isVirtual" 
    class="mt-4 pt-3 grid place-items-center border-t border-gray-200 text-gray-500 dark:text-gray-400 text-sm italic"
  >
    Generated from rehearsal schedule
  </div>
</template>
<script>
export default {
    props: {
        event: {
            type: Object,
            required: true
        }
    },
    computed: {
        isVirtual() {
            if (!this.event) return false;
            const key = this.event.key || this.event['key'];
            return key && (key.startsWith('virtual-') || this.event.is_virtual === true || this.event['is_virtual'] === true);
        },
        isRehearsal() {
            // Virtual rehearsals from schedule
            if (this.isVirtual) return true;
            // Saved rehearsals
            if (this.event && this.event.eventable_type === 'App\\Models\\Rehearsal') return true;
            return false;
        },
        advanceLink() {
            if (this.isRehearsal) return '#';
            const key = this.event.key || this.event['key'];
            if (!key) return '#';
            try {
                return this.route('events.advance', {'key': key});
            } catch (e) {
                console.warn('Could not generate advance route:', e);
                return '#';
            }
        },
        setlistLink() {
            const key = this.event.key || this.event['key'];
            if (!key) return '#';
            try {
                return this.route('setlists.show', {'key': key});
            } catch (e) {
                return '#';
            }
        },
        liveLink() {
            if (!this.event.live_session_id) return null;
            const key = this.event.key || this.event['key'];
            if (!key) return null;
            try {
                return this.route('setlists.live', {'key': key});
            } catch (e) {
                return null;
            }
        }
    }
}
</script>