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
          <!-- Outstanding Invoices Alert -->
          <div
            v-if="outstandingInvoices && outstandingInvoices.length > 0"
            class="mb-6 bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 dark:border-yellow-500 p-4 rounded-md"
          >
            <div class="flex">
              <div class="flex-shrink-0">
                <svg
                  class="h-5 w-5 text-yellow-400 dark:text-yellow-500"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                >
                  <path
                    fill-rule="evenodd"
                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                    clip-rule="evenodd"
                  />
                </svg>
              </div>
              <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                  You have {{ outstandingInvoices.length }} outstanding invoice{{ outstandingInvoices.length > 1 ? 's' : '' }}
                </h3>
                <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                  <ul class="list-disc pl-5 space-y-1">
                    <li
                      v-for="invoice in outstandingInvoices"
                      :key="invoice.id"
                      class="flex items-center justify-between"
                    >
                      <span>
                        {{ invoice.booking.name }} - ${{ (invoice.total_amount / 100).toFixed(2) }}
                        <span
                          v-if="invoice.has_convenience_fee"
                          class="text-xs"
                        >
                          (${{ (invoice.base_amount / 100).toFixed(2) }} + ${{ (invoice.fee_amount / 100).toFixed(2) }} fee)
                        </span>
                        <span class="text-xs ml-1">({{ invoice.created_at }})</span>
                      </span>
                      <a
                        v-if="invoice.stripe_url"
                        :href="invoice.stripe_url"
                        target="_blank"
                        class="ml-4 inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-yellow-700 bg-yellow-100 hover:bg-yellow-200 dark:text-yellow-200 dark:bg-yellow-800 dark:hover:bg-yellow-700"
                      >
                        View & Pay Invoice
                      </a>
                    </li>
                  </ul>
                </div>
                <div class="mt-4">
                  <Link
                    :href="route('portal.invoices')"
                    class="text-sm font-medium text-yellow-800 dark:text-yellow-200 hover:text-yellow-600 dark:hover:text-yellow-100"
                  >
                    View all invoices →
                  </Link>
                </div>
              </div>
            </div>
          </div>

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

                    <!-- Contract Information -->
                    <div
                      v-if="booking.contract"
                      class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-md border border-blue-200 dark:border-blue-800"
                    >
                      <div class="flex items-center justify-between">
                        <div class="flex items-center">
                          <svg
                            class="h-5 w-5 text-blue-600 dark:text-blue-400 mr-2"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                          >
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                          </svg>
                          <div>
                            <span class="text-sm font-medium text-blue-900 dark:text-blue-100">
                              Contract
                            </span>
                            <span
                              :class="[
                                'ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                (booking.contract.status === 'document.completed' || booking.contract.status === 'completed') ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                                (booking.contract.status === 'document.sent' || booking.contract.status === 'sent') ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' :
                                'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                              ]"
                            >
                              {{ formatContractStatus(booking.contract.status) }}
                            </span>
                          </div>
                        </div>
                        <a
                          v-if="booking.contract.download_url"
                          :href="booking.contract.download_url"
                          class="inline-flex items-center px-3 py-1 border border-blue-300 dark:border-blue-700 text-xs font-medium rounded-md text-blue-700 dark:text-blue-200 bg-white dark:bg-blue-900/50 hover:bg-blue-50 dark:hover:bg-blue-900/70"
                        >
                          <svg
                            class="h-4 w-4 mr-1"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                          >
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                          </svg>
                          Download Contract
                        </a>
                      </div>
                    </div>

                    <!-- Payment History -->
                    <div
                      v-if="booking.payments && booking.payments.length > 0"
                      class="mt-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700"
                    >
                      <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Payment History
                      </div>
                      <ul class="space-y-2">
                        <li
                          v-for="payment in booking.payments"
                          :key="payment.id"
                          class="text-xs text-gray-600 dark:text-gray-400"
                        >
                          <div class="flex justify-between items-start">
                            <div class="flex-1">
                              <div class="font-medium text-gray-900 dark:text-gray-100">
                                {{ payment.date }} - {{ payment.name || 'Payment' }}
                              </div>
                              <div
                                v-if="payment.payment_type"
                                class="flex items-center mt-0.5 space-x-2"
                              >
                                <span
                                  v-if="payment.payment_type === 'stripe'"
                                  class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300"
                                >
                                  <svg
                                    class="h-3 w-3 mr-0.5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                  >
                                    <path
                                      stroke-linecap="round"
                                      stroke-linejoin="round"
                                      stroke-width="2"
                                      d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                                    />
                                  </svg>
                                  Online Payment
                                </span>
                                <span
                                  v-else-if="payment.payment_type === 'invoice'"
                                  class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300"
                                >
                                  Invoice Payment
                                </span>
                                <span
                                  v-else
                                  class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                  {{ payment.payment_type }}
                                </span>
                              </div>
                              <a
                                v-if="payment.invoice && payment.invoice.stripe_url"
                                :href="payment.invoice.stripe_url"
                                target="_blank"
                                class="mt-1 inline-flex items-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300"
                              >
                                <svg
                                  class="h-3 w-3 mr-0.5"
                                  fill="none"
                                  stroke="currentColor"
                                  viewBox="0 0 24 24"
                                >
                                  <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                  />
                                </svg>
                                View Invoice
                              </a>
                            </div>
                            <span class="font-medium text-gray-900 dark:text-gray-100 ml-2">
                              ${{ payment.amount.toFixed(2) }}
                            </span>
                          </div>
                        </li>
                      </ul>
                      <div
                        v-if="booking.payments.length > 0"
                        class="mt-2 pt-2 border-t border-gray-300 dark:border-gray-600 flex justify-between text-sm"
                      >
                        <span class="font-medium text-gray-700 dark:text-gray-300">Total Paid:</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">
                          ${{ Number(booking.amount_paid).toFixed(2) }}
                        </span>
                      </div>
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

          <!-- Links -->
          <div class="mt-6 flex justify-center gap-6">
            <Link
              :href="route('portal.payment.history')"
              class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 font-medium"
            >
              View Payment History →
            </Link>
            <Link
              :href="route('portal.invoices')"
              class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 font-medium"
            >
              View Invoices →
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
  outstandingInvoices: Array,
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

const formatContractStatus = (status) => {
  const statusMap = {
    'document.completed': 'Signed',
    'document.sent': 'Sent',
    'document.draft': 'Draft',
    'document.viewed': 'Viewed',
    'completed': 'Signed',
    'sent': 'Sent',
    'pending': 'Pending',
  };
  return statusMap[status] || status.charAt(0).toUpperCase() + status.slice(1);
};
</script>
