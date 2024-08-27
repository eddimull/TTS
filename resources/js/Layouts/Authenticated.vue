<template>
  <div>
    <nav class="fixed z-50 w-full bg-white border-b border-gray-100">
      <!-- Primary Navigation Menu -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex">
            <!-- Logo -->
            <div class="flex-shrink-0 flex items-center">
              <Link :href="route('dashboard')">
                <breeze-application-logo class="block h-9 w-auto" />
              </Link>
            </div>

            <!-- Navigation Links -->
            <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
              <breeze-nav-link
                :href="route('dashboard')"
                :active="route().current('dashboard')"
              >
                Dashboard
              </breeze-nav-link>
              <breeze-nav-link
                :href="route('bands')"
                :active="route().current('bands')"
              >
                Bands
              </breeze-nav-link>
              <breeze-nav-link
                v-if="navigation && navigation.Bookings"
                :href="route('booking')"
                :active="route().current('booking')"
              >
                Booking
              </breeze-nav-link>                  
              <breeze-nav-link
                v-if="navigation && navigation.Events"
                :href="route('events')"
                :active="route().current('events')"
              >
                Events
              </breeze-nav-link> 
              <breeze-nav-link
                v-if="navigation && navigation.Proposals"
                :href="route('proposals')"
                :active="route().current('proposals')"
              >
                Proposals
              </breeze-nav-link>       
              <breeze-nav-link
                v-if="navigation && navigation.Invoices"
                :href="route('finances')"
                :active="route().current('finances')"
              >
                Finances
              </breeze-nav-link>                             
              <breeze-nav-link
                v-if="navigation && navigation.Colors"
                :href="route('colors')"
                :active="route().current('colors')"
              >
                Colors
              </breeze-nav-link>        
              <breeze-nav-link
                v-if="navigation && navigation.Charts"
                :href="route('charts')"
                :active="route().current('charts')"
              >
                Charts
              </breeze-nav-link>             
            </div>
          </div>
          <!-- notifications and username -->
          <div class="hidden sm:flex sm:items-center sm:ml-6">
            <div class="ml-3 relative">
              <breeze-dropdown
                align="right"
                width="56"
              >
                <template #trigger>
                  <span class="inline-flex rounded-md">
                    <button
                      type="button"
                      class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150"
                      @click="markSeen"
                    >
                      <span
                        v-if="unseenNotifications > 0"
                        class="absolute top-0 right-0.5 rounded-full flex items-center justify-center bg-gradient-to-r from-purple-400 via-pink-500 to-red-500 text-white h-4 w-f px-1 py-1.5"
                      >{{ unseenNotifications }}</span>
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-6 w-6"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                        />
                      </svg>
                    </button>
                  </span>
                </template>
                <template #content>
                  <div class="flex flex-col max-h-72">
                    <div class="overflow-y-auto flex-auto">
                      <notification-link
                        v-for="(notification,index) in notifications"
                        :key="index"
                        :unread="notification.read_at === null"
                        :href="route(notification.data.route,notification.data.routeParams == null ? '' : !notification.data.routeParams.split ? '' : notification.data.routeParams.split(','))"
                        method="get"
                        as="button"
                        @click="markAsRead(notification)"
                      >
                        {{ notification.data.text }}
                      </notification-link>
                    </div>
                  </div>
                  <button
                    :class="['block', 'w-full', 'px-4', 'py-2','hover:underline', 'text-center', 'text-sm', 'border-t-2', 'leading-5', 'text-blue-700', 'focus:outline-none','focus:bg-gray-100','transition duration-150','ease-in-out']"
                    @click="markAllAsRead()"
                  >
                    Mark all as read
                  </button>
                </template>
              </breeze-dropdown>
            </div>
            <!-- Settings Dropdown -->
            <div class="ml-3 relative">
              <breeze-dropdown
                align="right"
                width="48"
              >
                <template #trigger>
                  <span class="inline-flex rounded-md">
                    <button
                      type="button"
                      class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150"
                    >
                      {{ $page.props.auth.user.name }}

                      <svg
                        class="ml-2 -mr-0.5 h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                      >
                        <path
                          fill-rule="evenodd"
                          d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                          clip-rule="evenodd"
                        />
                      </svg>
                    </button>
                  </span>
                </template>
                                       
                <template #content>
                  <breeze-dropdown-link
                    :href="route('account')"
                    method="get"
                    as="button"
                  >
                    Account
                  </breeze-dropdown-link>
                  <breeze-dropdown-link
                    :href="route('logout')"
                    method="post"
                    as="button"
                  >
                    Log Out
                  </breeze-dropdown-link>
                </template>
              </breeze-dropdown>
            </div>
          </div>

          <!-- notifications on mobile -->
          <div class="flex items-center sm:hidden">
            <breeze-dropdown
              align="full"
              width="full"
            >
              <template #trigger>
                <span class="inline-flex rounded-md">
                  <button
                    type="button"
                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150"
                    @click="markSeen"
                  >
                    <span
                      v-if="unseenNotifications > 0"
                      class="absolute top-0 right-0.5 rounded-full flex items-center justify-center bg-gradient-to-r from-purple-400 via-pink-500 to-red-500 text-white h-4 w-f px-1 py-1.5"
                    >{{ unseenNotifications }}</span>
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      class="h-6 w-6"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                      />
                    </svg>
                  </button>
                </span>
              </template>
              <template #content>
                <div class="flex flex-col flex-grow max-h-72">
                  <div class="overflow-y-auto flex-auto">
                    <notification-link
                      v-for="(notification,index) in notifications"
                      :key="index"
                      :unread="notification.read_at === null"
                      :href="route(notification.data.route,notification.data.routeParams == null ? '' : !notification.data.routeParams.split ? '' : notification.data.routeParams.split(','))"
                      method="get"
                      as="button"
                      @click="markAsRead(notification)"
                    >
                      {{ notification.data.text }}
                    </notification-link>
                  </div>
                </div>
                <button
                  :class="['block', 'w-full', 'px-4', 'py-2','hover:underline', 'text-center', 'text-sm', 'border-t-2', 'leading-5', 'text-blue-700', 'focus:outline-none','focus:bg-gray-100','transition duration-150','ease-in-out']"
                  @click="markAllAsRead()"
                >
                  Mark all as read
                </button>
              </template>
            </breeze-dropdown>
          </div>
          <!-- Hamburger -->
          <div class="-mr-2 flex items-center sm:hidden">
            <button
              class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
              @click="showingNavigationDropdown = ! showingNavigationDropdown"
            >
              <svg
                class="h-6 w-6"
                stroke="currentColor"
                fill="none"
                viewBox="0 0 24 24"
              >
                <path
                  :class="{'hidden': showingNavigationDropdown, 'inline-flex': ! showingNavigationDropdown }"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"
                />
                <path
                  :class="{'hidden': ! showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>
      <Toast />
      <Toast
        position="top-left"
        group="tl"
      />
      <Toast
        position="bottom-left"
        group="bl"
      />
      <Toast
        position="bottom-right"
        group="br"
      />

      <Toast
        position="bottom-center"
        group="bc"
      />
      <!-- Responsive Navigation Menu -->
      <div
        :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}"
        class="sm:hidden"
      >
        <div class="pt-2 pb-3 space-y-1">
          <breeze-responsive-nav-link
            :href="route('dashboard')"
            :active="route().current('dashboard')"
          >
            Dashboard
          </breeze-responsive-nav-link>
          <breeze-responsive-nav-link
            :href="route('bands')"
            :active="route().current('bands')"
          >
            Bands
          </breeze-responsive-nav-link>
          <breeze-responsive-nav-link
            v-if="navigation && navigation.Events"
            :href="route('events')"
            :active="route().current('events')"
          >
            Events
          </breeze-responsive-nav-link> 
          <breeze-responsive-nav-link
            v-if="navigation && navigation.Proposals"
            :href="route('proposals')"
            :active="route().current('proposals')"
          >
            Proposals
          </breeze-responsive-nav-link> 
          <breeze-responsive-nav-link
            v-if="navigation && navigation.Bookings"
            :href="route('booking')"
            :active="route().current('booking')"
          >
            Booking
          </breeze-responsive-nav-link>
          <breeze-responsive-nav-link
            v-if="navigation && navigation.Events"
            class="pl-4"
            :href="route('questionnaire')"
            :active="route().current('questionnaire')"
          >
            Questionnaires
          </breeze-responsive-nav-link> 
          <breeze-responsive-nav-link
            v-if="navigation && navigation.Invoices"
            :href="route('finances')"
            :active="route().current('finances')"
          >
            Finances
          </breeze-responsive-nav-link> 
          <breeze-responsive-nav-link
            v-if="navigation && navigation.Colors"
            :href="route('colors')"
            :active="route().current('colors')"
          >
            Colors
          </breeze-responsive-nav-link>   

          <breeze-responsive-nav-link
            v-if="navigation && navigation.Charts"
            :href="route('charts')"
            :active="route().current('charts')"
          >
            Charts
          </breeze-responsive-nav-link>   
        </div> 

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
          <div class="flex items-center px-4">
            <div class="font-medium text-base text-gray-800">
              {{ $page.props.auth.user.name }} 
            </div>
            <div class="px-2 font-medium text-sm text-gray-500">
              {{ $page.props.auth.user.email }}
            </div>
          </div>

          <div class="mt-3 space-y-1">
            <breeze-dropdown-link
              :href="route('account')"
              method="get"
              as="button"
            >
              Account
            </breeze-dropdown-link>
            <breeze-responsive-nav-link
              :href="route('logout')"
              method="post"
              as="button"
            >
              Log Out
            </breeze-responsive-nav-link>
          </div>
        </div>
      </div>
    </nav>
    <div class="pt-16 min-h-screen bg-gradient-to-r from-blue-800 to-blue-200">
      <!-- Page Heading -->
      <header
        v-if="$slots.header"
        class="bg-white shadow"
      >
        <div class="max-w-7xl mx-auto py-2 px-4 sm:px-4 lg:px-8">
          <slot name="header" />
        </div>
      </header>

      <!-- Page Content -->
      <main class="rounded-t-lg relative border-t border-l border-r border-gray-400 px-3 py-4 flex justify-center">
        <!-- <toast
          :success-message="$page.props.successMessage"
          :errors="$page.props.errors"
        /> -->
        <slot />
        <div class="layout-main-container">
          <slot name="content" />
        </div>
      </main>
    </div>
  </div>
  <!-- {{ $page.props.auth.user }} -->
