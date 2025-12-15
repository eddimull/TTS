<template>
  <div>
    <nav
      class="fixed z-[999] w-full bg-white dark:bg-slate-700 border-b border-gray-100"
    >
      <!-- Primary Navigation Menu -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex">
            <!-- Logo -->
            <div class="flex-shrink-0 flex items-center">
              <Link :href="route('dashboard')">
                <span><breeze-application-logo
                  class="block h-9 w-auto fill-current dark:text-white"
                /></span>
              </Link>
            </div>

            <!-- Navigation Links -->
            <div
              class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex transition-opacity duration-300 ease-in-out"
              :class="{ 'opacity-0 pointer-events-none': searchActive }"
            >
              <breeze-nav-link
                :href="route('dashboard')"
                :active="route().current('dashboard')"
              >
                Dashboard
              </breeze-nav-link>
              <breeze-nav-link
                v-if="navigation && navigation.Bookings && navigation.Bookings.read"
                :href="route('Bookings Home')"
                :active="
                  route().current().indexOf('Booking') > -1
                "
              >
                Booking
              </breeze-nav-link>
              <breeze-nav-link
                v-if="navigation && navigation.Events && navigation.Events.read"
                :href="route('events')"
                :active="route().current('events')"
              >
                Events
              </breeze-nav-link>
              <breeze-nav-link
                v-if="navigation && navigation.Rehearsals && navigation.Rehearsals.read"
                :href="route('rehearsal-schedules.index')"
                :active="route().current().includes('rehearsal')"
              >
                Rehearsals
              </breeze-nav-link>
              <breeze-nav-link
                v-if="navigation && navigation.Invoices && navigation.Invoices.read"
                :href="route('finances')"
                :active="
                  route().current('finances') ||
                    $page.props?.url?.includes('/finances')
                "
              >
                Finances
              </breeze-nav-link>
              <breeze-nav-link
                v-if="navigation && navigation.Charts && navigation.Charts.read"
                :href="route('charts')"
                :active="route().current('charts')"
              >
                Charts
              </breeze-nav-link>
            </div>
          </div>

          <!-- Search Toggle Button -->
          <div class="hidden sm:flex sm:items-center sm:ml-6">
            <button
              class="p-2 rounded-md text-gray-400 dark:text-gray-300 hover:text-gray-500 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
              :class="{ 'text-indigo-600 dark:text-indigo-400': searchActive }"
              @click="toggleSearch"
            >
              <svg
                class="h-5 w-5"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
              >
                <path
                  fill-rule="evenodd"
                  d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                  clip-rule="evenodd"
                />
              </svg>
            </button>
          </div>

          <!-- Search Overlay -->
          <div
                        
            class="absolute inset-0 bg-white dark:bg-slate-700 z-10 flex items-center px-4 sm:px-6 lg:px-8 transition-all duration-300 ease-in-out transform"
            :class="{
              'translate-y-0 opacity-100': searchActive,
              '-translate-y-full opacity-0': !searchActive
            }"
          >
            <div class="flex-1 max-w-2xl mx-auto">
              <SearchComponent
                :is-overlay="true"
                @close="closeSearch"
              />
            </div>
          </div>

          <!-- notifications and username -->
          <div
            class="hidden sm:flex sm:items-center sm:ml-6 transition-opacity duration-300 ease-in-out"
            :class="{ 'opacity-0 pointer-events-none': searchActive }"
          >
            <div class="ml-3 relative">
              <breeze-dropdown
                align="right"
                width="56"
              >
                <template #trigger>
                  <span class="inline-flex rounded-md">
                    <button
                      type="button"
                      class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-50 bg-white dark:bg-slate-700 hover:text-gray-700 dark:hover:text-blue-100  focus:outline-none transition ease-in-out duration-150"
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
                        v-for="(
                          notification, index
                        ) in notifications"
                        :key="index"
                        :unread="
                          notification.read_at ===
                            null
                        "
                        :href="
                          route(
                            notification.data.route,
                            notification.data
                              .routeParams || {}
                          )
                        "
                        method="get"
                        as="button"
                        @click="
                          markAsRead(notification)
                        "
                      >
                        {{ notification.data.text }}
                      </notification-link>
                    </div>
                  </div>
                  <button
                    :class="[
                      'block',
                      'w-full',
                      'px-4',
                      'py-2',
                      'hover:underline',
                      'text-center',
                      'text-sm',
                      'border-t-2',
                      'leading-5',
                      'text-blue-700',
                      'focus:outline-none',
                      'focus:bg-gray-100',
                      'transition duration-150',
                      'ease-in-out',
                    ]"
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
                      class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-50 bg-white dark:bg-slate-700 hover:text-gray-700 dark:hover:text-blue-100 focus:outline-none transition ease-in-out duration-150"
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
                    :href="route('bands')"
                    method="get"
                    as="button"
                  >
                    Bands
                  </breeze-dropdown-link>
                  <breeze-dropdown-link
                    :href="route('stats')"
                    method="get"
                    as="button"
                  >
                    Stats
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
                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-300 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-100 focus:outline-none transition ease-in-out duration-150"
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
                <div
                  class="flex flex-col flex-grow max-h-72 bg-white dark:bg-gray-800"
                >
                  <div class="overflow-y-auto flex-auto">
                    <notification-link
                      v-for="(
                        notification, index
                      ) in notifications"
                      :key="index"
                      :unread="
                        notification.read_at === null
                      "
                      :href="
                        route(
                          notification.data.route,
                          notification.data
                            .routeParams || {}
                        )
                      "
                      method="get"
                      as="button"
                      class="dark:hover:bg-gray-700"
                      @click="markAsRead(notification)"
                    >
                      <span class="dark:text-gray-300">{{
                        notification.data.text
                      }}</span>
                    </notification-link>
                  </div>
                </div>
                <button
                  :class="[
                    'block',
                    'w-full',
                    'px-4',
                    'py-2',
                    'hover:underline',
                    'text-center',
                    'text-sm',
                    'border-t-2',
                    'dark:border-gray-600',
                    'leading-5',
                    'text-blue-700',
                    'dark:text-blue-400',
                    'focus:outline-none',
                    'hover:bg-gray-100',
                    'dark:hover:bg-gray-700',
                    'transition duration-150',
                    'ease-in-out',
                  ]"
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
              class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-50 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
              @click="
                showingNavigationDropdown =
                  !showingNavigationDropdown
              "
            >
              <svg
                class="h-6 w-6"
                stroke="currentColor"
                fill="none"
                viewBox="0 0 24 24"
              >
                <path
                  :class="{
                    hidden: showingNavigationDropdown,
                    'inline-flex':
                      !showingNavigationDropdown,
                  }"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"
                  class="transform transition-transform duration-300"
                />
                <path
                  :class="{
                    hidden: !showingNavigationDropdown,
                    'inline-flex':
                      showingNavigationDropdown,
                  }"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                  class="transform transition-transform duration-300"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Toast notifications -->
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
        :class="{
          'transform-gpu transition-all duration-300 ease-in-out max-h-[1000px] opacity-100':
            showingNavigationDropdown,
          'transform-gpu transition-all duration-300 ease-in-out max-h-0 opacity-0':
            !showingNavigationDropdown,
        }"
        class="sm:hidden overflow-hidden"
      >
        <div class="pt-2 pb-3 space-y-1">
          <!-- Mobile Search -->
          <div class="px-4 py-2">
            <SearchComponent />
          </div>
                    
          <breeze-responsive-nav-link
            :href="route('dashboard')"
            :active="route().current('dashboard')"
          >
            Dashboard
          </breeze-responsive-nav-link>
          <breeze-responsive-nav-link
            v-if="navigation && navigation.Events && navigation.Events.read"
            :href="route('events')"
            :active="route().current('events')"
          >
            Events
          </breeze-responsive-nav-link>
          <breeze-responsive-nav-link
            v-if="navigation && navigation.Rehearsals && navigation.Rehearsals.read"
            :href="route('rehearsal-schedules.index')"
            :active="route().current().includes('rehearsal')"
          >
            Rehearsals
          </breeze-responsive-nav-link>
          <breeze-responsive-nav-link
            v-if="navigation && navigation.Bookings && navigation.Bookings.read"
            :href="route('Bookings Home')"
            :active="route().current('Bookings Home')"
          >
            Booking
          </breeze-responsive-nav-link>
          <breeze-responsive-nav-link
            v-if="navigation && navigation.Invoices && navigation.Invoices.read"
            :href="route('finances')"
            :active="route().current('finances')"
          >
            Finances
          </breeze-responsive-nav-link>

          <breeze-responsive-nav-link
            v-if="navigation && navigation.Charts && navigation.Charts.read"
            :href="route('charts')"
            :active="route().current('charts')"
          >
            Charts
          </breeze-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
          <div class="flex items-center px-4">
            <div
              class="font-medium text-base text-gray-800 dark:text-gray-200"
            >
              {{ $page.props.auth.user.name }}
            </div>
            <div
              class="px-2 font-medium text-sm text-gray-500 dark:text-gray-50"
            >
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
            <breeze-dropdown-link
              :href="route('bands')"
              method="get"
              as="button"
            >
              Bands
            </breeze-dropdown-link>
            <breeze-dropdown-link
              :href="route('stats')"
              method="get"
              as="button"
            >
              Stats
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
    <div
      class="pt-16 min-h-screen bg-gradient-to-r layout-background dark:text-white"
    >
      <!-- Page Heading -->
      <header
        v-if="$slots.header"
        class="sticky top-16 z-40 bg-white dark:bg-slate-700 dark:text-white shadow"
      >
        <div class="max-w-7xl mx-auto py-2 px-4 sm:px-4 lg:px-8">
          <slot name="header" />
        </div>
      </header>

      <header
        v-else
        class="sticky top-16 z-40 bg-white dark:bg-slate-700 shadow"
      >
        <div class="max-w-7xl mx-auto py-2 px-4 sm:px-4 lg:px-8">
          <h2
            class="font-semibold text-xl text-gray-800 dark:text-gray-50 leading-tight"
          >
            {{ computedPageTitle }}
            {{ navSuffix ?? `- ${navSuffix}` }}
          </h2>
        </div>
      </header>

      <!-- Page Content -->
      <main
        class="rounded-t-lg relative border-t border-l border-r border-gray-400 px-1 sm:px-3 py-0 sm:py-4 flex justify-center"
      >
        <slot />
        <div class="layout-main-container">
          <slot name="content" />
        </div>
      </main>
    </div>
  </div>
