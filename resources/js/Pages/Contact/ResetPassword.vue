<template>
  <ContactLayout>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
      <div class="max-w-md w-full space-y-8">
        <div>
          <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-white">
            Reset your password
          </h2>
          <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
            Enter your new password below
          </p>
        </div>

        <form
          class="mt-8 space-y-6"
          @submit.prevent="submit"
        >
          <div
            v-if="status"
            class="rounded-md bg-green-50 dark:bg-green-900/30 p-4"
          >
            <p class="text-sm font-medium text-green-800 dark:text-green-200">
              {{ status }}
            </p>
          </div>

          <div class="space-y-4">
            <div>
              <label
                for="email"
                class="sr-only"
              >Email address</label>
              <input
                id="email"
                v-model="form.email"
                type="email"
                required
                readonly
                class="appearance-none relative block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-700 rounded-md focus:outline-none sm:text-sm"
                placeholder="Email address"
              >
            </div>

            <div>
              <label
                for="password"
                class="sr-only"
              >New Password</label>
              <input
                id="password"
                v-model="form.password"
                type="password"
                required
                class="appearance-none relative block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white dark:bg-gray-800 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                placeholder="New password"
              >
              <div
                v-if="form.errors.password"
                class="text-red-500 dark:text-red-400 text-sm mt-1"
              >
                {{ form.errors.password }}
              </div>
            </div>

            <div>
              <label
                for="password_confirmation"
                class="sr-only"
              >Confirm Password</label>
              <input
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                required
                class="appearance-none relative block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white dark:bg-gray-800 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                placeholder="Confirm password"
              >
            </div>
          </div>

          <div class="flex items-center justify-between">
            <Link
              :href="route('portal.login')"
              class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300"
            >
              Back to login
            </Link>
          </div>

          <div>
            <button
              type="submit"
              :disabled="form.processing"
              class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
              <span v-if="form.processing">Resetting password...</span>
              <span v-else>Reset password</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </ContactLayout>
</template>

<script setup>
import { useForm, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ContactLayout from '@/Layouts/ContactLayout.vue';

const props = defineProps({
  token: String,
  email: String,
});

const page = usePage();
const status = computed(() => page.props.flash?.status || page.props.status);

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
});

const submit = () => {
  form.post(route('portal.password.update'));
};
</script>
