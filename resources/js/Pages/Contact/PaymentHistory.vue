<template>
  <ContactLayout>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
      <header class="bg-white dark:bg-gray-800 shadow">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
          <Link
            :href="route('portal.dashboard')"
            class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 mb-4 inline-flex items-center"
          >
            ← Back to Dashboard
          </Link>
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white mt-2">
            Payment History
          </h1>
        </div>
      </header>

      <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
          <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-md">
            <ul
              role="list"
              class="divide-y divide-gray-200 dark:divide-gray-700"
            >
              <li
                v-for="payment in payments"
                :key="payment.id"
                class="px-4 py-4 sm:px-6 hover:bg-gray-50 dark:hover:bg-gray-700"
              >
                <div class="flex items-center justify-between">
                  <div class="flex-1 min-w-0">
                    <h4 class="text-base font-medium text-gray-900 dark:text-white">
                      {{ payment.booking_name }}
                    </h4>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                      Event Date: {{ payment.booking_date }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                      Band: {{ payment.band_name }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                      Payment Date: {{ payment.date }}
                    </p>
                    
                    <!-- Payment Type Information -->
                    <div
                      v-if="payment.payment_type"
                      class="mt-2 flex items-center text-sm text-gray-600 dark:text-gray-400"
                    >
                      <svg
                        class="w-4 h-4 mr-1.5"
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
                      <span class="capitalize">
                        {{ payment.payment_type === 'stripe' ? 'Online Payment' : payment.payment_type === 'invoice' ? 'Invoice Payment' : payment.payment_type }}
                      </span>
                    </div>

                    <!-- Invoice Link -->
                    <a
                      v-if="payment.invoice && payment.invoice.stripe_url"
                      :href="payment.invoice.stripe_url"
                      target="_blank"
                      class="mt-2 inline-flex items-center text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300"
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
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        />
                      </svg>
                      View Invoice
                      <span
                        v-if="payment.invoice.convenience_fee"
                        class="ml-1 text-xs"
                      >
                        (includes convenience fee)
                      </span>
                    </a>
                  </div>
                  <div class="text-right">
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">
                      ${{ typeof payment.amount === 'string' ? payment.amount : Number(payment.amount).toFixed(2) }}
                    </p>
                    <span
                      :class="[
                        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                        payment.status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                        'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                      ]"
                    >
                      {{ payment.status.charAt(0).toUpperCase() + payment.status.slice(1) }}
                    </span>
                    <div
                      v-if="payment.name && payment.name !== 'Payment'"
                      class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                    >
                      {{ payment.name }}
                    </div>
                  </div>
                </div>
              </li>
              <li
                v-if="payments.length === 0"
                class="px-4 py-12 text-center text-gray-500 dark:text-gray-400"
              >
                No payment history found
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
  payments: Array,
  contact: Object,
});
</script>