</template>

<script>
    import BreezeApplicationLogo from '@/Components/ApplicationLogo'
    import BreezeDropdown from '@/Components/Dropdown'
    import BreezeDropdownLink from '@/Components/DropdownLink'
    import NotificationLink from '@/Components/NotificationDropdown'
    import BreezeNavLink from '@/Components/NavLink'
    import BreezeResponsiveNavLink from '@/Components/ResponsiveNavLink'
    import Toast from 'primevue/toast';
    import axios from 'axios';
    import { mapState, mapActions } from 'vuex'

    export default {
        components: {
            BreezeApplicationLogo,
            BreezeDropdown,
            BreezeDropdownLink,
            BreezeNavLink,
            BreezeResponsiveNavLink,
            NotificationLink,
            Toast
        },

        data() {
          return {
              showingNavigationDropdown: false,
              }
        },
        computed: {
            ...mapState('user', ['navigation', 'notifications']),
            unseenNotifications() {
                return !this.notifications ? 0 : this.notifications.filter(notification => notification.seen_at === null).length
            }
        },        
        watch: {
          $page: {
            handler:function(val,oldval){
             
              if(this.$page.props.errors !== null && Object.keys(this.$page.props.errors).length > 0)
              {
                const errors = this.$page.props.errors;
                for(const i in errors){
                  
                  this.$toast.add({severity:'error', summary: 'Error', detail:errors[i], life: 3000});		
                };			
              }

              if(this.$page.props.successMessage !== null && Object.keys(this.$page.props.successMessage).length > 0)
              {
                const successMessage = this.$page.props.successMessage;
                
                  this.$toast.add({severity:'success', summary: 'Success', detail:successMessage, life: 3000});		
                
              }
              
            },
            deep:true
          }
        },
        created:async function(){
            // this.unseenNotifications = this.$page.props.auth.user.notifications.filter(notification=>notification.seen_at === null).length
            // this.getNotifications()
            this.fetchUserData()
        },
        methods: {
          ...mapActions('user', ['fetchNavigation', 'fetchNotifications', 'markAllNotificationsAsRead', 'markNotificationAsRead', 'markNotificationsAsSeen']),

          fetchUserData() {
              this.fetchNavigation()
              this.fetchNotifications()
          },

          markAllAsRead() {
              this.markAllNotificationsAsRead()
          },

          markAsRead(notification) {
              this.markNotificationAsRead(notification.id)
          },

          markSeen() {
              this.markNotificationsAsSeen()
          }
        }
    }
</script>
