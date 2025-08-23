<template>
  <div class="space-y-6">
    <!-- Calendar Access Overview -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
      <h3 class="font-medium text-blue-800 dark:text-blue-200 mb-3">
        Calendar Management
      </h3>
      <p class="text-sm text-blue-700 dark:text-blue-300 mb-3">
        Create and manage separate Google Calendars for different types of events.
      </p>
    </div>

    <!-- Calendar Types Management -->
    <div class="space-y-4">
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
        Calendar Types
      </h3>
       
      <div class="grid gap-4">
        <div
          v-for="calType in calendarTypes"
          :key="calType.value"
          class="border border-gray-200 dark:border-gray-600 rounded-lg p-4"
        >
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <h4 class="font-medium text-gray-900 dark:text-white">
                {{ calType.label }}
              </h4>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ calType.description }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                Access: {{ calType.access }}
              </p>
              
              <!-- Calendar Status -->
              <div class="mt-2">
                <div
                  v-if="getCalendarStatus(calType.value)"
                  class="flex items-center gap-2"
                >
                  <svg
                    class="w-4 h-4 text-green-600 dark:text-green-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M5 13l4 4L19 7"
                    />
                  </svg>
                  <div class="flex flex-col">
                    <span class="text-sm text-green-700 dark:text-green-300">
                      Calendar created
                    </span>
                    <span 
                      v-if="getCalendarId(calType.value)"
                      class="text-xs text-gray-500 dark:text-gray-400 font-mono"
                    >
                      ID: {{ getCalendarId(calType.value) }}
                    </span>
                  </div>
                </div>
                <div
                  v-else
                  class="flex items-center gap-2"
                >
                  <svg
                    class="w-4 h-4 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                  </svg>
                  <span class="text-sm text-gray-500 dark:text-gray-400">
                    Not created
                  </span>
                </div>
              </div>
            </div>
            
            <div class="flex items-center gap-2 ml-4">
              <Button
                v-if="!getCalendarStatus(calType.value)"
                :label="`Create ${calType.label}`"
                icon="pi pi-plus"
                severity="success"
                size="small"
                :loading="creatingCalendars[calType.value]"
                @click="createCalendarByType(calType.value)"
              />
              <Button
                v-else
                label="Sync"
                icon="pi pi-sync"
                severity="secondary"
                size="small"
                :loading="syncingCalendars[calType.value]"
                @click="syncCalendarByType(calType.value)"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Bulk Actions -->
      <div class="border-t border-gray-200 dark:border-gray-600 pt-4">
        <div class="flex items-center gap-3">
          <Button
            label="Create All Calendars"
            icon="pi pi-calendar-plus"
            severity="info"
            :loading="creatingAllCalendars"
            :disabled="allCalendarsExist"
            @click="createAllCalendars"
          />
          <Button
            label="Sync All Calendars"
            icon="pi pi-sync"
            severity="secondary"
            :loading="syncingAllCalendars"
            :disabled="!anyCalendarExists"
            @click="syncAllCalendars"
          />
        </div>
      </div>
    </div>

    <!-- Calendar Access Management (only show if calendars exist) -->
    <div
      v-if="anyCalendarExists"
      class="border-t border-gray-200 dark:border-gray-600 pt-6"
    >
      <!-- Grant Access Form -->
      <div class="border-b border-gray-200 dark:border-gray-600 pb-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
          Grant Calendar Access
        </h3>
        <div v-if="!grantingAccess">
          <Button
            label="Grant Access to User"
            icon="pi pi-calendar-plus"
            @click="startGrantingAccess"
          />
        </div>
        
        <transition name="slide-down">
          <div
            v-if="grantingAccess"
            class="space-y-4"
          >
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Select User
              </label>
              <select
                :value="calendarAccess.email"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                required
                @change="updateAccessUserId"
              >
                <option value="">
                  Select a band member or owner...
                </option>
                <optgroup
                  v-if="bandOwnersAndMembers.owners.length > 0"
                  label="Band Owners"
                >
                  <option
                    v-for="owner in bandOwnersAndMembers.owners"
                    :key="`owner-${owner.id}`"
                    :value="owner.id"
                  >
                    {{ owner.name }} ({{ owner.email }}) - Owner
                  </option>
                </optgroup>
                <optgroup
                  v-if="bandOwnersAndMembers.members.length > 0"
                  label="Band Members"
                >
                  <option
                    v-for="member in bandOwnersAndMembers.members"
                    :key="`member-${member.id}`"
                    :value="member.id"
                  >
                    {{ member.name }} ({{ member.email }}) - Member
                  </option>
                </optgroup>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Access Level
              </label>
              <select
                :value="calendarAccess.role"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                @change="updateAccessRole"
              >
                <option value="reader">
                  Reader (view only)
                </option>
                <option value="writer">
                  Writer (can edit events)
                </option>
                <option value="owner">
                  Owner (full access)
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Calendar Type
              </label>
              <select
                :value="calendarAccess.calendarType"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                @change="updateAccessCalendarId"
              >
                <option value="all">
                  All Calendars
                </option>
                <option
                  v-for="calType in calendarTypes"
                  :key="calType.value"
                  :value="getDBCalendarId(calType.value)"
                  :disabled="!getCalendarStatus(calType.value)"
                >
                  {{ calType.label }}
                </option>
              </select>
            </div>
            <div class="flex items-center gap-3">
              <Button
                label="Grant Access"
                icon="pi pi-check"
                :loading="grantingAccessLoading"
                :disabled="!calendarAccess.user_id"
                @click="grantCalendarAccess"
              />
              <Button
                label="Cancel"
                icon="pi pi-times"
                severity="secondary"
                text
                size="small"
                @click="cancelGrantAccess"
              />
            </div>
          </div>
        </transition>
      </div>
      <!-- Sync All Band Members -->
      <div class="pt-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
          Sync Band Members
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
          Grant calendar access to all current band members and owners automatically.
        </p>
        <Button
          label="Sync All Band Members"
          icon="pi pi-sync"
          severity="info"
          :loading="syncingMembers"
          @click="syncAllBandMembers"
        />
      </div>
    </div>

    <!-- No Calendars Message -->
    <div
      v-else
      class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4"
    >
      <div class="flex items-center gap-3">
        <svg
          class="w-6 h-6 text-yellow-600 dark:text-yellow-400"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"
          />
        </svg>
        <div>
          <h3 class="font-medium text-yellow-800 dark:text-yellow-200">
            No Calendars Configured
          </h3>
          <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
            Create your first calendar to start managing events and bookings.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { update } from 'lodash';

