<template>
  <transition name="fade">
    <div
      class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50 flex items-center justify-center p-4"
      @click.self="$emit('close')"
    >
      <transition name="slide-down">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
            <div>
              <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                Manage Roster Members
              </h2>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ roster.name }}
              </p>
            </div>
            <button
              @click="$emit('close')"
              class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto p-6 space-y-6">

            <!-- Slots Management Section -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg">
              <button
                @click="showSlotsSection = !showSlotsSection"
                class="w-full flex items-center justify-between p-4 text-left"
              >
                <div class="flex items-center gap-2">
                  <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                  </svg>
                  <span class="font-semibold text-gray-900 dark:text-white">
                    Instruments ({{ slots.length }})
                  </span>
                  <span v-if="unfilledRequiredCount > 0" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                    {{ unfilledRequiredCount }} unfilled
                  </span>
                </div>
                <svg
                  :class="['w-5 h-5 text-gray-400 transition-transform', showSlotsSection ? 'rotate-180' : '']"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <div v-if="showSlotsSection" class="border-t border-gray-200 dark:border-gray-700 p-4 space-y-3">
                <!-- Existing slots -->
                <div
                  v-for="slot in slots"
                  :key="slot.id"
                  class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700 rounded-lg"
                >
                  <div v-if="editingSlotId !== slot.id" class="flex items-center gap-3 flex-1">
                    <div>
                      <span class="font-medium text-gray-900 dark:text-white">{{ slot.name }}</span>
                      <span
                        :class="slot.is_required ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                        class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                      >
                        {{ slot.is_required ? 'Required' : 'Optional' }}
                      </span>
                    </div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                      {{ slot.member_count }}/{{ slot.quantity }}
                      <span v-if="slot.is_required && slot.member_count < slot.quantity" class="text-red-500 font-medium ml-1">needs {{ slot.quantity - slot.member_count }}</span>
                    </span>
                  </div>

                  <!-- Inline edit form -->
                  <div v-else class="flex-1 grid grid-cols-2 gap-2 mr-2">
                    <input
                      v-model="slotEditForm.name"
                      type="text"
                      class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm dark:bg-slate-600 dark:text-white"
                      placeholder="Instrument name"
                    />
                    <select
                      v-model="slotEditForm.band_role_id"
                      class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm dark:bg-slate-600 dark:text-white"
                    >
                      <option value="">No linked role</option>
                      <option v-for="role in bandRoles" :key="role.id" :value="role.id">{{ role.name }}</option>
                    </select>
                    <div class="flex items-center gap-3">
                      <label class="flex items-center gap-1 text-sm text-gray-700 dark:text-gray-300">
                        <input v-model="slotEditForm.is_required" type="checkbox" class="rounded" />
                        Required
                      </label>
                      <label class="text-sm text-gray-700 dark:text-gray-300">
                        Qty:
                        <input
                          v-model.number="slotEditForm.quantity"
                          type="number" min="1" max="99"
                          class="w-14 ml-1 px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm dark:bg-slate-600 dark:text-white"
                        />
                      </label>
                    </div>
                    <input
                      v-model="slotEditForm.notes"
                      type="text"
                      class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm dark:bg-slate-600 dark:text-white"
                      placeholder="Notes (optional)"
                    />
                  </div>

                  <div class="flex items-center gap-2 ml-2">
                    <template v-if="editingSlotId === slot.id">
                      <button @click="saveSlotEdit(slot)" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Save</button>
                      <button @click="editingSlotId = null" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </template>
                    <template v-else>
                      <button @click="startEditSlot(slot)" title="Edit instrument" class="p-1 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                      </button>
                      <button @click="deleteSlot(slot)" title="Delete instrument" class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </template>
                  </div>
                </div>

                <!-- Add slot form -->
                <div v-if="showAddSlotForm" class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg space-y-3">
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Name <span class="text-red-500">*</span></label>
                      <input
                        v-model="slotForm.name"
                        type="text"
                        class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-sm dark:bg-slate-700 dark:text-white"
                        placeholder="e.g. Drums, Keys, Production"
                      />
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Linked Role</label>
                      <select
                        v-model="slotForm.band_role_id"
                        class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-sm dark:bg-slate-700 dark:text-white"
                      >
                        <option value="">None</option>
                        <option v-for="role in bandRoles" :key="role.id" :value="role.id">{{ role.name }}</option>
                      </select>
                    </div>
                  </div>
                  <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                      <input v-model="slotForm.is_required" type="checkbox" class="rounded border-gray-300 text-blue-600" />
                      Required
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                      Quantity:
                      <input
                        v-model.number="slotForm.quantity"
                        type="number" min="1" max="99"
                        class="w-16 px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm dark:bg-slate-700 dark:text-white"
                      />
                    </label>
                  </div>
                  <input
                    v-model="slotForm.notes"
                    type="text"
                    class="w-full px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-sm dark:bg-slate-700 dark:text-white"
                    placeholder="Notes (optional)"
                  />
                  <div class="flex gap-2">
                    <button
                      @click="addSlot"
                      :disabled="!slotForm.name || slotProcessing"
                      class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 disabled:opacity-50"
                    >
                      Add Instrument
                    </button>
                    <button @click="showAddSlotForm = false; resetSlotForm()" class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900">
                      Cancel
                    </button>
                  </div>
                </div>

                <button
                  v-if="!showAddSlotForm"
                  @click="showAddSlotForm = true"
                  class="w-full px-3 py-2 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-500 dark:text-gray-400 hover:border-blue-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors text-sm flex items-center justify-center gap-1"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  Add Instrument
                </button>
              </div>
            </div>

            <!-- Add Member Section -->
            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                  Add Member
                </h3>
                <button
                  v-if="showAddForm"
                  @click="cancelAdd"
                  class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                >
                  Cancel
                </button>
              </div>

              <button
                v-if="!showAddForm"
                @click="showAddForm = true"
                class="w-full px-4 py-3 border-2 border-dashed border-blue-300 dark:border-blue-700 rounded-lg text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors flex items-center justify-center gap-2"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add New Member
              </button>

              <div v-else class="space-y-4">
                <!-- Member Type Toggle -->
                <div class="flex gap-2">
                  <button
                    @click="memberForm.type = 'user'"
                    :class="[
                      'flex-1 px-4 py-2 rounded-lg font-medium transition-colors',
                      memberForm.type === 'user'
                        ? 'bg-blue-600 text-white'
                        : 'bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-600'
                    ]"
                  >
                    Band Member
                  </button>
                  <button
                    @click="memberForm.type = 'non-user'"
                    :class="[
                      'flex-1 px-4 py-2 rounded-lg font-medium transition-colors',
                      memberForm.type === 'non-user'
                        ? 'bg-blue-600 text-white'
                        : 'bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-600'
                    ]"
                  >
                    Substitute / Guest
                  </button>
                </div>

                <!-- User Selection -->
                <div v-if="memberForm.type === 'user'">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Select Band Member <span class="text-red-500">*</span>
                  </label>
                  <select
                    v-model="memberForm.user_id"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white"
                    :class="{ 'border-red-500': memberErrors.user_id }"
                  >
                    <option value="">Choose a member...</option>
                    <option v-for="user in availableBandMembers" :key="user.id" :value="user.id">
                      {{ user.name }} ({{ user.email }})
                    </option>
                  </select>
                  <p v-if="memberErrors.user_id" class="mt-1 text-sm text-red-600">{{ memberErrors.user_id }}</p>
                </div>

                <!-- Non-User Fields -->
                <div v-else class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Name <span class="text-red-500">*</span>
                    </label>
                    <input
                      v-model="memberForm.name"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white"
                      placeholder="Full name"
                      :class="{ 'border-red-500': memberErrors.name }"
                    />
                    <p v-if="memberErrors.name" class="mt-1 text-sm text-red-600">{{ memberErrors.name }}</p>
                  </div>
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                      <input v-model="memberForm.email" type="email" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white" placeholder="email@example.com" />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Phone</label>
                      <input v-model="memberForm.phone" type="tel" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white" placeholder="(555) 123-4567" />
                    </div>
                  </div>
                </div>

                <!-- Slot assignment -->
                <div v-if="slots.length > 0">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Assign to Instrument
                  </label>
                  <select
                    v-model="memberForm.slot_id"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white"
                  >
                    <option value="">No specific instrument</option>
                    <option v-for="slot in slots" :key="slot.id" :value="slot.id">
                      {{ slot.name }}{{ slot.is_required ? ' (Required)' : '' }} — {{ slot.member_count }}/{{ slot.quantity }}
                    </option>
                  </select>
                </div>

                <!-- Role / Instrument -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Role / Instrument
                  </label>
                  <select
                    v-model="memberForm.band_role_id"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-white"
                  >
                    <option value="">Select a role...</option>
                    <option v-for="role in bandRoles" :key="role.id" :value="role.id">{{ role.name }}</option>
                  </select>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Roles can be managed in your <a :href="route('bands.roles.page', bandId)" target="_blank" class="text-blue-600 hover:underline">band settings</a>
                  </p>
                </div>

                <!-- Notes -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</label>
                  <textarea
                    v-model="memberForm.notes"
                    rows="2"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-slate-700 dark:text-white"
                    placeholder="Any additional notes..."
                  />
                </div>

                <button
                  @click="addMember"
                  :disabled="processing"
                  class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                >
                  <svg v-if="processing" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                  </svg>
                  Add Member
                </button>
              </div>
            </div>

            <!-- Members List — grouped by slot -->
            <div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Current Members ({{ members.length }})
              </h3>

              <div v-if="members.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                <svg class="mx-auto h-12 w-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                No members in this roster yet
              </div>

              <div v-else class="space-y-4">
                <!-- Role groups -->
                <template v-for="group in slotsByRole" :key="group.roleName || '__none__'">
                  <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                    <!-- Role header -->
                    <div v-if="group.roleName" class="px-4 py-2 bg-gray-100 dark:bg-slate-700 border-b border-gray-200 dark:border-gray-600">
                      <span class="font-bold text-sm text-gray-700 dark:text-gray-200 uppercase tracking-wide">{{ group.roleName }}</span>
                    </div>

                    <!-- Slot sub-sections within the role -->
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                      <div v-for="slot in group.slots" :key="slot.id">
                        <!-- Slot header -->
                        <div class="flex items-center gap-3 px-4 py-2 bg-gray-50 dark:bg-slate-700/50">
                          <span class="font-medium text-sm text-gray-900 dark:text-white">{{ slot.name }}</span>
                          <span
                            :class="slot.is_required ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                            class="px-1.5 py-0.5 rounded text-xs font-medium"
                          >
                            {{ slot.is_required ? 'Required' : 'Optional' }}
                          </span>
                          <span
                            :class="[
                              'px-1.5 py-0.5 rounded text-xs font-medium ml-auto',
                              slot.is_required && membersBySlot[slot.id]?.filter(m => m.is_active).length < slot.quantity
                                ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200'
                                : 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200'
                            ]"
                          >
                            {{ membersBySlot[slot.id]?.filter(m => m.is_active).length ?? 0 }}/{{ slot.quantity }}
                          </span>
                        </div>
                        <!-- Slot members -->
                        <div v-if="membersBySlot[slot.id]?.length > 0" class="divide-y divide-gray-100 dark:divide-gray-700">
                          <div
                            v-for="member in membersBySlot[slot.id]"
                            :key="member.id"
                            class="flex items-center justify-between px-4 py-3 pl-6 bg-white dark:bg-slate-800"
                          >
                            <MemberRow :member="member" @toggle="toggleMemberActive" @remove="removeMember" />
                          </div>
                        </div>
                        <div v-else class="px-4 py-3 pl-6 text-sm text-gray-400 dark:text-gray-500 italic">
                          No members assigned to this instrument
                        </div>
                      </div>
                    </div>
                  </div>
                </template>

                <!-- Unslotted members -->
                <div v-if="membersBySlot[null]?.length > 0" class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                  <div class="flex items-center gap-2 px-4 py-2 bg-gray-50 dark:bg-slate-700/50">
                    <span class="font-medium text-sm text-gray-500 dark:text-gray-400">Unassigned</span>
                  </div>
                  <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    <div
                      v-for="member in membersBySlot[null]"
                      :key="member.id"
                      class="flex items-center justify-between px-4 py-3 bg-white dark:bg-slate-800"
                    >
                      <MemberRow :member="member" @toggle="toggleMemberActive" @remove="removeMember" />
                    </div>
                  </div>
                </div>

                <!-- Flat list when no slots are defined -->
                <div v-if="slots.length === 0" class="space-y-3">
                  <div
                    v-for="member in members"
                    :key="member.id"
                    class="flex items-center justify-between p-4 bg-white dark:bg-slate-700 border border-gray-200 dark:border-gray-600 rounded-lg"
                  >
                    <MemberRow :member="member" @toggle="toggleMemberActive" @remove="removeMember" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-slate-900">
            <button @click="$emit('close')" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
              Done
            </button>
          </div>
        </div>
      </transition>
    </div>
  </transition>
