<template>
  <Container>
    <div class="max-w-4xl mx-auto">
      <div class="componentPanel shadow-md rounded-lg p-6">
        <!-- Panel Tabs -->
        <div class="mb-6 flex rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600">
          <button
            v-for="panel in panels"
            :key="panel.name"
            :class="[
              activePanel === panel.name 
                ? 'bg-blue-600 text-white' 
                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600',
              'flex-1 py-3 px-4 font-medium transition-colors duration-200 flex items-center justify-center gap-2'
            ]"
            @click="gotoPage(panel.name.toLowerCase())"
          >
            <span
              class="w-5 h-5"
              v-html="panel.icon"
            />
            {{ panel.name }}
          </button>
        </div>

        <!-- Details Panel -->
        <EditDetails
          v-if="activePanel === 'Details'"
          :band="band"
          :form="form"
          :loading="loading"
          :url-warn="urlWarn"
          @update-band="updateBand"
          @update-form="updateForm"
          @upload-logo="uploadLogo"
        />

        <!-- Members Panel -->
        <EditMembers
          v-if="activePanel === 'Band Members'"
          :band="band"
          :inviting="inviting"
          :invite="invite"
          @delete-owner="deleteOwner"
          @delete-invite="deleteInvite"
          @invite-owner="inviteOwner"
          @invite-member="inviteMember"
          @update-inviting="updateInviting"
          @update-invite-email="updateInviteEmail"
        />

        <!-- Calendar Access Panel -->
        <EditCalendar
          v-if="activePanel === 'Calendar Access'"
          :band="band"
          :calendar-types="calendarTypes"
          :creating-calendars="creatingCalendars"
          :creating-all-calendars="creatingAllCalendars"
          :syncing-calendars="syncingCalendars"
          :syncing-all-calendars="syncingAllCalendars"
          :granting-access="grantingAccess"
          :granting-access-loading="grantingAccessLoading"
          :syncing-members="syncingMembers"
          :calendar-access="calendarAccess"
          :calendar-statuses="calendarStatuses"
          @create-calendar-by-type="createCalendarByType"
          @sync-calendar-by-type="syncCalendarByType"
          @create-all-calendars="createAllCalendars"
          @sync-all-calendars="syncAllCalendars"
          @grant-calendar-access="grantCalendarAccess"
          @cancel-grant-access="cancelGrantAccess"
          @sync-all-band-members="syncAllBandMembers"
          @update-granting-access="updateGrantingAccess"
          @update-calendar-access="updateCalendarAccess"
        />
      </div>
    </div>
  </Container>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
import EditDetails from './Components/EditDetails.vue'
import EditMembers from './Components/EditMembers.vue'
import EditCalendar from './Components/EditCalendar.vue'

