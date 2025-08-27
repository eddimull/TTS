<template>
  <div class="space-y-6">
    <div class="border-b border-gray-200 dark:border-gray-600 pb-6">
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
        Calendar Access Management
      </h3>
      <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
        Manage who has access to each calendar and their permission level.
      </p>

      <!-- Access Table -->
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                User
              </th>
              <th
                v-for="calendar in availableCalendars"
                :key="calendar.id"
                class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
              >
                {{ getCalendarTypeLabel(calendar.type) }}
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-600">
            <!-- Band Owners -->
            <tr
              v-for="owner in bandOwnersAndMembers.owners"
              :key="`owner-${owner.id}`"
              class="bg-yellow-50 dark:bg-yellow-900/10"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 bg-yellow-100 dark:bg-yellow-900/30 rounded-full flex items-center justify-center">
                    <span class="text-yellow-700 dark:text-yellow-300 font-medium text-xs">
                      {{ owner.name.charAt(0).toUpperCase() }}
                    </span>
                  </div>
                  <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ owner.name }}
                    </div>
                    <div class="text-xs text-yellow-700 dark:text-yellow-300 font-medium">
                      Owner
                    </div>
                  </div>
                </div>
              </td>
              <td
                v-for="calendar in availableCalendars"
                :key="`${owner.id}-${calendar.id}`"
                class="px-3 py-4 text-center"
              >
                <select
                  :value="getUserCalendarRole(owner.id, calendar.id)"
                  class="text-xs border border-gray-300 dark:border-gray-600 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                  @change="updateUserCalendarRole(owner.id, calendar.id, $event.target.value)"
                >
                  <option
                    v-for="role in roles"
                    :key="role.value"
                    :value="role.value"
                  >
                    {{ role.label }}
                  </option>
                </select>
              </td>
            </tr>

            <!-- Band Members -->
            <tr
              v-for="member in bandOwnersAndMembers.members"
              :key="`member-${member.id}`"
              class="bg-blue-50 dark:bg-blue-900/10"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center">
                    <span class="text-blue-700 dark:text-blue-300 font-medium text-xs">
                      {{ member.name.charAt(0).toUpperCase() }}
                    </span>
                  </div>
                  <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ member.name }}
                    </div>
                    <div class="text-xs text-blue-700 dark:text-blue-300 font-medium">
                      Member
                    </div>
                  </div>
                </div>
              </td>
              <td
                v-for="calendar in availableCalendars"
                :key="`${member.id}-${calendar.id}`"
                class="px-3 py-4 text-center"
              >
                <select
                  :value="getUserCalendarRole(member.id, calendar.id)"
                  class="text-xs border border-gray-300 dark:border-gray-600 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                  @change="updateUserCalendarRole(member.id, calendar.id, $event.target.value)"
                >
                  <option
                    v-for="role in roles"
                    :key="role.value"
                    :value="role.value"
                  >
                    {{ role.label }}
                  </option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- No Users Message -->
      <div
        v-if="bandOwnersAndMembers.owners.length === 0 && bandOwnersAndMembers.members.length === 0"
        class="text-center py-8 text-gray-500 dark:text-gray-400"
      >
        <p>No band members found</p>
      </div>
    </div>

    <!-- Bulk Actions -->
    <div class="pt-6">
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
        Bulk Actions
      </h3>
      <div class="flex gap-3">
        <Button
          label="Grant All Members Writer Access"
          icon="pi pi-users"
          severity="info"
          :loading="syncingMembers"
          @click="grantAllMembersAccess('writer')"
        />
        <Button
          label="Remove All Access"
          icon="pi pi-times"
          severity="danger"
          outline
          @click="removeAllAccess"
        />
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'EditCalendarAccess',
  props: {
    band: {
      type: Object,
      required: true
    },
    calendarTypes: {
      type: Array,
      required: true
    },
    syncingMembers: {
      type: Boolean,
      default: false
    }
  },
  emits: [
    'update-calendar-access-for-user'
  ],
  data() {
    return {
      userCalendarRoles: {}, // { userId: { calendarId: role } }
      originalUserCalendarRoles: {}, // Store original state to detect changes
      updatingAccess: {}
    };
  },
  computed: {
    roles() {
      return [{label: 'None', value: ''}, {label: 'Reader', value: 'reader'}, {label: 'Writer', value: 'writer'}, {label: 'Owner', value: 'owner'}];
    },
    availableCalendars() {
      return (this.band.calendars || []).filter(calendar => calendar.calendar_id);
    },
    bandOwnersAndMembers() {
      const owners = this.band.owners || [];
      const members = this.band.members || [];
      
      return {
        owners: owners
          .map(owner => {
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
  mounted() {
    this.initializeUserRoles();
  },
  methods: {
    initializeUserRoles() {
      // Initialize with current access from band data
      const roles = {};
      
      this.availableCalendars.forEach(calendar => {
        if (calendar.user_access && Array.isArray(calendar.user_access)) {
          calendar.user_access.forEach(access => {
            if (!roles[access.user_id]) {
              roles[access.user_id] = {};
            }
            roles[access.user_id][calendar.id] = access.role;
          });
        }
      });
      
      this.userCalendarRoles = { ...roles };
      this.originalUserCalendarRoles = { ...roles };
    },
    getCalendarTypeLabel(type) {
      const calType = this.calendarTypes.find(ct => ct.value === type);
      return calType ? calType.label : type;
    },
    getUserCalendarRole(userId, calendarId) {
      return this.userCalendarRoles[userId]?.[calendarId] || '';
    },
    updateUserCalendarRole(userId, calendarId, role) {
      const calendar = this.band.calendars.find(cal => cal.id === calendarId);
      console.log(this.band.calendars);
      console.log('Updating role:', calendar);
      if(role === '')
      {
        this.$inertia.delete(route('bands.revokeCalendarAccess', {'calendar_id': calendar.calendar_id, 'user': userId}));
      }
      else
      {
        this.$inertia.post(route('bands.grantCalendarAccess', {'calendar_id': calendar.calendar_id}), { user_id: userId, role: role},{
          preserveScroll:true,
          preserveState:true,
        });
      }
    },
    hasChanges(userId) {
      // Check if current state differs from original state for this user
      const currentRoles = this.userCalendarRoles[userId] || {};
      const originalRoles = this.originalUserCalendarRoles[userId] || {};
      
      // Check if any calendar role has changed
      for (const calendar of this.availableCalendars) {
        const currentRole = currentRoles[calendar.id] || '';
        const originalRole = originalRoles[calendar.id] || '';
        if (currentRole !== originalRole) {
          return true;
        }
      }
      
      return false;
    },
    grantAllMembersAccess(role) {
      const allUsers = [...this.bandOwnersAndMembers.owners, ...this.bandOwnersAndMembers.members];
      
      allUsers.forEach(user => {
        this.availableCalendars.forEach(calendar => {
          this.updateUserCalendarRole(user.id, calendar.id, role);
        });
      });
    },
    removeAllAccess() {
      const allUsers = [...this.bandOwnersAndMembers.owners, ...this.bandOwnersAndMembers.members];
      
      allUsers.forEach(user => {
        this.availableCalendars.forEach(calendar => {
          this.updateUserCalendarRole(user.id, calendar.id, '');
        });
      });
    }
  }
}
</script>