</template>

<script>
// Sub-component to avoid repetition in member rows
const MemberRow = {
  props: { member: Object },
  emits: ['toggle', 'remove'],
  template: `
    <div class="flex-1">
      <div class="flex items-center gap-2">
        <h4 class="font-semibold text-gray-900 dark:text-white">{{ member.name }}</h4>
        <span v-if="member.is_user" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Band Member</span>
        <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">Guest</span>
        <span v-if="!member.is_active" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Inactive</span>
      </div>
      <div class="mt-1 text-sm text-gray-600 dark:text-gray-400 space-y-0.5">
        <div v-if="member.role"><span class="font-medium">Role:</span> {{ member.role }}</div>
        <div v-if="member.email"><span class="font-medium">Email:</span> {{ member.email }}</div>
        <div v-if="member.phone"><span class="font-medium">Phone:</span> {{ member.phone }}</div>
        <div v-if="member.notes" class="text-xs italic">{{ member.notes }}</div>
      </div>
    </div>
    <div class="flex items-center gap-2 ml-4">
      <button @click="$emit('toggle', member)" :title="member.is_active ? 'Mark as inactive' : 'Mark as active'" class="p-2 text-gray-400 hover:text-yellow-600 dark:hover:text-yellow-400">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
      </button>
      <button @click="$emit('remove', member)" class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
      </button>
    </div>
  `
};

