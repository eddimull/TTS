<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl w-full space-y-8">
      <!-- Header -->
      <div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-white">
          Substitute Invitation
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">
          You've been invited to substitute for {{ band.name }}
        </p>
      </div>

      <!-- Event Details Card -->
      <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">
        <div class="px-6 py-8">
          <!-- Band Name -->
          <div class="mb-6">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ band.name }}
            </h3>
          </div>

          <!-- Event Information -->
          <div class="space-y-4">
            <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
              <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                Event Details
              </h4>

              <dl class="grid grid-cols-1 gap-3">
                <div>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Event
                  </dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                    {{ event.title || event.name || 'Upcoming Event' }}
                  </dd>
                </div>

                <div>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Date
                  </dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                    {{ formatDate(event.date) }}
                  </dd>
                </div>

                <div v-if="event.time || event.start_time">
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Time
                  </dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                    {{ event.time || event.start_time }}
                  </dd>
                </div>

                <div v-if="event.location || eventSub.location">
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Location
                  </dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                    {{ event.location || eventSub.location }}
                  </dd>
                </div>

                <div v-if="roleName">
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Instrument / Role
                  </dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-white font-semibold">
                    {{ roleName }}
                  </dd>
                </div>

                <div v-if="eventSub.payout_amount">
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Payout
                  </dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-white font-semibold text-green-600 dark:text-green-400">
                    ${{ (eventSub.payout_amount / 100).toFixed(2) }}
                  </dd>
                </div>

                <div v-if="event.end_time || event.start_time">
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Duration
                  </dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                    {{ event.start_time || event.time }} - {{ event.end_time || 'TBD' }}
                  </dd>
                </div>

                <div v-if="eventSub.notes">
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Notes
                  </dt>
                  <dd class="mt-1 text-sm text-gray-900 dark:text-white whitespace-pre-wrap">
                    {{ eventSub.notes }}
                  </dd>
                </div>
              </dl>
            </div>

            <!-- Music Selection -->
            <div v-if="charts.length > 0 || songs.length > 0" class="border-t border-gray-200 dark:border-gray-700 pt-4">
              <h5 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                Music Selection
              </h5>

              <div v-if="charts.length > 0" class="mb-3">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Charts:</p>
                <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 space-y-1">
                  <li v-for="(chart, index) in charts" :key="'chart-' + index">
                    {{ chart.title }}
                    <span v-if="chart.composer" class="text-gray-500 text-xs">
                      - {{ chart.composer }}
                    </span>
                  </li>
                </ul>
              </div>

              <div v-if="songs.length > 0">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Songs:</p>
                <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 space-y-1">
                  <li v-for="(song, index) in songs" :key="'song-' + index">
                    <a v-if="song.url" :href="song.url" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                      {{ song.title }}
                    </a>
                    <span v-else>{{ song.title }}</span>
                  </li>
                </ul>
              </div>
            </div>

            <!-- What you can access -->
            <div class="pt-4">
              <h5 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">
                As a substitute, you'll have access to:
              </h5>
              <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 space-y-1">
                <li>Event details (time, location, attire)</li>
                <li>Charts and music for this event</li>
                <li>Roster information</li>
                <li>Your payout information</li>
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
            By accepting this invitation, you agree to substitute for this event
            and will receive access to event materials.
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
    eventSub: {
      type: Object,
      required: true,
    },
    event: {
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
    charts: {
      type: Array,
      default: () => [],
    },
    songs: {
      type: Array,
      default: () => [],
    },
    roleName: {
      type: String,
      default: null,
    },
  },

  methods: {
    formatDate(dateString) {
      if (!dateString) return 'TBD'

      try {
        const date = new Date(dateString)
        return date.toLocaleDateString('en-US', {
          weekday: 'long',
          year: 'numeric',
          month: 'long',
          day: 'numeric'
        })
      } catch (e) {
        return dateString
      }
    },
  },
}
</script>
