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
    <button
      :class="['flex items-center gap-1 text-sm font-medium', rosterColor]"
      @click="openRoster"
    >
      <i class="pi pi-users h-5 w-5" />
      <span>{{ rosterLabel }}</span>
    </button>
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

  <Dialog
    v-model:visible="showRoster"
    modal
    :header="`Event Roster | ${event.title}`"
    :style="{ width: '500px' }"
  >
    <div v-if="loadingRoster" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl text-gray-400" />
    </div>
    <div v-else-if="rosterError" class="text-red-500 text-sm text-center py-4">
      {{ rosterError }}
    </div>
    <div v-else-if="rosterMembers.length === 0 && rosterSubs.length === 0" class="text-gray-500 text-sm text-center py-4">
      No roster assigned to this event.
    </div>
    <div v-else>
      <div v-if="rosterMembers.length > 0" class="mb-4">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">
          Members ({{ rosterMembers.length }})
        </h3>
        <ul class="space-y-2">
          <li
            v-for="member in rosterMembers"
            :key="member.id"
            class="flex items-center justify-between text-sm"
          >
            <div>
              <span class="font-medium">{{ member.display_name }}</span>
              <span v-if="member.role" class="ml-2 text-xs text-gray-500">{{ member.role }}</span>
            </div>
            <span
              v-if="member.attendance_status"
              :class="statusClass(member.attendance_status)"
              class="text-xs px-2 py-0.5 rounded-full capitalize"
            >
              {{ member.attendance_status }}
            </span>
          </li>
        </ul>
      </div>
      <div v-if="rosterSubs.length > 0">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">
          Subs ({{ rosterSubs.length }})
        </h3>
        <ul class="space-y-2">
          <li
            v-for="sub in rosterSubs"
            :key="sub.id"
            class="flex items-center justify-between text-sm"
          >
            <div>
              <span class="font-medium">{{ sub.display_name }}</span>
              <span v-if="sub.role" class="ml-2 text-xs text-gray-500">{{ sub.role }}</span>
            </div>
            <span
              v-if="sub.attendance_status"
              :class="statusClass(sub.attendance_status)"
              class="text-xs px-2 py-0.5 rounded-full capitalize"
            >
              {{ sub.attendance_status }}
            </span>
          </li>
        </ul>
      </div>
    </div>
  </Dialog>
</template>
<script>
import Dialog from 'primevue/dialog';

export default {
    components: { Dialog },
    props: {
        event: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            showRoster: false,
            loadingRoster: false,
            rosterMembers: [],
            rosterSubs: [],
            rosterError: null,
        };
    },
    computed: {
        isVirtual() {
            if (!this.event) return false;
            const key = this.event.key || this.event['key'];
            return key && (key.startsWith('virtual-') || this.event.is_virtual === true || this.event['is_virtual'] === true);
        },
        isRehearsal() {
            if (this.isVirtual) return true;
            if (this.event && this.event.eventable_type === 'App\\Models\\Rehearsal') return true;
            return false;
        },
        rosterCount() {
            return this.event.roster_count ?? 0;
        },
        subCount() {
            return this.event.sub_count ?? 0;
        },
        absentCount() {
            return this.event.absent_count ?? 0;
        },
        rosterLabel() {
            if (this.rosterCount === 0 && this.subCount === 0) return 'Roster';
            if (this.subCount > 0) return `Roster (${this.rosterCount}/${this.subCount})`;
            return `Roster (${this.rosterCount})`;
        },
        rosterColor() {
            if (this.rosterCount === 0 && this.subCount === 0) {
                return 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100';
            }
            if (this.subCount > 0) {
                return 'text-yellow-600 dark:text-yellow-400 hover:text-yellow-800 dark:hover:text-yellow-200';
            }
            if (this.absentCount > 0) {
                return 'text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200';
            }
            return 'text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200';
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
    },
    methods: {
        async openRoster() {
            this.showRoster = true;
            if (this.rosterMembers.length > 0 || this.rosterSubs.length > 0) return;

            const eventId = this.event.id;
            if (!eventId) {
                this.rosterError = 'Event ID not available.';
                return;
            }

            this.loadingRoster = true;
            this.rosterError = null;

            try {
                const url = this.route('events.members.index', { event: eventId });
                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) throw new Error('Failed to load roster.');

                const data = await response.json();
                const members = data.members ?? [];

                this.rosterMembers = members.filter(m => m.roster_member_id !== null);
                this.rosterSubs = members.filter(m => m.roster_member_id === null);
            } catch (e) {
                this.rosterError = e.message || 'Could not load roster.';
            } finally {
                this.loadingRoster = false;
            }
        },
        statusClass(status) {
            const map = {
                confirmed: 'bg-blue-100 text-blue-700',
                attended: 'bg-green-100 text-green-700',
                absent: 'bg-red-100 text-red-700',
                excused: 'bg-yellow-100 text-yellow-700',
            };
            return map[status] ?? 'bg-gray-100 text-gray-700';
        },
    }
}
</script>
