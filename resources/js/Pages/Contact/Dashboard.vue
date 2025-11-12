<template>
  <ContactLayout>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
      <!-- Header -->
      <header class="bg-white dark:bg-gray-800 shadow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            Welcome, {{ portal.name }}
          </h1>
          <form @submit.prevent="logout">
            <button
              type="submit"
              class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600"
            >
              Logout
            </button>
          </form>
        </div>
      </header>

      <!-- Main Content -->
      <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
          <!-- Summary Cards -->
          <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
              <div class="p-5">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <svg
                      class="h-6 w-6 text-gray-400 dark:text-gray-500"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                      />
                    </svg>
                  </div>
                  <div class="ml-5 w-0 flex-1">
                    <dl>
                      <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Total Bookings
                      </dt>
                      <dd class="text-lg font-medium text-gray-900 dark:text-white">
                        {{ bookings.length }}
                      </dd>
                    </dl>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
              <div class="p-5">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <svg
                      class="h-6 w-6 text-green-400 dark:text-green-500"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                      />
                    </svg>
                  </div>
                  <div class="ml-5 w-0 flex-1">
                    <dl>
                      <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Paid Bookings
                      </dt>
                      <dd class="text-lg font-medium text-gray-900 dark:text-white">
                        {{ paidBookings }}
                      </dd>
                    </dl>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
              <div class="p-5">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <svg
                      class="h-6 w-6 text-yellow-400 dark:text-yellow-500"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                      />
                    </svg>
                  </div>
                  <div class="ml-5 w-0 flex-1">
                    <dl>
                      <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Outstanding Balance
                      </dt>
                      <dd class="text-lg font-medium text-gray-900 dark:text-white">
                        ${{ totalOutstanding }}
                      </dd>
                    </dl>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Bookings List -->
          <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-md">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                Your Bookings
              </h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                View your bookings and make payments on outstanding balances
              </p>
            </div>
            <ul
              role="list"
              class="divide-y divide-gray-200 dark:divide-gray-700"
            >
              <li
                v-for="booking in bookings"
                :key="booking.id"
                class="px-4 py-4 sm:px-6 hover:bg-gray-50 dark:hover:bg-gray-700"
              >
                <div class="flex items-center justify-between">
                  <div class="flex-1 min-w-0">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-white truncate">
                      {{ booking.name }}
                    </h4>
                    <div class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400">
                      <svg
                        class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400 dark:text-gray-500"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                      </svg>
                      {{ booking.date }} at {{ booking.start_time }}
                    </div>
                    <div class="mt-1 flex items-center text-sm text-gray-500 dark:text-gray-400">
                      <svg
                        class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400 dark:text-gray-500"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                        />
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                        />
                      </svg>
                      {{ booking.venue_name }}
                    </div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                      Band: {{ booking.band_name }}
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                      <span
                        :class="[
                          'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                          booking.status === 'confirmed' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                          booking.status === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' :
                          booking.status === 'cancelled' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' :
                          'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                        ]"
                      >
                        {{ booking.status.charAt(0).toUpperCase() + booking.status.slice(1) }}
                      </span>
                      <span class="text-xs text-gray-500 dark:text-gray-400">
                        since {{ booking.status_changed_at }}
                      </span>
                    </div>
                  </div>
                  <div class="flex flex-col items-end ml-4">
                    <div class="flex items-center">
                      <span
                        :class="[
                          'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                          booking.is_paid ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                        ]"
                      >
                        {{ booking.is_paid ? 'Paid' : 'Balance Due' }}
                      </span>
                    </div>
                    <div class="mt-2 text-right">
                      <div class="text-sm text-gray-900 dark:text-gray-100">
                        Total: ${{ typeof booking.price === 'string' ? booking.price : Number(booking.price).toFixed(2) }}
                      </div>
                      <div class="text-sm text-gray-500 dark:text-gray-400">
                        Paid: ${{ Number(booking.amount_paid).toFixed(2) }}
                      </div>
                      <div
                        v-if="booking.has_balance"
                        class="text-sm font-medium text-red-600 dark:text-red-400"
                      >
                        Due: ${{ Number(booking.amount_due).toFixed(2) }}
                      </div>
                    </div>
                    <Link
                      v-if="booking.has_balance && booking.status === 'confirmed'"
                      :href="route('portal.booking.payment', booking.id)"
                      class="mt-3 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                      Make Payment
                    </Link>
                    <div
                      v-else-if="booking.has_balance && booking.status === 'pending'"
                      class="mt-3 text-xs text-gray-500 dark:text-gray-400 text-right max-w-xs"
                    >
                      Payment is not available for pending bookings. The booking must be confirmed first.
                    </div>
                  </div>
                </div>
              </li>
              <li
                v-if="bookings.length === 0"
                class="px-4 py-12 text-center text-gray-500 dark:text-gray-400"
              >
                No bookings found
              </li>
            </ul>
          </div>

          <!-- Payment History Link -->
          <div class="mt-6 text-center">
            <Link
              :href="route('portal.payment.history')"
              class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 font-medium"
            >
              View Payment History →
            </Link>
          </div>
        </div>
      </main>
    </div>
  </ContactLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import ContactLayout from '@/Layouts/ContactLayout.vue';

const props = defineProps({
  portal: Object,
  bookings: Array,
});

const paidBookings = computed(() => {
  return props.bookings.filter(b => b.is_paid).length;
});

const totalOutstanding = computed(() => {
  
  return props.bookings.reduce((sum, b) => parseFloat(sum) + (parseFloat(b.amount_due) || 0), 0);
});

const logout = () => {
  router.post(route('portal.logout'));
};
</script>
