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
    <div v-else-if="allMembers.length === 0" class="text-gray-500 text-sm text-center py-4">
      No roster assigned to this event.
    </div>
    <div v-else class="space-y-1">
      <!-- Role groups -->
      <template v-for="group in lineupByRole" :key="group.roleName || '__none__'">
        <div v-if="group.roleName" class="pt-3 pb-1">
          <span class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ group.roleName }}</span>
        </div>
        <template v-for="slot in group.slots" :key="slot.slotId">
          <div class="mb-2">
            <div class="mb-1 pl-1">
              <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">{{ slot.slotName }}</span>
            </div>
            <ul class="space-y-1.5">
              <li
                v-for="(member, i) in slot.seats"
                :key="member ? member.id : `empty-${slot.slotId}-${i}`"
                class="pl-2"
              >
                <!-- Filled seat -->
                <div v-if="member" class="flex items-center justify-between gap-2">
                  <button
                    class="text-sm font-medium text-left hover:text-blue-600 dark:hover:text-blue-400 flex items-center gap-1.5"
                    @click="expandedMemberId = expandedMemberId === member.id ? null : member.id"
                  >
                    {{ member.display_name }}
                    <span v-if="!member.roster_member_id" class="text-xs px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-200">Sub</span>
                  </button>
                  <div class="flex items-center gap-1.5 flex-shrink-0">
                    <template v-if="expandedMemberId === member.id">
                      <select
                        v-if="rosterSlots.length > 0"
                        :value="member.slot_id || ''"
                        @change="updateMember(member.id, { slot_id: $event.target.value ? parseInt($event.target.value) : null })"
                        class="text-xs px-2 py-0.5 rounded-full border-0 cursor-pointer bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                      >
                        <option value="">No instrument</option>
                        <option v-for="s in rosterSlots" :key="s.id" :value="s.id">
                          {{ s.band_role?.name ? `${s.band_role.name} — ` : '' }}{{ s.name }}
                        </option>
                      </select>
                      <select
                        :value="member.attendance_status"
                        @change="updateMember(member.id, { attendance_status: $event.target.value })"
                        :class="statusClass(member.attendance_status)"
                        class="text-xs px-2 py-0.5 rounded-full border-0 cursor-pointer capitalize"
                      >
                        <option value="confirmed">Confirmed</option>
                        <option value="attended">Attended</option>
                        <option value="absent">Absent</option>
                        <option value="excused">Excused</option>
                      </select>
                      <button
                        @click="removeMember(member.id)"
                        title="Remove from event"
                        class="p-0.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </template>
                    <span
                      v-else
                      :class="statusClass(member.attendance_status)"
                      class="text-xs px-2 py-0.5 rounded-full capitalize"
                    >{{ member.attendance_status }}</span>
                  </div>
                </div>
                <!-- Empty seat -->
                <div v-else>
                  <button
                    @click="openSubPicker(slot)"
                    :class="slot.isRequired ? 'text-red-500 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300' : 'text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300'"
                    class="text-xs italic flex items-center gap-1"
                  >
                    {{ slot.isRequired ? 'Needs filling' : 'Empty' }}
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                  </button>
                </div>
              </li>
            </ul>
          </div>
        </template>
      </template>

      <!-- Unslotted members -->
      <template v-if="unslottedMembers.length > 0">
        <div class="pt-3 pb-1">
          <span class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Unassigned</span>
        </div>
        <ul class="space-y-2">
          <li
            v-for="member in unslottedMembers"
            :key="member.id"
            class="pl-2"
          >
            <div class="flex items-center justify-between gap-2">
              <button
                class="text-sm font-medium text-left hover:text-blue-600 dark:hover:text-blue-400 flex items-center gap-1.5"
                @click="expandedMemberId = expandedMemberId === member.id ? null : member.id"
              >
                {{ member.display_name }}
                <span v-if="member.role" class="text-xs text-gray-500 dark:text-gray-400">{{ member.role }}</span>
                <span v-if="!member.roster_member_id" class="text-xs px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-200">Sub</span>
              </button>
              <div class="flex items-center gap-1.5 flex-shrink-0">
                <select
                  v-if="rosterSlots.length > 0"
                  :value="member.slot_id || ''"
                  @change="updateMember(member.id, { slot_id: $event.target.value ? parseInt($event.target.value) : null })"
                  class="text-xs px-2 py-0.5 rounded-full border-0 cursor-pointer bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400"
                >
                  <option value="">Unassigned</option>
                  <option v-for="s in rosterSlots" :key="s.id" :value="s.id">
                    {{ s.band_role?.name ? `${s.band_role.name} — ` : '' }}{{ s.name }}
                  </option>
                </select>
                <template v-if="expandedMemberId === member.id">
                  <select
                    :value="member.attendance_status"
                    @change="updateMember(member.id, { attendance_status: $event.target.value })"
                    :class="statusClass(member.attendance_status)"
                    class="text-xs px-2 py-0.5 rounded-full border-0 cursor-pointer capitalize"
                  >
                    <option value="confirmed">Confirmed</option>
                    <option value="attended">Attended</option>
                    <option value="absent">Absent</option>
                    <option value="excused">Excused</option>
                  </select>
                  <button
                    @click="removeMember(member.id)"
                    title="Remove from event"
                    class="p-0.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </template>
                <span
                  v-else
                  :class="statusClass(member.attendance_status)"
                  class="text-xs px-2 py-0.5 rounded-full capitalize"
                >{{ member.attendance_status }}</span>
              </div>
            </div>
          </li>
        </ul>
      </template>
    </div>
  </Dialog>

  <!-- Sub Picker Dialog -->
  <Dialog
    v-model:visible="showSubPicker"
    modal
    :header="`Add Sub — ${subPickerSlot?.slotName ?? ''}`"
    :style="{ width: '420px' }"
  >
    <div v-if="subPickerOptions.length === 0" class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
      No available members or subs.
    </div>
    <ul v-else class="space-y-1 max-h-96 overflow-y-auto">
      <li v-for="option in subPickerOptions" :key="option.id">
        <button
          @click="addSubToSlot(option)"
          class="w-full flex items-center gap-3 p-2 rounded-lg hover:bg-blue-50 dark:hover:bg-slate-700 text-left transition-colors"
        >
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5">
              <span class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ option.name }}</span>
              <span v-if="option._type === 'sub'" class="text-xs px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-200">Sub</span>
            </div>
            <div v-if="option.email" class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ option.email }}</div>
            <div v-if="option.role" class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ option.role }}</div>
          </div>
          <svg class="w-4 h-4 text-green-600 dark:text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
        </button>
      </li>
    </ul>
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
            allMembers: [],
            rosterSlots: [],
            rosterMembers: [],
            callLists: [],
            rosterError: null,
            expandedMemberId: null,
            showSubPicker: false,
            subPickerSlot: null,
        };
    },
    computed: {
        lineupByRole() {
            const roleGroups = {};
            const roleOrder = [];

            // Build structure from slots (source of truth), so empty slots appear
            this.rosterSlots.forEach(slot => {
                const roleKey = slot.band_role?.name || '__none__';
                if (!roleGroups[roleKey]) {
                    roleGroups[roleKey] = { roleName: slot.band_role?.name || null, slots: [] };
                    roleOrder.push(roleKey);
                }
                const members = this.allMembers.filter(m => m.slot_id === slot.id);
                const seats = [];
                for (let i = 0; i < slot.quantity; i++) {
                    seats.push(members[i] || null);
                }
                // Overfill beyond quantity
                members.slice(slot.quantity).forEach(m => seats.push(m));
                roleGroups[roleKey].slots.push({ slotId: slot.id, slotName: slot.name, isRequired: slot.is_required, quantity: slot.quantity, bandRoleId: slot.band_role_id ?? null, seats });
            });

            return roleOrder.map(key => roleGroups[key]);
        },
        unslottedMembers() {
            return this.allMembers.filter(m => !m.slot_id);
        },
        subPickerOptions() {
            const currentRosterMemberIds = new Set(
                this.allMembers.filter(m => m.roster_member_id).map(m => m.roster_member_id)
            );
            const currentUserIds = new Set(
                this.allMembers.filter(m => m.user_id).map(m => m.user_id)
            );

            // Roster members not already in the event
            const members = this.rosterMembers
                .filter(m => m.is_active && !currentRosterMemberIds.has(m.id))
                .map(m => ({ _type: 'member', id: `m-${m.id}`, rosterId: m.id, name: m.name, email: m.email, role: m.role }));

            // Call list subs not already in the event
            const subs = this.callLists
                .filter(s => !currentRosterMemberIds.has(s.roster_member_id))
                .map(s => ({
                    _type: 'sub',
                    id: `s-${s.id}`,
                    callListId: s.id,
                    rosterId: s.roster_member_id,
                    name: s.roster_member_id && s.roster_member ? s.roster_member.display_name : s.custom_name,
                    email: s.custom_email || s.roster_member?.display_email,
                    priority: s.priority,
                    band_role_id: s.band_role_id,
                    roster_member_id: s.roster_member_id,
                    custom_name: s.custom_name,
                    custom_email: s.custom_email,
                    custom_phone: s.custom_phone,
                }));

            // Deduplicate: if a roster member is also on the call list, show them only in subs (with priority)
            const subRosterIds = new Set(subs.filter(s => s.rosterId).map(s => s.rosterId));
            const filteredMembers = members.filter(m => !subRosterIds.has(m.rosterId));

            return [
                ...subs.sort((a, b) => (a.priority ?? 999) - (b.priority ?? 999)),
                ...filteredMembers,
            ];
        },
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
        unfilledRequiredSlotsCount() {
            return this.event.unfilled_required_slots_count ?? 0;
        },
        rosterLabel() {
            if (this.unfilledRequiredSlotsCount > 0) {
                const n = this.unfilledRequiredSlotsCount;
                return `Roster (${n} instrument${n === 1 ? '' : 's'} needed)`;
            }
            if (this.rosterCount === 0 && this.subCount === 0) return 'Roster';
            if (this.subCount > 0) return `Roster (${this.rosterCount}+${this.subCount} sub${this.subCount === 1 ? '' : 's'})`;
            return `Roster (${this.rosterCount})`;
        },
        rosterColor() {
            if (this.unfilledRequiredSlotsCount > 0) {
                return 'text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200';
            }
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
            if (this.allMembers.length > 0 && (this.rosterSlots.length > 0 || !this.event.roster_id)) return;

            const eventId = this.event.id;
            if (!eventId) {
                this.rosterError = 'Event ID not available.';
                return;
            }

            this.loadingRoster = true;
            this.rosterError = null;

            try {
                const membersUrl = this.route('events.members.index', { event: eventId });
                const membersResp = await fetch(membersUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!membersResp.ok) throw new Error('Failed to load roster.');
                const data = await membersResp.json();
                this.allMembers = data.members ?? [];

                if (this.event.roster_id) {
                    const slotsUrl = this.route('rosters.slots.index', { roster: this.event.roster_id });
                    const slotsResp = await fetch(slotsUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (slotsResp.ok) {
                        const slotsData = await slotsResp.json();
                        this.rosterSlots = slotsData.slots ?? [];
                    }

                    const rosterMembersUrl = this.route('rosters.show', { roster: this.event.roster_id });
                    const rosterMembersResp = await fetch(rosterMembersUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (rosterMembersResp.ok) {
                        const rosterMembersData = await rosterMembersResp.json();
                        this.rosterMembers = rosterMembersData.members ?? [];
                    }
                }

                if (this.event.band_id) {
                    const callUrl = this.route('bands.substitute-call-lists.index', { band: this.event.band_id });
                    const callResp = await fetch(callUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (callResp.ok) {
                        const callData = await callResp.json();
                        this.callLists = callData.call_lists ? Object.values(callData.call_lists).flat() : [];
                    }
                }
            } catch (e) {
                this.rosterError = e.message || 'Could not load roster.';
            } finally {
                this.loadingRoster = false;
            }
        },
        async updateMember(memberId, patch) {
            const url = this.route('events.members.update', { eventMember: memberId });
            const response = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify(patch),
            });
            if (!response.ok) return;

            const index = this.allMembers.findIndex(m => m.id === memberId);
            if (index === -1) return;

            const updated = { ...this.allMembers[index] };
            if (patch.attendance_status !== undefined) updated.attendance_status = patch.attendance_status;
            if (patch.slot_id !== undefined) {
                updated.slot_id = patch.slot_id;
                const slot = this.rosterSlots.find(s => s.id == patch.slot_id);
                updated.slot_name = slot?.name ?? null;
                updated.slot_role_name = slot?.band_role?.name ?? null;
            }
            this.allMembers.splice(index, 1, updated);

            this.emitRosterCounts();
        },
        openSubPicker(slot) {
            this.subPickerSlot = slot;
            this.showSubPicker = true;
        },
        async addSubToSlot(option) {
            if (!this.event.id) return;
            const payload = {
                attendance_status: 'confirmed',
                slot_id: this.subPickerSlot?.slotId ?? null,
            };
            if (option._type === 'member') {
                payload.roster_member_id = option.rosterId;
            } else if (option.roster_member_id) {
                payload.roster_member_id = option.roster_member_id;
            } else {
                payload.name = option.custom_name;
                payload.email = option.custom_email;
                payload.phone = option.custom_phone;
                payload.band_role_id = option.band_role_id || null;
            }
            const response = await fetch(`/events/${this.event.id}/members`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify(payload),
            });
            if (!response.ok) return;
            this.showSubPicker = false;
            this.subPickerSlot = null;
            // Reload the full member list to get properly formatted data
            const membersResp = await fetch(this.route('events.members.index', { event: this.event.id }), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (membersResp.ok) {
                const data = await membersResp.json();
                this.allMembers = data.members ?? [];
            }
            this.emitRosterCounts();
        },
        async removeMember(memberId) {
            if (!confirm('Remove this member from the event?')) return;
            const url = this.route('events.members.destroy', { eventMember: memberId });
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                },
            });
            if (!response.ok) return;
            this.allMembers = this.allMembers.filter(m => m.id !== memberId);
            this.expandedMemberId = null;
            this.emitRosterCounts();
        },
        emitRosterCounts() {
            const active = this.allMembers.filter(m => !['absent', 'excused'].includes(m.attendance_status));
            const rosterCount = active.filter(m => m.roster_member_id).length;
            const subCount = this.allMembers.filter(m => !m.roster_member_id).length;
            const absentCount = this.allMembers.filter(m => m.roster_member_id && ['absent', 'excused'].includes(m.attendance_status)).length;

            const unfilledRequiredSlotsCount = this.rosterSlots.filter(slot => {
                if (!slot.is_required) return false;
                const filled = this.allMembers.filter(m =>
                    m.slot_id === slot.id && !['absent', 'excused'].includes(m.attendance_status)
                ).length;
                return filled < slot.quantity;
            }).length;

            this.$emit('roster-counts-updated', {
                eventId: this.event.id,
                roster_count: rosterCount,
                sub_count: subCount,
                absent_count: absentCount,
                unfilled_required_slots_count: unfilledRequiredSlotsCount,
            });
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