export default {
  components: {
    EditDetails,
    EditMembers,
    EditCalendar,
  },
  layout: BreezeAuthenticatedLayout,
  pageTitle: 'Edit Band',
  props: {
    errors: {
      type: Object,
      default: () => ({})
    },
    band: {
      type: Object,
      required: true
    },
    members: {
      type: Array,
      default: () => []
    },
    owners: {
      type: Array,
      default: () => []
    },
    setting:{
      type: String,
      default: null
    }
  },
  data() {
    return {
      urlWarn: false,
      syncing: false,
      showInstructions: false,
      activePanel: this.getInitialPanel(),
      loading: false,
      inviting: false,
      invite: {
        email: ''
      },
      creatingCalendar: false,
      creatingCalendars: {
        booking: false,
        events: false,
        public: false
      },
      creatingAllCalendars: false,
      syncingCalendars: {
        booking: false,
        events: false,
        public: false
      },
      syncingAllCalendars: false,
      grantingAccess: false,
      grantingAccessLoading: false,
      syncingMembers: false,
      calendarAccess: {
        user_id: '',
        role: 'writer',
        calendar_id: 'all'
      },
      calendarTypes: [
        { 
          value: 'booking', 
          label: 'Booking Calendar', 
          description: 'Private booking information - Owners only',
          access: 'Owners: Read only'
        },
        { 
          value: 'event', 
          label: 'All Events Calendar', 
          description: 'All band events (private and public)',
          access: 'Owners: Edit, Members: Read'
        },
        { 
          value: 'public', 
          label: 'Public Events Calendar', 
          description: 'Public events only - Visible to everyone',
          access: 'Owners: Edit, Members: Read, Public: Read'
        }
      ],
      panels: [
        {
          name: 'Details',
          icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>'
        }, {
          name: 'Band Members',
          icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>'
        }, {
          name: 'Calendar Access',
          icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>'
        }],
      form: {
        name: this.band.name,
        site_name: this.band.site_name,
        calendar_id: this.band.calendar_id
      }
    }
  },
  computed: {
    calendarStatuses() {
      const statuses = {
        booking: false,
        event: false,
        public: false
      };
      
      if (this.band.calendars && Array.isArray(this.band.calendars)) {
        this.band.calendars.forEach(calendar => {
          if (calendar.type && statuses.hasOwnProperty(calendar.type)) {
            statuses[calendar.type] = true;
          }
        });
      }
      
      return statuses;
    },
    allCalendarsExist() {
      return Object.values(this.calendarStatuses).every(status => status);
    },
    anyCalendarExists() {
      return Object.values(this.calendarStatuses).some(status => status);
    }
  },
  watch: {
    form: {
      deep: true,
      handler() {

      }
    }
  },
  methods: {
    // Initial panel setup
    getInitialPanel() {
      if (!this.setting) {
        return 'Details';
      }
      
      // Map URL settings to panel names
      const settingToPanelMap = {
        'details': 'Details',
        'members': 'Band Members',
        'band members': 'Band Members',
        'calendar': 'Calendar Access',
        'calendars': 'Calendar Access',
        'calendar access': 'Calendar Access',
        'access': 'Calendar Access'
      };
      
      return settingToPanelMap[this.setting.toLowerCase()] || 'Details';
    },
    gotoPage(pageName) {
      this.$inertia.visit(`/bands/${this.band.id}/edit/${pageName}`);
    },

    // Form update methods
    updateForm(field, value) {
      this.form[field] = value;
      if (field === 'site_name') {
        this.filter();
      }
    },
    filter() {
      if (this.form.site_name.length > 0) {
        let message = this.form.site_name;
        let urlsafeName = message.replace(/[^aA-zZ0-9\-_]/gm, "")
        this.urlWarn = urlsafeName !== this.form.site_name
        this.form.site_name = urlsafeName;
      }
    },

    // Details methods
    updateBand() {
      const bandID = this.band.id;
      this.loading = true;
      this.$inertia.patch('/bands/' + bandID, this.form)
        .then(() => {
          this.loading = false;
        })
    },
    uploadLogo(event) {
      console.log(event.files);
      this.$inertia.post(route('bands.uploadLogo', this.band), { 'logo': event.files[0] });
    },
    syncCalendar() {
      this.syncing = true;
      this.$inertia.post('./syncCalendar', {}, {
        onSuccess: () => {
          this.$swal.fire("Calendar Synced", "", "success");
          this.syncing = false;
        }
      })
    },
    createCalendar() {
      this.creatingCalendar = true;
      this.$inertia.post(this.band.id + '/createCalendar', {}, {
        onSuccess: (page) => {
          this.creatingCalendar = false;
          if (page.props.band && page.props.band.calendar_id) {
            this.form.calendar_id = page.props.band.calendar_id;
          }
        },
        onError: () => {
          this.creatingCalendar = false;
        }
      });
    },

    // Members methods
    updateInviting(value) {
      this.inviting = value;
    },
    updateInviteEmail(value) {
      this.invite.email = value;
    },
    inviteOwner() {
      this.$inertia.post('/inviteOwner/' + this.band.id, {
        band_id: this.band.id,
        email: this.invite.email
      }, {
        onSuccess: () => {
          this.inviting = false;
          this.invite.email = '';
        }
      })
    },
    inviteMember() {
      this.$inertia.post('/inviteMember/' + this.band.id, {
        band_id: this.band.id,
        email: this.invite.email
      }, {
        onSuccess: () => {
          this.inviting = false;
          this.invite.email = '';
        }
      })
    },
    deleteOwner(owner) {
      this.$swal.fire({
        title: 'Are you sure you remove ' + owner.user.name + ' as an owner?',
        text: "You won't be able to revert this!",
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, BANISH THEM!'
      }).then((result) => {
        if (result.value) {
          this.$inertia.delete('/bands/deleteOwner/' + this.band.id + '/' + owner.user.id);
        }
      })
    },
    deleteInvite(invite) {
      this.$swal.fire({
        title: 'Are you sure you want to remove the invite for ' + invite.email,
        text: "You won't be able to revert this!",
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (result.value) {
          this.$inertia.delete('/deleteInvite/' + this.band.id + '/' + invite.id);
        }
      })
    },

    // Calendar methods
    createCalendarByType(type) {
      this.creatingCalendars[type] = true;
      this.$inertia.post(route('bands.createCalendar', { band: this.band.id, type }), {}, {
        onSuccess: (page) => {
          this.creatingCalendars[type] = false;
          this.$swal.fire("Calendar Created", `${this.getCalendarTypeLabel(type)} has been created successfully`, "success");
        },
        onError: () => {
          this.creatingCalendars[type] = false;
        }
      });
    },
    syncCalendarByType(type) {
      this.syncingCalendars[type] = true;
      
      this.$inertia.post(`/syncCalendar/${this.getCalendarIdFromType(type)}`, {}, {
        onError: () => {
          this.syncingCalendars[type] = false;
        }
      });
    },
    createAllCalendars() {
      this.creatingAllCalendars = true;
      this.$inertia.post(`/bands/${this.band.id}/createCalendars`, {}, {
        onSuccess: (page) => {
          this.creatingAllCalendars = false;
          this.$swal.fire({
            title: "All Calendars Created",
            html: `
              <div class="text-left">
                <p class="mb-3">All calendar types have been created with proper access:</p>
                <ul class="text-sm">
                  <li><strong>Booking Calendar:</strong> Owners can view bookings</li>
                  <li><strong>Event Calendar:</strong> All events (private + public), Members can read</li>
                  <li><strong>Public Calendar:</strong> Public events only, visible to everyone</li>
                </ul>
              </div>
            `,
            icon: "success"
          });
        },
        onError: () => {
          this.creatingAllCalendars = false;
        }
      });
    },
    syncAllCalendars() {
      this.syncingAllCalendars = true;
      this.$inertia.post(`/bands/${this.band.id}/syncAllCalendars`, {}, {
        onSuccess: () => {
          this.$swal.fire("All Calendars Synced", "Bookings, events, and public events have been synced to their respective calendars", "success");
        },
        onFinish: () => {
          this.syncingAllCalendars = false;
        }
      });
    },
    updateGrantingAccess(value) {
      this.grantingAccess = value;
    },
    updateCalendarAccess(field, value) {
      this.calendarAccess[field] = value;
    },

    updateCalendarAccessForUser(data) {
      this.grantingAccessLoading = true;
      this.$inertia.post(route('bands.grantCalendarAccess', this.band.id), data, {
        onError: () => {
          this.grantingAccessLoading = false;
        },
        onFinish: () => {
          this.grantingAccessLoading = false;
        }
      });
    },
    grantCalendarAccess() {
      this.grantingAccessLoading = true;
      this.$inertia.post(route('bands.grantCalendarAccess', this.band.id), {
        user_id: this.calendarAccess.user_id,
        role: this.calendarAccess.role,
        calendar_id: this.calendarAccess.calendar_id
      }, {
        onError: () => {
          this.grantingAccessLoading = false;
        },
        onFinish: () => {
          this.grantingAccessLoading = false;
        }
      });
    },
    cancelGrantAccess() {
      this.grantingAccess = false;
      this.calendarAccess.user_id = '';
      this.calendarAccess.role = 'writer';
      this.calendarAccess.calendar_id = '';
    },
    syncAllBandMembers() {
      this.syncingMembers = true;
      this.$inertia.post(`/bands/${this.band.id}/syncBandCalendarAccess`, {}, {
        onSuccess: () => {
          this.$swal.fire("Members Synced", "All band members now have calendar access", "success");
        },
        onFinish: () => {
          this.syncingMembers = false;
        }
      });
    },
    getCalendarTypeLabel(type) {
      const calType = this.calendarTypes.find(ct => ct.value === type);
      console.log(calType)
      return calType ? calType.label : type;
    },
    getCalendarIdFromType(type) {
      const calendar = this.band.calendars.find(cal => cal.type === type);
      return calendar ? calendar.calendar_id : null;
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
.slide-down-leave-to

/* .slide-fade-leave-active below version 2.1.8 */
  {
  transform: translateY(-50px);
  max-height: 0px;
}
</style>
