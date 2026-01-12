<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
      <div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-50">
          Substitute Call Lists
        </h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
          Organize substitutes by instrument with call priority
        </p>
      </div>
      <button
        @click="showAddInstrumentModal = true"
        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium transition-colors"
      >
        <i class="pi pi-plus mr-2" />
        Add Instrument
      </button>
    </div>

    <!-- Call Lists by Instrument -->
    <div
      v-if="Object.keys(callListsByInstrument).length === 0"
      class="text-center py-12 bg-gray-50 dark:bg-slate-800 rounded-lg"
    >
      <i class="pi pi-users text-4xl text-gray-400 dark:text-gray-600 mb-4" />
      <p class="text-gray-600 dark:text-gray-400">
        No call lists configured yet. Add an instrument to get started.
      </p>
    </div>

    <div
      v-else
      class="space-y-6"
    >
      <div
        v-for="(subs, instrument) in callListsByInstrument"
        :key="instrument"
        class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-6"
      >
        <!-- Instrument Header -->
        <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-50">
            {{ instrument }}
          </h3>
          <button
            @click="openAddSubModal(instrument)"
            class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded text-sm font-medium transition-colors"
          >
            <i class="pi pi-plus mr-1" />
            Add Sub
          </button>
        </div>

        <!-- Substitute List -->
        <div
          v-if="subs.length === 0"
          class="text-center py-8 text-gray-500 dark:text-gray-400"
        >
          No substitutes in call list for {{ instrument }}
        </div>

        <draggable
          v-else
          :list="subs"
          item-key="id"
          @end="onReorder(instrument, subs)"
          handle=".drag-handle"
          class="space-y-2"
        >
          <template #item="{ element, index }">
            <div
              class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-700 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors"
            >
              <!-- Drag Handle -->
              <div class="drag-handle cursor-move text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="pi pi-bars text-lg" />
              </div>

              <!-- Priority Badge -->
              <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 flex items-center justify-center font-semibold text-sm">
                {{ index + 1 }}
              </div>

              <!-- Member Info -->
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <div class="font-medium text-gray-900 dark:text-gray-50">
                    {{ element.roster_member_id ? element.roster_member.display_name : element.custom_name }}
                  </div>
                  <span
                    v-if="!element.roster_member_id"
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200"
                  >
                    Custom
                  </span>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                  <template v-if="element.roster_member_id && element.roster_member">
                    <span v-if="element.roster_member.display_email">{{ element.roster_member.display_email }}</span>
                    <span v-if="element.roster_member.phone" :class="{ 'ml-2': element.roster_member.display_email }">{{ element.roster_member.phone }}</span>
                  </template>
                  <template v-else>
                    <span v-if="element.custom_email">{{ element.custom_email }}</span>
                    <span v-if="element.custom_phone" :class="{ 'ml-2': element.custom_email }">{{ element.custom_phone }}</span>
                  </template>
                </div>
                <div
                  v-if="element.notes"
                  class="text-sm text-gray-600 dark:text-gray-400"
                >
                  {{ element.notes }}
                </div>
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-2">
                <button
                  @click="openEditNotesModal(element)"
                  class="p-2 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                  title="Edit notes"
                >
                  <i class="pi pi-pencil" />
                </button>
                <button
                  @click="removeFromCallList(element.id)"
                  class="p-2 text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors"
                  title="Remove from call list"
                >
                  <i class="pi pi-trash" />
                </button>
              </div>
            </div>
          </template>
        </draggable>
      </div>
    </div>

    <!-- Add Instrument Modal -->
    <Dialog
      v-model:visible="showAddInstrumentModal"
      modal
      :closable="true"
      header="Add Instrument/Role"
      class="w-full max-w-md"
    >
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Instrument/Role Name
          </label>
          <input
            v-model="newInstrument"
            type="text"
            placeholder="e.g., Guitar, Drums, Oboe"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-50"
            @keyup.enter="addInstrument"
          >
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <button
            @click="showAddInstrumentModal = false"
            class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md transition-colors"
          >
            Cancel
          </button>
          <button
            @click="addInstrument"
            :disabled="!newInstrument"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-md transition-colors"
          >
            Add
          </button>
        </div>
      </template>
    </Dialog>

    <!-- Add Sub Modal -->
    <Dialog
      v-model:visible="showAddSubModal"
      modal
      :closable="true"
      :header="`Add Substitute for ${selectedInstrument}`"
      class="w-full max-w-md"
    >
      <div class="space-y-4">
        <!-- Toggle between Roster Member and Custom -->
        <div class="flex gap-2 p-1 bg-gray-100 dark:bg-slate-700 rounded-lg">
          <button
            @click="newSub.is_custom = false"
            :class="[
              'flex-1 px-3 py-2 text-sm font-medium rounded-md transition-colors',
              !newSub.is_custom
                ? 'bg-white dark:bg-slate-600 text-gray-900 dark:text-white shadow'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
            ]"
          >
            From Roster
          </button>
          <button
            @click="newSub.is_custom = true"
            :class="[
              'flex-1 px-3 py-2 text-sm font-medium rounded-md transition-colors',
              newSub.is_custom
                ? 'bg-white dark:bg-slate-600 text-gray-900 dark:text-white shadow'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
            ]"
          >
            Custom Player
          </button>
        </div>

        <!-- Roster Member Selection -->
        <div v-if="!newSub.is_custom">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Select Roster Member
          </label>
          <select
            v-model="newSub.roster_member_id"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-50"
          >
            <option :value="null">
              Select a roster member...
            </option>
            <option
              v-for="member in availableRosterMembers"
              :key="member.id"
              :value="member.id"
            >
              {{ member.display_name }} {{ member.role ? `(${member.role})` : '' }}
            </option>
          </select>
        </div>

        <!-- Custom Player Fields -->
        <div v-else class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Name <span class="text-red-500">*</span>
            </label>
            <input
              v-model="newSub.custom_name"
              type="text"
              placeholder="Full name"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-50"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Email (optional)
            </label>
            <input
              v-model="newSub.custom_email"
              type="email"
              placeholder="email@example.com"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-50"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Phone (optional)
            </label>
            <input
              v-model="newSub.custom_phone"
              type="tel"
              placeholder="(555) 123-4567"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-50"
            />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Notes (optional)
          </label>
          <textarea
            v-model="newSub.notes"
            placeholder="e.g., Only available weekends, No travel gigs"
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-50"
          />
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <button
            @click="showAddSubModal = false"
            class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md transition-colors"
          >
            Cancel
          </button>
          <button
            @click="addSubToCallList"
            :disabled="!newSub.is_custom ? !newSub.roster_member_id : !newSub.custom_name"
            class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white rounded-md transition-colors"
          >
            Add to Call List
          </button>
        </div>
      </template>
    </Dialog>

    <!-- Edit Notes Modal -->
    <Dialog
      v-model:visible="showEditNotesModal"
      modal
      :closable="true"
      header="Edit Notes"
      class="w-full max-w-md"
    >
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Notes
          </label>
          <textarea
            v-model="editingEntry.notes"
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-gray-50"
          />
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <button
            @click="showEditNotesModal = false"
            class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md transition-colors"
          >
            Cancel
          </button>
          <button
            @click="updateNotes"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors"
          >
            Save
          </button>
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Dialog from 'primevue/dialog';
import draggable from 'vuedraggable';

