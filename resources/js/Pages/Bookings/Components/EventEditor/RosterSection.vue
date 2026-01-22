<template>
  <div class="mt-4 space-y-6">
    <!-- Roster Selection -->
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Roster Template
      </label>
      <select
        :value="props.modelValue.roster_id || ''"
        @change="handleRosterChange"
        class="w-full px-3 py-2 border-2 border-gray-300 dark:border-gray-600 rounded-lg
               focus:ring-2 focus:ring-blue-500 focus:border-blue-500
               dark:bg-slate-800 dark:text-gray-50"
      >
        <option value="">No Roster (Custom Lineup)</option>
        <option
          v-for="roster in rosters"
          :key="roster.id"
          :value="roster.id"
        >
          {{ roster.name }}
          <span v-if="roster.is_default">(Default)</span>
          - {{ roster.members_count || 0 }} members
        </option>
      </select>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Changing the roster will replace the current lineup with the roster template
      </p>
    </div>

    <!-- Event Members List -->
    <div>
      <div class="flex items-center justify-between mb-3">
        <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
          Event Lineup ({{ eventMembers.length }})
        </h4>
        <button
          @click="openAddMemberModal"
          class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-1"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Add Sub
        </button>
      </div>

      <!-- Empty State -->
      <div
        v-if="eventMembers.length === 0"
        class="text-center py-8 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg"
      >
        <p class="text-gray-500 dark:text-gray-400">
          No members assigned to this event yet
        </p>
        <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
          Select a roster or add subs manually
        </p>
      </div>

      <!-- Members List -->
      <div v-else class="space-y-2">
        <div
          v-for="member in eventMembers"
          :key="member.id"
          class="flex items-center justify-between p-3 bg-white dark:bg-slate-700 border border-gray-200 dark:border-gray-600 rounded-lg"
        >
          <div class="flex-1">
            <div class="flex items-center gap-2">
              <h5 class="font-semibold text-gray-900 dark:text-white">
                {{ member.display_name }}
              </h5>
              <PlayerTypeFlag :label="member.roster_member_id ? 'From Roster' : 'Sub'" />
            </div>
            <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
              <span v-if="member.role">{{ member.role }}</span>
              <span v-if="member.email" class="ml-2">• {{ member.email }}</span>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <!-- Attendance Status -->
            <select
              :value="member.attendance_status"
              @change="updateAttendance(member.id, $event.target.value)"
              :class="[
                'px-2 py-1 text-sm rounded border',
                member.attendance_status === 'confirmed' ? 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-900 dark:text-blue-200' :
                member.attendance_status === 'attended' ? 'bg-green-100 text-green-800 border-green-300 dark:bg-green-900 dark:text-green-200' :
                member.attendance_status === 'absent' ? 'bg-red-100 text-red-800 border-red-300 dark:bg-red-900 dark:text-red-200' :
                member.attendance_status === 'excused' ? 'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-900 dark:text-yellow-200' :
                'bg-gray-100 text-gray-800 border-gray-300 dark:bg-gray-700 dark:text-gray-200'
              ]"
            >
              <option value="confirmed">Confirmed</option>
              <option value="attended">Attended</option>
              <option value="absent">Absent</option>
              <option value="excused">Excused</option>
            </select>

            <!-- Remove Button -->
            <button
              @click="removeMember(member.id)"
              class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
              title="Remove from event"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Member Modal -->
    <teleport to="body">
      <transition name="fade">
        <div
          v-if="showAddMemberModal"
          class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50 flex items-center justify-center p-4"
          @click.self="showAddMemberModal = false"
        >
          <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-lg w-full p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                Add Substitute / Guest
              </h3>
              <button
                @click="showAddMemberModal = false"
                class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
              >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Tab Navigation -->
            <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
              <nav class="-mb-px flex space-x-8">
                <button
                  v-if="Object.keys(callListsByInstrument).length > 0"
                  @click="activeTab = 'subList'"
                  :class="[
                    'py-3 px-1 border-b-2 font-medium text-sm transition-colors',
                    activeTab === 'subList'
                      ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                  ]"
                >
                  Sub List
                </button>
                <button
                  @click="activeTab = 'existingMembers'"
                  :class="[
                    'py-3 px-1 border-b-2 font-medium text-sm transition-colors',
                    activeTab === 'existingMembers'
                      ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                  ]"
                >
                  Existing Members
                </button>
                <button
                  @click="activeTab = 'createNew'"
                  :class="[
                    'py-3 px-1 border-b-2 font-medium text-sm transition-colors',
                    activeTab === 'createNew'
                      ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                  ]"
                >
                  Create New
                </button>
              </nav>
            </div>

            <!-- Tab Content -->
            <div class="space-y-4">
              <!-- Sub List Tab -->
              <div v-if="activeTab === 'subList' && Object.keys(callListsByInstrument).length > 0">
                <div class="space-y-3 max-h-96 overflow-y-auto">
                  <div
                    v-for="(subs, instrument) in callListsByInstrument"
                    :key="instrument"
                    class="bg-gray-50 dark:bg-slate-700 rounded-lg p-3"
                  >
                    <h5 class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wide">
                      {{ instrument }}
                    </h5>
                    <div class="space-y-1">
                      <button
                        v-for="(sub, index) in subs"
                        :key="sub.id"
                        @click="addFromCallList(sub)"
                        class="w-full flex items-center justify-between p-2 bg-white dark:bg-slate-600 rounded hover:bg-blue-50 dark:hover:bg-slate-500 transition-colors text-left"
                      >
                        <div class="flex items-center gap-2 flex-1">
                          <span class="flex-shrink-0 w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 flex items-center justify-center text-xs font-semibold">
                            {{ index + 1 }}
                          </span>
                          <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1">
                              <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                {{ sub.roster_member_id && sub.roster_member ? sub.roster_member.display_name : sub.custom_name }}
                              </div>
                              <span
                                v-if="!sub.roster_member_id"
                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 flex-shrink-0"
                              >
                                Custom
                              </span>
                            </div>
                            <div v-if="sub.custom_email || sub.custom_phone" class="text-xs text-gray-600 dark:text-gray-400 truncate">
                              {{ sub.custom_email || sub.custom_phone }}
                            </div>
                            <div v-if="sub.notes" class="text-xs text-gray-600 dark:text-gray-400 truncate">
                              {{ sub.notes }}
                            </div>
                          </div>
                        </div>
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                  Click a substitute to add them to this event
                </p>
              </div>

              <!-- Existing Members Tab -->
              <div v-if="activeTab === 'existingMembers'">
                <select
                  v-model="newMember.user_id"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white"
                  @change="handleBandMemberSelect"
                >
                  <option value="">-- Select a band member --</option>
                  <option
                    v-for="bandMember in availableBandMembers"
                    :key="bandMember.id"
                    :value="bandMember.id"
                  >
                    {{ bandMember.name }}
                  </option>
                </select>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                  Select from members already in your band
                </p>
              </div>

              <!-- Create New Tab -->
              <div v-if="activeTab === 'createNew'" class="space-y-4">
                <!-- Custom Name -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Name <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="newMember.name"
                    type="text"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white"
                    placeholder="Full name"
                    :disabled="!!newMember.roster_member_id || !!newMember.user_id"
                  />
                </div>

                <!-- Role -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Role / Instrument
                  </label>
                  <select
                    v-model="newMember.band_role_id"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white"
                    :disabled="!!newMember.roster_member_id || !!newMember.user_id"
                  >
                    <option value="">Select a role...</option>
                    <option
                      v-for="role in bandRoles"
                      :key="role.id"
                      :value="role.id"
                    >
                      {{ role.name }}
                    </option>
                  </select>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Roles can be managed in your <a :href="route('bands.roles.page', bandId)" target="_blank" class="text-blue-600 hover:underline">band settings</a>
                  </p>
                </div>

                <!-- Email -->
                <div v-if="!newMember.roster_member_id && !newMember.user_id">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Email
                  </label>
                  <input
                    v-model="newMember.email"
                    type="email"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white"
                    placeholder="email@example.com"
                  />
                </div>

                <!-- Phone -->
                <div v-if="!newMember.roster_member_id && !newMember.user_id">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Phone
                  </label>
                  <input
                    v-model="newMember.phone"
                    type="tel"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white"
                    placeholder="(555) 123-4567"
                  />
                </div>

                <!-- Invite Substitute Checkbox -->
                <div v-if="!newMember.roster_member_id && !newMember.user_id && newMember.email" class="flex items-center gap-2">
                  <input
                    v-model="newMember.invite_substitute"
                    type="checkbox"
                    id="invite-sub"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                  <label for="invite-sub" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    Send invitation to create account and join as substitute
                  </label>
                </div>
              </div>

              <!-- Action Buttons -->
              <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <button
                  @click="showAddMemberModal = false"
                  class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700"
                >
                  Cancel
                </button>
                <button
                  @click="addMember"
                  :disabled="!newMember.name"
                  class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Add to Event
                </button>
              </div>
            </div>
          </div>
        </div>
      </transition>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import PlayerTypeFlag from './PlayerTypeFlag.vue';

