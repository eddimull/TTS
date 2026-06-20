<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl w-full space-y-8">
      <!-- Header -->
      <div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-white">
          Substitute Invitation
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
          You've been invited to sub for {{ band.name }}
        </p>
      </div>

      <!-- Details Card -->
      <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">
        <div class="px-6 py-8">
          <!-- Band Name -->
          <div class="mb-6">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ band.name }}
            </h3>
          </div>

          <div class="space-y-4">
            <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
              <dl class="grid grid-cols-1 gap-3">
                <div v-if="roleName">
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Instrument / Role
                  </dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-white font-semibold">
                    {{ roleName }}
                  </dd>
                </div>

                <div v-if="invitation.notes">
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Notes
                  </dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-white whitespace-pre-wrap">
                    {{ invitation.notes }}
                  </dd>
                </div>
              </dl>
            </div>

            <!-- What you can access -->
            <div class="pt-4">
              <h5 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">
                As a substitute for this band, you'll have access to:
              </h5>
              <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 space-y-1">
                <li>Event details for gigs you're called for</li>
                <li>Charts and music for those events</li>
                <li>Roster information</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4 flex flex-col sm:flex-row gap-3 justify-center">
          <a
            :href="route('register', { invitation: invitationKey })"
            class="inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            Create Account & Accept
          </a>

          <a
            :href="route('login')"
            class="inline-flex justify-center items-center px-6 py-3 border border-gray-300 dark:border-gray-600 text-base font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            Already Have an Account? Log In
          </a>
        </div>

        <!-- Footer Note -->
        <div class="px-6 py-4 text-center text-xs text-gray-500 dark:text-gray-400">
          <p>
            By accepting this invitation, you agree to substitute for this band
            and will receive access to event materials when you're called.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { Head } from '@inertiajs/vue3'

export default {
  components: {
    Head,
  },

  props: {
    invitation: {
      type: Object,
      required: true,
    },
    band: {
      type: Object,
      required: true,
    },
    invitationKey: {
      type: String,
      required: true,
    },
    roleName: {
      type: String,
      default: null,
    },
  },
}
</script>