export default {
  name: 'EditCalendar',
  props: {
    band: {
      type: Object,
      required: true
    },
    calendarTypes: {
      type: Array,
      required: true
    },
    creatingCalendars: {
      type: Object,
      required: true
    },
    creatingAllCalendars: {
      type: Boolean,
      default: false
    },
    syncingCalendars: {
      type: Object,
      required: true
    },
    syncingAllCalendars: {
      type: Boolean,
      default: false
    },
    grantingAccess: {
      type: Boolean,
      default: false
    },
    grantingAccessLoading: {
      type: Boolean,
      default: false
    },
    syncingMembers: {
      type: Boolean,
      default: false
    },
    calendarAccess: {
      type: Object,
      required: true
    },
    calendarStatuses: {
      type: Object,
      required: true
    }
  },
  emits: [
    'create-calendar-by-type',
    'sync-calendar-by-type', 
    'create-all-calendars',
    'sync-all-calendars',
    'grant-calendar-access',
    'cancel-grant-access',
    'sync-all-band-members',
    'update-granting-access',
    'update-calendar-access'
  ],
  computed: {
    allCalendarsExist() {
      return Object.values(this.calendarStatuses).every(status => status);
    },
    anyCalendarExists() {
      return Object.values(this.calendarStatuses).some(status => status);
    },
    bandOwnersAndMembers() {
      const owners = this.band.owners || [];
      const members = this.band.members || [];
      
      return {
        owners: owners
          .map(owner => {
            // Try user_data first, then user as fallback
            const userData = owner.user_data || owner.user;
            return userData ? {
              id: userData.id,
              name: userData.name,
              email: userData.email
            } : null;
          })
          .filter(owner => owner && owner.email),
        members: members
          .map(member => {
            // Try user_data first, then user as fallback
            const userData = member.user_data || member.user;
            return userData ? {
              id: userData.id,
              name: userData.name,
              email: userData.email
            } : null;
          })
          .filter(member => member && member.email)
      };
    }
  },
  methods: {
    getCalendarStatus(type) {
      return this.calendarStatuses[type] || false;
    },
    getCalendarId(type) {
      if (this.band.calendars && Array.isArray(this.band.calendars)) {
        const calendar = this.band.calendars.find(cal => cal.type === type);
        return calendar ? calendar.calendar_id : null;
      }
      return null;
    },
    getDBCalendarId(type) {
      if (this.band.calendars && Array.isArray(this.band.calendars)) {
        const calendar = this.band.calendars.find(cal => cal.type === type);
        return calendar ? calendar.id : null;
      }
      return null;
    },
    createCalendarByType(type) {
      this.$emit('create-calendar-by-type', type);
    },
    syncCalendarByType(type) {
      this.$emit('sync-calendar-by-type', type);
    },
    createAllCalendars() {
      this.$emit('create-all-calendars');
    },
    syncAllCalendars() {
      this.$emit('sync-all-calendars');
    },
    startGrantingAccess() {
      this.$emit('update-granting-access', true);
    },
    grantCalendarAccess() {
      this.$emit('grant-calendar-access');
    },
    cancelGrantAccess() {
      this.$emit('cancel-grant-access');
    },
    syncAllBandMembers() {
      this.$emit('sync-all-band-members');
    },
    updateAccessUserId(event) {
      this.$emit('update-calendar-access', 'user_id', event.target.value);
    },
    updateAccessRole(event) {
      this.$emit('update-calendar-access', 'role', event.target.value);
    },
    updateAccessCalendarId(event) {
      this.$emit('update-calendar-access', 'calendar_id', event.target.value);
    }
  }
}
</script>

<style scoped>
.slide-down-enter-active {
  transition: all .2s ease;
}

.slide-down-leave-active {
  transition: all .1s cubic-bezier(1.0, 0.5, 0.8, 1.0);
  max-height: 230px;
}

.slide-down-enter-from,
.slide-down-leave-to {
  transform: translateY(-50px);
  max-height: 0px;
}
</style>
