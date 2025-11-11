<template>
  <ContactLayout>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
      <div class="max-w-md w-full space-y-8">
        <div>
          <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-white">
            Client Portal
          </h2>
          <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
            Log in to view and pay for your bookings
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

          <div class="rounded-md shadow-sm -space-y-px">
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
                class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white dark:bg-gray-800 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                placeholder="Email address"
              >
              <div
                v-if="form.errors.email"
                class="text-red-500 dark:text-red-400 text-sm mt-1"
              >
                {{ form.errors.email }}
              </div>
            </div>
            <div>
              <label
                for="password"
                class="sr-only"
              >Password</label>
              <input
                id="password"
                v-model="form.password"
                type="password"
                required
                class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white bg-white dark:bg-gray-800 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                placeholder="Password"
              >
              <div
                v-if="form.errors.password"
                class="text-red-500 dark:text-red-400 text-sm mt-1"
              >
                {{ form.errors.password }}
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <input
                id="remember"
                v-model="form.remember"
                type="checkbox"
                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-800"
              >
              <label
                for="remember"
                class="ml-2 block text-sm text-gray-900 dark:text-gray-300"
              >
                Remember me
              </label>
            </div>

            <div class="text-sm">
              <Link
                :href="route('portal.password.request')"
                class="font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300"
              >
                Forgot your password?
              </Link>
            </div>
          </div>

          <div>
            <button
              type="submit"
              :disabled="form.processing"
              class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
              <span v-if="form.processing">Logging in...</span>
              <span v-else>Sign in</span>
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

const page = usePage();
const status = computed(() => page.props.flash?.status || page.props.status);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('portal.login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>
