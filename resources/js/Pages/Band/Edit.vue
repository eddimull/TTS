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
            @click="activePanel = panel.name"
          >
            <span
              class="w-5 h-5"
              v-html="panel.icon"
            />
            {{ panel.name }}
          </button>
        </div>

        <form
          v-if="activePanel == 'Details'"
          :action="'/bands/' + band.id"
          method="PATCH"
          @submit.prevent="updateBand"
        >
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Band Name
            </label>
            <input
              v-model="form.name"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
              placeholder="Enter band name"
              required
            >
          </div>
          <!-- Site Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Page Name (URL)
            </label>
            <input
              v-model="form.site_name"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
              placeholder="band_name"
              pattern="([a-zA-z0-9\-_]+)"
              @input="filter"
            >
            <p
              v-if="urlWarn"
              class="mt-1 text-sm text-red-600 dark:text-red-400"
            >
              Only letters, numbers, underscores, and hyphens are allowed
            </p>
          </div>
          <!-- Logo -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Band Logo
            </label>
            <div class="space-y-3">
              <div
                v-if="band.logo"
                class="flex items-center gap-4"
              >
                <img
                  :src="band.logo"
                  alt="Current logo"
                  class="w-16 h-16 object-cover rounded-lg border border-gray-200 dark:border-gray-600"
                >
                <span class="text-sm text-gray-600 dark:text-gray-400">Current logo</span>
              </div>
              <FileUpload
                ref="fileUpload"
                mode="basic"
                name="logo"
                accept="image/*"
                :auto="true"
                :custom-upload="true"
                choose-label="Upload New Logo"
                class="w-full"
                @uploader="uploadLogo"
              />
            </div>
          </div>
          <!-- Google Calendar Integration -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Google Calendar ID
            </label>
            <div class="flex gap-3">
              <input
                v-model="form.calendar_id"
                type="text"
                class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="your-calendar-id@group.calendar.google.com"
                :readonly="form.calendar_id"
              >
              <Button
                v-if="!form.calendar_id"
                type="button"
                label="Create Calendar"
                icon="pi pi-plus"
                severity="success"
                size="small"
                :loading="creatingCalendar"
                @click="createCalendar"
              />
            </div>
            
            <div 
              v-if="form.calendar_id"
              class="mt-2 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg"
            >
              <div class="flex items-center gap-2">
                <svg
                  class="w-5 h-5 text-green-600 dark:text-green-400"
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
                <span class="text-green-800 dark:text-green-200 font-medium text-sm">
                  Calendar configured and ready to sync
                </span>
              </div>
            </div>
          </div>

          <!-- Stripe Setup -->
          <div class="border-t border-gray-200 dark:border-gray-600 pt-6">
            <div
              v-if="!band.stripe_accounts"
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
                    Stripe Payment Setup Required
                  </h3>
                  <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                    Set up Stripe to accept payments for your band.
                  </p>
                  <a
                    :href="'/bands/' + band.id + '/setupStripe'"
                    class="inline-block mt-3"
                  >
                    <Button
                      severity="warning"
                      size="small"
                    >Setup Stripe</Button>
                  </a>
                </div>
              </div>
            </div>
            <div
              v-else
              class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4"
            >
              <div class="flex items-center gap-2">
                <svg
                  class="w-5 h-5 text-green-600 dark:text-green-400"
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
                <span class="text-green-800 dark:text-green-200 font-medium">Stripe account configured</span>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-600">
            <Button
              type="submit"
              label="Update Band"
              icon="pi pi-save"
              :loading="loading"
            />
            <Button
              type="button"
              :label="syncing ? 'Syncing...' : 'Sync Calendar'"
              icon="pi pi-calendar"
              severity="secondary"
              :disabled="syncing"
              :loading="syncing"
              @click="syncCalendar"
            />
          </div>
        </form>

        <div
          v-if="activePanel === 'Band Members'"
          class="space-y-6"
        >
          <!-- Members Section -->
          <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
              Members
            </h3>
            <div
              v-if="band.members && band.members.length > 0"
              class="space-y-2"
            >
              <div
                v-for="member in band.members"
                :key="member.id"
                class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
              >
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">
                    {{ member.user.name }}
                  </p>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ member.user.email }}
                  </p>
                </div>
                <Link
                  :href="'/permissions/' + band.id + '/' + member.user.id"
                  class="text-blue-600 dark:text-blue-400 hover:underline text-sm"
                >
                  Edit Permissions
                </Link>
              </div>
            </div>
            <p
              v-else
              class="text-gray-600 dark:text-gray-400 italic"
            >
              No members added yet.
            </p>
          </div>

          <!-- Owners Section -->
          <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
              Owners
            </h3>
            <div
              v-if="band.owners && band.owners.length > 0"
              class="space-y-2"
            >
              <div
                v-for="owner in band.owners"
                :key="owner.id"
                class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
              >
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">
                    {{ owner.user.name }}
                  </p>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ owner.user.email }}
                  </p>
                </div>
                <button
                  class="text-red-600 dark:text-red-400 hover:underline text-sm"
                  @click="deleteOwner(owner)"
                >
                  Remove
                </button>
              </div>
            </div>
            <p
              v-else
              class="text-gray-600 dark:text-gray-400 italic"
            >
              No owners found.
            </p>
          </div>

          <!-- Pending Invites -->
          <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
              Pending Invitations
            </h3>
            <div
              v-if="band.pending_invites && band.pending_invites.length > 0"
              class="space-y-2"
            >
              <div
                v-for="pendingInvite in band.pending_invites"
                :key="pendingInvite.id"
                class="flex items-center justify-between p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800"
              >
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">
                    {{ pendingInvite.email }}
                  </p>
                  <p class="text-sm text-yellow-700 dark:text-yellow-400">
                    Invitation pending
                  </p>
                </div>
                <button
                  class="text-red-600 dark:text-red-400 hover:underline text-sm"
                  @click="deleteInvite(pendingInvite)"
                >
                  Cancel
                </button>
              </div>
            </div>
            <p
              v-else
              class="text-gray-600 dark:text-gray-400 italic"
            >
              No pending invitations.
            </p>
          </div>

          <!-- Invite Form -->
          <div class="border-t border-gray-200 dark:border-gray-600 pt-6">
            <div v-if="!inviting">
              <Button
                label="Invite New Member"
                icon="pi pi-user-plus"
                @click="inviting = true"
              />
            </div>
              
            <transition name="slide-down">
              <div
                v-if="inviting"
                class="space-y-4"
              >
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Email Address
                  </label>
                  <input
                    v-model="invite.email"
                    type="email"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                    placeholder="user@example.com"
                    required
                  >
                </div>
                <div class="flex items-center gap-3">
                  <Button
                    label="Invite as Owner"
                    icon="pi pi-star"
                    severity="secondary"
                    size="small"
                    @click="inviteOwner"
                  />
                  <Button
                    label="Invite as Member"
                    icon="pi pi-user"
                    severity="secondary"
                    size="small"
                    @click="inviteMember"
                  />
                  <Button
                    label="Cancel"
                    icon="pi pi-times"
                    severity="secondary"
                    text
                    size="small"
                    @click="inviting = false"
                  />
                </div>
              </div>
            </transition>
          </div>
        </div>

        <!-- Calendar Access Panel -->
        <div
          v-if="activePanel === 'Calendar Access'"
          class="space-y-6"
        >
          <div
            v-if="!form.calendar_id"
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
                  No Calendar Configured
                </h3>
                <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                  Please create or configure a Google Calendar first to manage access.
                </p>
              </div>
            </div>
          </div>

          <div v-else>
            <!-- Grant Access Form -->
            <div class="border-b border-gray-200 dark:border-gray-600 pb-6">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                Grant Calendar Access
              </h3>
              <div v-if="!grantingAccess">
                <Button
                  label="Grant Access to User"
                  icon="pi pi-calendar-plus"
                  @click="grantingAccess = true"
                />
              </div>
              
              <transition name="slide-down">
                <div
                  v-if="grantingAccess"
                  class="space-y-4"
                >
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Email Address
                    </label>
                    <input
                      v-model="calendarAccess.email"
                      type="email"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                      placeholder="user@example.com"
                      required
                    >
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Access Level
                    </label>
                    <select
                      v-model="calendarAccess.role"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
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
                  <div class="flex items-center gap-3">
                    <Button
                      label="Grant Access"
                      icon="pi pi-check"
                      :loading="grantingAccessLoading"
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
        </div>
      </div>
    </div>
    <Container />
  </Container>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