const props = defineProps({
  band: {
    type: Object,
    required: true,
  },
  rosterMembers: {
    type: Array,
    required: true,
  },
});

const callLists = ref([]);
const showAddInstrumentModal = ref(false);
const showAddSubModal = ref(false);
const showEditNotesModal = ref(false);
const newInstrument = ref('');
const selectedInstrument = ref('');
const newSub = ref({
  roster_member_id: null,
  custom_name: '',
  custom_email: '',
  custom_phone: '',
  notes: '',
  is_custom: false,
});
const editingEntry = ref({
  id: null,
  notes: '',
});

// Group call lists by instrument
const callListsByInstrument = computed(() => {
  const grouped = {};
  callLists.value.forEach(entry => {
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

// Filter out roster members already in the call list for the selected instrument
const availableRosterMembers = computed(() => {
  if (!selectedInstrument.value) return props.rosterMembers;

  const existingIds = (callListsByInstrument.value[selectedInstrument.value] || [])
    .map(entry => entry.roster_member_id);

  return props.rosterMembers.filter(member => !existingIds.includes(member.id));
});

const loadCallLists = async () => {
  try {
    const response = await axios.get(route('bands.substitute-call-lists.index', props.band.id));
    callLists.value = response.data.call_lists ? Object.values(response.data.call_lists).flat() : [];
  } catch (error) {
    console.error('Failed to load call lists:', error);
  }
};

const addInstrument = () => {
  if (!newInstrument.value.trim()) return;

  selectedInstrument.value = newInstrument.value.trim();
  newInstrument.value = '';
  showAddInstrumentModal.value = false;
  showAddSubModal.value = true;
};

const openAddSubModal = (instrument) => {
  selectedInstrument.value = instrument;
  newSub.value = {
    roster_member_id: null,
    custom_name: '',
    custom_email: '',
    custom_phone: '',
    notes: '',
    is_custom: false,
  };
  showAddSubModal.value = true;
};

const addSubToCallList = async () => {
  // Validate required fields
  if (!newSub.value.is_custom && !newSub.value.roster_member_id) return;
  if (newSub.value.is_custom && !newSub.value.custom_name) return;

  try {
    const payload = {
      instrument: selectedInstrument.value,
      notes: newSub.value.notes,
    };

    // Add either roster member or custom player fields
    if (newSub.value.is_custom) {
      payload.custom_name = newSub.value.custom_name;
      payload.custom_email = newSub.value.custom_email;
      payload.custom_phone = newSub.value.custom_phone;
    } else {
      payload.roster_member_id = newSub.value.roster_member_id;
    }

    await axios.post(route('bands.substitute-call-lists.store', props.band.id), payload);

    await loadCallLists();
    showAddSubModal.value = false;
    newSub.value = {
      roster_member_id: null,
      custom_name: '',
      custom_email: '',
      custom_phone: '',
      notes: '',
      is_custom: false,
    };
  } catch (error) {
    console.error('Failed to add sub to call list:', error);
    alert('Failed to add substitute to call list');
  }
};

const openEditNotesModal = (entry) => {
  editingEntry.value = {
    id: entry.id,
    notes: entry.notes || '',
  };
  showEditNotesModal.value = true;
};

const updateNotes = async () => {
  try {
    await axios.patch(route('substitute-call-lists.update', editingEntry.value.id), {
      notes: editingEntry.value.notes,
    });

    await loadCallLists();
    showEditNotesModal.value = false;
  } catch (error) {
    console.error('Failed to update notes:', error);
    alert('Failed to update notes');
  }
};

const removeFromCallList = async (id) => {
  if (!confirm('Remove this substitute from the call list?')) return;

  try {
    await axios.delete(route('substitute-call-lists.destroy', id));
    await loadCallLists();
  } catch (error) {
    console.error('Failed to remove from call list:', error);
    alert('Failed to remove substitute from call list');
  }
};

const onReorder = async (instrument, subs) => {
  try {
    const order = subs.map(sub => sub.id);
    await axios.post(route('bands.substitute-call-lists.reorder', props.band.id), {
      instrument,
      order,
    });

    await loadCallLists();
  } catch (error) {
    console.error('Failed to reorder call list:', error);
    alert('Failed to reorder call list');
  }
};

onMounted(() => {
  loadCallLists();
});
</script>
