<template>
  <ContactLayout>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
      <!-- Header -->
      <header class="bg-white dark:bg-gray-800 shadow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
          <div class="flex items-center justify-between">
            <div>
              <Link
                :href="route('portal.dashboard')"
                class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 mb-2 inline-flex items-center"
              >
                <svg
                  class="w-4 h-4 mr-1"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 19l-7-7 7-7"
                  />
                </svg>
                Back to Dashboard
              </Link>
              <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                Invoices
              </h1>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                View and download your invoices
              </p>
            </div>
          </div>
        </div>
      </header>

      <!-- Main Content -->
      <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
          <!-- Invoices List -->
          <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-md">
            <ul
              role="list"
              class="divide-y divide-gray-200 dark:divide-gray-700"
            >
              <li
                v-for="invoice in invoices"
                :key="invoice.id"
                class="px-4 py-4 sm:px-6"
              >
                <div class="flex items-center justify-between">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                      <h4 class="text-lg font-medium text-gray-900 dark:text-white truncate">
                        {{ invoice.booking.name }}
                      </h4>
                      <span
                        :class="[
                          'ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                          invoice.status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                          invoice.status === 'open' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' :
                          'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                        ]"
                      >
                        {{ invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1) }}
                      </span>
                    </div>
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
                      Event Date: {{ invoice.booking.date }}
                    </div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                      Band: {{ invoice.booking.band_name }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                      Invoice created: {{ invoice.created_at }}
                    </div>
                    <div
                      v-if="invoice.paid_at"
                      class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                    >
                      Paid: {{ invoice.paid_at }}
                    </div>

                    <!-- Contract Information -->
                    <div
                      v-if="invoice.booking.contract"
                      class="mt-3 p-2 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800"
                    >
                      <div class="flex items-center text-xs">
                        <svg
                          class="h-4 w-4 text-blue-600 dark:text-blue-400 mr-1.5"
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
                        <span class="text-blue-900 dark:text-blue-100">
                          Contract {{ invoice.booking.contract.is_signed ? 'Signed' : 'Available' }}
                        </span>
                        <a
                          v-if="invoice.booking.contract.download_url"
                          :href="invoice.booking.contract.download_url"
                          class="ml-2 text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300"
                        >
                          View
                        </a>
                      </div>
                    </div>

                    <!-- Payment Information -->
                    <div
                      v-if="invoice.payment"
                      class="mt-2 text-xs text-gray-600 dark:text-gray-400"
                    >
                      <div class="flex items-center">
                        <svg
                          class="h-3 w-3 mr-1"
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
                        {{ invoice.payment.payment_type === 'stripe' ? 'Online Payment' : 'Payment' }}
                        on {{ invoice.payment.date }}
                      </div>
                    </div>
                  </div>
                  <div class="flex flex-col items-end ml-4">
                    <div class="text-right mb-3">
                      <div class="text-sm text-gray-500 dark:text-gray-400">
                        Amount: ${{ (invoice.base_amount / 100).toFixed(2) }}
                      </div>
                      <div
                        v-if="invoice.has_convenience_fee && invoice.fee_amount > 0"
                        class="text-sm text-gray-500 dark:text-gray-400"
                      >
                        Convenience Fee: ${{ (invoice.fee_amount / 100).toFixed(2) }}
                      </div>
                      <div class="text-lg font-medium text-gray-900 dark:text-white mt-1">
                        Total: ${{ (invoice.total_amount / 100).toFixed(2) }}
                      </div>
                    </div>
                    <a
                      v-if="invoice.stripe_url"
                      :href="invoice.stripe_url"
                      target="_blank"
                      class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                      <svg
                        class="w-4 h-4 mr-2"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                        />
                      </svg>
                      View Invoice
                    </a>
                  </div>
                </div>
              </li>
              <li
                v-if="invoices.length === 0"
                class="px-4 py-12 text-center text-gray-500 dark:text-gray-400"
              >
                <svg
                  class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500"
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
                <p class="mt-2">
                  No invoices found
                </p>
              </li>
            </ul>
          </div>
        </div>
      </main>
    </div>
  </ContactLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import ContactLayout from '@/Layouts/ContactLayout.vue';

defineProps({
  invoices: Array,
  contact: Object,
});
</script>
