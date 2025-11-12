<template>
  <ContactLayout>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
      <div class="max-w-md w-full space-y-8">
        <div class="text-center">
          <svg
            class="mx-auto h-16 w-16 text-green-400 dark:text-green-500"
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
          <h2 class="mt-6 text-3xl font-extrabold text-gray-900 dark:text-white">
            Payment Successful!
          </h2>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Thank you for your payment. Your transaction has been completed successfully.
          </p>
        </div>
        
        <div class="mt-8 text-center">
          <Link
            :href="route('portal.dashboard')"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600"
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

defineProps({
  session_id: String,
});
</script>