const props = defineProps({
  modelValue: {
    type: Object,
    required: true,
  },
  bandId: {
    type: Number,
    required: true,
  },
});

const emit = defineEmits(['update:modelValue']);

const rosters = ref([]);
const eventMembers = ref([]);
const allRosterMembers = ref([]);
const allBandMembers = ref([]);
const callLists = ref([]);
const bandRoles = ref([]);
const showAddMemberModal = ref(false);
const activeTab = ref('subList');
const newMember = ref({
  roster_member_id: '',
  user_id: '',
  name: '',
  band_role_id: '',
  email: '',
  phone: '',
  invite_substitute: false,
});

const availableBandMembers = computed(() => {
  const currentUserIds = eventMembers.value
    .filter(m => m.user_id)
    .map(m => m.user_id);

  return allBandMembers.value.filter(bm => !currentUserIds.includes(bm.id));
});

const callListsByInstrument = computed(() => {
  const grouped = {};
  const currentRosterMemberIds = eventMembers.value
    .filter(m => m.roster_member_id)
    .map(m => m.roster_member_id);

  callLists.value.forEach(entry => {
    // Skip if already added to event
    if (currentRosterMemberIds.includes(entry.roster_member_id)) {
      return;
    }

    if (!grouped[entry.instrument]) {
      grouped[entry.instrument] = [];
    }
    grouped[entry.instrument].push(entry);
  });

  // Sort by priority within each instrument
  Object.keys(grouped).forEach(instrument => {
    grouped[instrument].sort((a, b) => a.priority - b.priority);
  });

  return grouped;
});

