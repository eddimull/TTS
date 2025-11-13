<template>
  <ContactLayout>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
      <div class="max-w-md w-full space-y-8">
        <div class="text-center">
          <svg
            class="mx-auto h-16 w-16 text-yellow-400 dark:text-yellow-500"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
          </svg>
          <h2 class="mt-6 text-3xl font-extrabold text-gray-900 dark:text-white">
            Payment Cancelled
          </h2>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Your payment was cancelled. No charges were made to your card.
          </p>
        </div>
        
        <div class="mt-8 space-y-4 text-center">
          <Link
            :href="route('portal.dashboard')"
            class="block w-full text-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600"
          >
            Return to Dashboard
          </Link>
        </div>
      </div>
    </div>
  </ContactLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import ContactLayout from '@/Layouts/ContactLayout.vue';
</script>