</template>

<script>
import BreezeApplicationLogo from "@/Components/ApplicationLogo";
import BreezeDropdown from "@/Components/Dropdown";
import BreezeDropdownLink from "@/Components/DropdownLink";
import NotificationLink from "@/Components/NotificationDropdown";
import BreezeNavLink from "@/Components/NavLink";
import BreezeResponsiveNavLink from "@/Components/ResponsiveNavLink";
import SearchComponent from "@/Components/Search/SearchComponent.vue";
import Toast from "primevue/toast";
import axios from "axios";
import { mapState, mapActions } from "vuex";
import { router } from "@inertiajs/vue3";

export default {
    components: {
        BreezeApplicationLogo,
        BreezeDropdown,
        BreezeDropdownLink,
        BreezeNavLink,
        BreezeResponsiveNavLink,
        NotificationLink,
        SearchComponent,
        Toast,
    },
    props: {
        navSuffix: {
            type: String,
            default: "",
            required: false,
        },
        pageTitle: {
            type: String,
            default: "",
            required: false,
        },
    },
    data() {
        return {
            showingNavigationDropdown: false,
            searchActive: false,
        };
    },
    computed: {
        ...mapState("user", ["navigation", "notifications"]),
        unseenNotifications() {
            return !this.notifications
                ? 0
                : this.notifications.filter(
                      (notification) => notification.seen_at === null
                  ).length;
        },
        computedPageTitle() {
            if (this.pageTitle) {
                return this.pageTitle;
            }
            // Check if the current page component has pageTitle option
            if (this.$page.component && this.$page.component.pageTitle) {
                return this.$page.component.pageTitle;
            }
            // Then check if the slot component has pageTitle option
            if (this.$slots.default && this.$slots.default()[0]?.type?.pageTitle) {
                return this.$slots.default()[0].type.pageTitle;
            }
            // Fall back to route-based title
            return route().current().charAt(0).toUpperCase() +
                   route().current().slice(1).replace(".", " - ");
        },
        firstBandId() {
            return this.$page.props.auth?.user?.bands?.[0]?.id || null;
        }
    },
    onUpdated() {
        this.toast();
    },
    watch: {
        $page: {
            handler: function (val, oldval) {
                this.toast();
            },
            deep: true,
        },
    },
    created: async function () {
        this.fetchUserData();
    },
    methods: {
        ...mapActions("user", [
            "fetchNavigation",
            "fetchNotifications",
            "markAllNotificationsAsRead",
            "markNotificationAsRead",
            "markNotificationsAsSeen",
        ]),

        fetchUserData() {
            this.fetchNavigation();
            this.fetchNotifications();
        },

        markAllAsRead() {
            this.markAllNotificationsAsRead();
        },

        markAsRead(notification) {
            this.markNotificationAsRead(notification.id);
        },

        markSeen() {
            this.markNotificationsAsSeen();
        },

        toggleSearch() {
            this.searchActive = !this.searchActive;
            if (this.searchActive) {
            const input = document.getElementById('everythingSearchInput');
            if (input) {
                try {
                    input.focus();
                        input.click();
                    } catch (e) {
                        console.error('Direct DOM focus error:', e);
                    }
                }
            }
        },

        closeSearch() {
            this.searchActive = false;
        },

        toast() {
            if (
                this.$page.props.errors !== null &&
                Object.keys(this.$page.props.errors).length > 0
            ) {
                const errors = this.$page.props.errors;
                for (const i in errors) {
                    this.$toast.add({
                        severity: "error",
                        summary: "Error",
                        detail: errors[i],
                        life: 30000,
                    });
                }
            }

            if (
                this.$page.props.successMessage !== null &&
                Object.keys(this.$page.props.successMessage).length > 0
            ) {
                const successMessage = this.$page.props.successMessage;
                this.$toast.add({
                    severity: "success",
                    summary: "Success",
                    detail: successMessage,
                    life: 3000,
                });
            }

            if (
                this.$page.props.warningMessage !== null &&
                Object.keys(this.$page.props.warningMessage).length > 0
            ) {
                const warningMessage = this.$page.props.warningMessage;
                this.$toast.add({
                    severity: "warn",
                    summary: "Warning",
                    detail: warningMessage,
                    life: 3000,
                });
            }
        },
    },
};
</script>