onMounted(() => {
  loadRosters();
  loadEventMembers();
  loadCallLists();
  loadBandMembers();
  loadBandRoles();
  if (props.modelValue.roster_id) {
    loadRosterMembers(props.modelValue.roster_id);
  }
});

const loadRosters = async () => {
  if (!props.bandId) {
    console.warn('No band ID provided to load rosters');
    return;
  }

  try {
    const response = await axios.get(route('bands.rosters.index', props.bandId), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    rosters.value = response.data.rosters || [];

    // Auto-select default roster for new events (that don't have an ID yet)
    if (!props.modelValue.id && !props.modelValue.roster_id) {
      const defaultRoster = rosters.value.find(r => r.is_default);
      if (defaultRoster) {
        const updatedEvent = { ...props.modelValue, roster_id: defaultRoster.id };
        emit('update:modelValue', updatedEvent);
      }
    }
  } catch (error) {
    console.error('Failed to load rosters:', error);
  }
};

const loadCallLists = async () => {
  if (!props.bandId) return;

  try {
    const response = await axios.get(route('bands.substitute-call-lists.index', props.bandId));
    callLists.value = response.data.call_lists ? Object.values(response.data.call_lists).flat() : [];
  } catch (error) {
    console.error('Failed to load call lists:', error);
  }
};

const loadEventMembers = async () => {
  if (!props.modelValue.id) return;

  try {
    const response = await axios.get(`/events/${props.modelValue.id}/members`);
    eventMembers.value = response.data.members || [];
  } catch (error) {
    console.error('Failed to load event members:', error);
  }
};

const loadRosterMembers = async (rosterId) => {
  try {
    const response = await axios.get(`/rosters/${rosterId}`);
    allRosterMembers.value = response.data.members || [];
  } catch (error) {
    console.error('Failed to load roster members:', error);
  }
};

const loadBandMembers = async () => {
  if (!props.bandId) return;

  try {
    const response = await axios.get(`/api/bands/${props.bandId}/members`);
    allBandMembers.value = response.data.members || [];
  } catch (error) {
    console.error('Failed to load band members:', error);
  }
};

const loadBandRoles = async () => {
  if (!props.bandId) return;

  try {
    const response = await axios.get(route('bands.roles.index', props.bandId));
    bandRoles.value = response.data.roles || [];
  } catch (error) {
    console.error('Failed to load band roles:', error);
  }
};

const handleRosterChange = async (event) => {
  const rosterId = event.target.value ? parseInt(event.target.value) : null;

  if (rosterId && props.modelValue.id) {
    // Confirm before replacing lineup
    if (eventMembers.value.length > 0) {
      if (!confirm('This will replace the current lineup with the roster template. Continue?')) {
        // Reset select to current value
        event.target.value = props.modelValue.roster_id || '';
        return;
      }
    }
  }

  // Update the event model for autosave
  const updatedEvent = { ...props.modelValue, roster_id: rosterId };
  emit('update:modelValue', updatedEvent);

  if (rosterId) {
    loadRosterMembers(rosterId);
  }

  // Immediately save the roster change to backend and sync members
  if (props.modelValue.id) {
    try {
      await axios.patch(`/events/${props.modelValue.id}/roster`, {
        roster_id: rosterId
      });

      // Reload event members after sync completes
      await loadEventMembers();
    } catch (error) {
      console.error('Failed to update roster:', error);
      alert('Failed to apply roster template. Please try again.');
    }
  }
};

const openAddMemberModal = () => {
  // Set initial tab based on whether there are call lists
  if (Object.keys(callListsByInstrument.value).length > 0) {
    activeTab.value = 'subList';
  } else {
    activeTab.value = 'existingMembers';
  }
  showAddMemberModal.value = true;
};

const handleBandMemberSelect = () => {
  if (newMember.value.user_id) {
    const bandMember = allBandMembers.value.find(
      bm => bm.id === parseInt(newMember.value.user_id)
    );
    if (bandMember) {
      newMember.value.name = bandMember.name;
      newMember.value.email = bandMember.email || '';
      newMember.value.roster_member_id = ''; // Clear roster selection
    }
  } else {
    // Clear fields when deselecting
    newMember.value.name = '';
    newMember.value.email = '';
  }
};

const addMember = async () => {
  if (!props.modelValue.id || !newMember.value.name) return;

  try {
    const response = await axios.post(`/events/${props.modelValue.id}/members`, {
      roster_member_id: newMember.value.roster_member_id || null,
      user_id: newMember.value.user_id || null,
      name: newMember.value.name,
      band_role_id: newMember.value.band_role_id || null,
      email: newMember.value.email,
      phone: newMember.value.phone,
      attendance_status: 'confirmed',
      invite_substitute: newMember.value.invite_substitute,
    });

    if (response.data.invited) {
      alert(`Invitation sent to ${newMember.value.email}! They'll be added to the band as a substitute once they create an account.`);
    }

    await loadEventMembers();
    showAddMemberModal.value = false;

    // Reset form
    newMember.value = {
      roster_member_id: '',
      user_id: '',
      name: '',
      band_role_id: '',
      email: '',
      phone: '',
      invite_substitute: false,
    };
  } catch (error) {
    console.error('Failed to add member:', error);
    alert('Failed to add member to event');
  }
};

const updateAttendance = async (memberId, status) => {
  try {
    await axios.patch(`/event-members/${memberId}`, {
      attendance_status: status,
    });
    await loadEventMembers();
  } catch (error) {
    console.error('Failed to update attendance:', error);
    alert('Failed to update attendance status');
  }
};

const removeMember = async (memberId) => {
  if (!confirm('Remove this member from the event?')) return;

  try {
    await axios.delete(`/event-members/${memberId}`);
    await loadEventMembers();
  } catch (error) {
    console.error('Failed to remove member:', error);
    alert('Failed to remove member from event');
  }
};

const addFromCallList = async (callListEntry) => {
  if (!props.modelValue.id) return;

  try {
    const payload = {
      attendance_status: 'confirmed',
    };

    // Add either roster member or custom player fields
    if (callListEntry.roster_member_id) {
      payload.roster_member_id = callListEntry.roster_member_id;
    } else {
      // Custom player from call list - invite them!
      payload.name = callListEntry.custom_name;
      payload.email = callListEntry.custom_email;
      payload.phone = callListEntry.custom_phone;
      payload.band_role_id = callListEntry.band_role_id || null;
      payload.invite_substitute = true; // Auto-invite custom players from call list
    }

    const response = await axios.post(`/events/${props.modelValue.id}/members`, payload);

    if (response.data.invited) {
      alert(`Invitation sent to ${callListEntry.custom_email}! They'll be added to the band as a substitute once they create an account.`);
    }

    await loadEventMembers();
  } catch (error) {
    console.error('Failed to add member from call list:', error);
    alert('Failed to add substitute to event');
  }
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