export default {
  components: { MemberRow },
  props: {
    roster: { type: Object, required: true },
    bandId: { type: Number, required: true }
  },
  emits: ['close'],
  data() {
    return {
      members: [],
      slots: [],
      bandMembers: [],
      bandRoles: [],
      showAddForm: false,
      showSlotsSection: true,
      showAddSlotForm: false,
      editingSlotId: null,
      slotForm: { name: '', band_role_id: '', is_required: true, quantity: 1, notes: '' },
      slotEditForm: { name: '', band_role_id: '', is_required: true, quantity: 1, notes: '' },
      slotProcessing: false,
      memberForm: { type: 'user', user_id: '', name: '', email: '', phone: '', slot_id: '', band_role_id: '', notes: '' },
      memberErrors: {},
      processing: false,
    }
  },
  computed: {
    availableBandMembers() {
      const currentUserIds = this.members.filter(m => m.user_id).map(m => m.user_id)
      return this.bandMembers.filter(user => !currentUserIds.includes(user.id))
    },
    membersBySlot() {
      const grouped = { null: [] }
      this.slots.forEach(slot => { grouped[slot.id] = [] })
      this.members.forEach(member => {
        const key = member.slot_id ?? null
        if (key !== null && !grouped[key]) grouped[key] = []
        grouped[key !== null ? key : null].push(member)
      })
      return grouped
    },
    slotsByRole() {
      const roleGroups = {}
      const roleOrder = []
      this.slots.forEach(slot => {
        const key = slot.band_role_name || '__none__'
        if (!roleGroups[key]) {
          roleGroups[key] = { roleName: slot.band_role_name || null, slots: [] }
          roleOrder.push(key)
        }
        roleGroups[key].slots.push(slot)
      })
      return roleOrder.map(key => roleGroups[key])
    },
    unfilledRequiredCount() {
      return this.slots.filter(slot =>
        slot.is_required &&
        (this.membersBySlot[slot.id]?.filter(m => m.is_active).length ?? 0) < slot.quantity
      ).length
    },
  },
  mounted() {
    this.loadRosterDetails()
    this.loadBandMembers()
    this.loadBandRoles()
  },
  methods: {
    loadRosterDetails() {
      axios.get(route('rosters.show', this.roster.id))
        .then(response => {
          this.members = response.data.members || []
          this.slots = response.data.slots || []
        })
        .catch(error => console.error('Failed to load roster details:', error))
    },
    loadBandMembers() {
      axios.get(`/api/bands/${this.bandId}/members`)
        .then(response => { this.bandMembers = response.data.members || [] })
        .catch(error => console.error('Failed to load band members:', error))
    },
    loadBandRoles() {
      axios.get(route('bands.roles.index', this.bandId))
        .then(response => { this.bandRoles = response.data.roles || [] })
        .catch(error => console.error('Failed to load band roles:', error))
    },
    cancelAdd() {
      this.showAddForm = false
      this.resetMemberForm()
      this.memberErrors = {}
    },
    resetMemberForm() {
      this.memberForm = { type: 'user', user_id: '', name: '', email: '', phone: '', slot_id: '', band_role_id: '', notes: '' }
    },
    resetSlotForm() {
      this.slotForm = { name: '', band_role_id: '', is_required: true, quantity: 1, notes: '' }
    },
    addMember() {
      this.memberErrors = {}
      this.processing = true

      const data = this.memberForm.type === 'user'
        ? { user_id: this.memberForm.user_id, slot_id: this.memberForm.slot_id || null, band_role_id: this.memberForm.band_role_id, notes: this.memberForm.notes }
        : { name: this.memberForm.name, email: this.memberForm.email, phone: this.memberForm.phone, slot_id: this.memberForm.slot_id || null, band_role_id: this.memberForm.band_role_id, notes: this.memberForm.notes }

      axios.post(route('rosters.members.store', this.roster.id), data)
        .then(() => { this.loadRosterDetails(); this.cancelAdd() })
        .catch(error => {
          if (error.response?.data?.errors) {
            this.memberErrors = error.response.data.errors
          } else {
            alert('Failed to add member')
          }
        })
        .finally(() => { this.processing = false })
    },
    toggleMemberActive(member) {
      axios.post(route('rosters.members.toggleActive', member.id))
        .then(() => this.loadRosterDetails())
        .catch(() => alert('Failed to update member status'))
    },
    removeMember(member) {
      if (!confirm(`Remove ${member.name} from this roster?`)) return
      axios.delete(route('rosters.members.destroy', member.id))
        .then(() => this.loadRosterDetails())
        .catch(error => alert(error.response?.data?.message || 'Failed to remove member'))
    },
    addSlot() {
      if (!this.slotForm.name) return
      this.slotProcessing = true
      axios.post(route('rosters.slots.store', this.roster.id), this.slotForm)
        .then(() => { this.loadRosterDetails(); this.showAddSlotForm = false; this.resetSlotForm() })
        .catch(() => alert('Failed to add slot'))
        .finally(() => { this.slotProcessing = false })
    },
    startEditSlot(slot) {
      this.editingSlotId = slot.id
      this.slotEditForm = { name: slot.name, band_role_id: slot.band_role_id || '', is_required: slot.is_required, quantity: slot.quantity, notes: slot.notes || '' }
    },
    saveSlotEdit(slot) {
      axios.patch(route('rosters.slots.update', slot.id), this.slotEditForm)
        .then(() => { this.loadRosterDetails(); this.editingSlotId = null })
        .catch(() => alert('Failed to update slot'))
    },
    deleteSlot(slot) {
      if (!confirm(`Delete instrument "${slot.name}"? Members assigned to it will become unassigned.`)) return
      axios.delete(route('rosters.slots.destroy', slot.id))
        .then(() => this.loadRosterDetails())
        .catch(() => alert('Failed to delete slot'))
    },
  }
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-down-enter-active { transition: all 0.3s ease-out; }
.slide-down-leave-active { transition: all 0.2s ease-in; }
.slide-down-enter-from, .slide-down-leave-to { transform: translateY(-20px); opacity: 0; }
</style>