import FileUpload from 'primevue/fileupload';
export default {
  components: {
    FileUpload,
  },
  layout: BreezeAuthenticatedLayout,
  pageTitle: 'Edit Band',
  props: ['errors', 'band', 'members', 'owners'],
  data() {
    return {
      urlWarn: false,
      syncing: false,
      showInstructions: false,
      activePanel: 'Details',
      loading: false,
      inviting: false,
      invite: {
        email: ''
      },
      creatingCalendar: false,
      grantingAccess: false,
      grantingAccessLoading: false,
      syncingMembers: false,
      calendarAccess: {
        email: '',
        role: 'writer'
      },
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
  watch: {
    form: {
      deep: true,
      handler() {

      }
    }
  },
  methods: {
    updateBand() {
      const bandID = this.band.id;
      this.$inertia.patch('/bands/' + bandID, this.form)
        .then(() => {
          this.loading = false;
        })
    },
    filter() {
      if (this.form.site_name.length > 0) {

        let message = this.form.site_name;
        let urlsafeName = message.replace(/[^aA-zZ0-9\-_]/gm, "")
        this.urlWarn = urlsafeName !== this.form.site_name
        this.form.site_name = urlsafeName;

      }
    },
    inviteOwner() {
      this.$inertia.post('/inviteOwner/' + this.band.id, {
        band_id: this.band.id,
        email: this.invite.email
      }, {
        onSuccess: () => {
        }
      })

    },
    inviteMember() {
      this.$inertia.post('/inviteMember/' + this.band.id, {
        band_id: this.band.id,
        email: this.invite.email
      }, {
        onSuccess: () => {
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
        confirmButtonText: 'Yes, delete it!'
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
    uploadLogo(event) {
      // console.log(event.files);
      this.$inertia.post('./uploadLogo', { 'logo': event.files }, {
        forceFormData: true,
        onSuccess: () => {
          this.$swal.fire("logo uploaded", "(no need to update)", "success");
          this.$refs.fileUpload.clear()
        }
      });
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
      this.$inertia.post('/bands/' + this.band.id + '/createCalendar', {}, {
        onSuccess: (page) => {
          this.creatingCalendar = false;
          // Update the form with the new calendar_id if it was created
          if (page.props.band && page.props.band.calendar_id) {
            this.form.calendar_id = page.props.band.calendar_id;
          }
        },
        onError: () => {
          this.creatingCalendar = false;
        }
      });
    },
    grantCalendarAccess() {
      this.grantingAccessLoading = true;
      this.$inertia.post(`/bands/${this.band.id}/grantCalendarAccess`, {
        email: this.calendarAccess.email,
        role: this.calendarAccess.role
      }, {
        onSuccess: () => {
          this.$swal.fire("Access Granted", `Calendar access granted to ${this.calendarAccess.email}`, "success");
          this.cancelGrantAccess();
        },
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
      this.calendarAccess.email = '';
      this.calendarAccess.role = 'writer';
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
