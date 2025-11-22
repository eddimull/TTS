<template>
  <authenticated-layout>
    <template #header>
      <h2 class="font-semibold text-xl leading-tight">
        My Personal Stats
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Info Banner -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg
                class="h-5 w-5 text-blue-400"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fill-rule="evenodd"
                  d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                  clip-rule="evenodd"
                />
              </svg>
            </div>
            <div class="ml-3 flex-1">
              <p class="text-sm text-blue-700 dark:text-blue-300">
                These are your personal earnings from bookings and travel statistics. Your share is calculated based on the payment configuration for each band, only counting bookings from after you joined.
              </p>
            </div>
          </div>
        </div>
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <!-- Total Earnings Card -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                  <svg
                    class="h-6 w-6 text-white"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
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
                      My Total Earnings
                    </dt>
                    <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                      ${{ formatNumber(stats.payments.total_earnings) }}
                    </dd>
                    <dd class="text-xs text-gray-500 dark:text-gray-400">
                      {{ stats.payments.booking_count }} bookings
                    </dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <!-- Total Distance Card -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                  <svg
                    class="h-6 w-6 text-white"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"
                    />
                  </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                      Distance Traveled
                    </dt>
                    <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                      {{ formatNumber(stats.travel.total_miles) }} miles
                    </dd>
                    <dd class="text-xs text-gray-500 dark:text-gray-400">
                      {{ stats.travel.total_hours }} hours
                    </dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <!-- Events Count Card -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                  <svg
                    class="h-6 w-6 text-white"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
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
                      Events Played
                    </dt>
                    <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                      {{ stats.travel.event_count }}
                    </dd>
                    <dd class="text-xs text-gray-500 dark:text-gray-400">
                      {{ stats.locations.length }} unique locations
                    </dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Earnings by Year Chart -->
          <div
            v-if="stats.payments.by_year.length > 0"
            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg"
          >
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                My Earnings by Year
              </h3>
              <div class="relative">
                <canvas ref="earningsByYearChart" />
              </div>
            </div>
          </div>

          <!-- Earnings by Band Chart -->
          <div
            v-if="stats.payments.by_band.length > 0"
            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg"
          >
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                My Earnings by Band
              </h3>
              <div class="relative">
                <canvas ref="earningsByBandChart" />
              </div>
            </div>
          </div>
        </div>

        <!-- Bookings Breakdown by Year -->
        <div
          v-if="stats.payments.bookings_by_year && stats.payments.bookings_by_year.length > 0"
          class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg"
        >
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
              My Bookings Breakdown
            </h3>
            <div class="space-y-4">
              <div
                v-for="yearData in stats.payments.bookings_by_year"
                :key="yearData.year"
                class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden"
              >
                <!-- Year Header -->
                <button
                  class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors flex items-center justify-between"
                  @click="toggleYear(yearData.year)"
                >
                  <div class="flex items-center space-x-4">
                    <svg
                      class="h-5 w-5 text-gray-500 transition-transform"
                      :class="{ 'rotate-90': expandedYears.includes(yearData.year) }"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 5l7 7-7 7"
                      />
                    </svg>
                    <span class="text-lg font-semibold text-gray-900 dark:text-white">
                      {{ yearData.year }}
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                      {{ yearData.booking_count }} {{ yearData.booking_count === 1 ? 'booking' : 'bookings' }}
                    </span>
                  </div>
                  <div class="text-right">
                    <div class="text-lg font-semibold text-green-600 dark:text-green-400">
                      ${{ formatNumber(yearData.year_total) }}
                    </div>
                  </div>
                </button>

                <!-- Bookings Table -->
                <div
                  v-if="expandedYears.includes(yearData.year)"
                  class="overflow-x-auto"
                >
                  <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                      <tr>
                        <th
                          scope="col"
                          class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                        >
                          Date
                        </th>
                        <th
                          scope="col"
                          class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                        >
                          Booking
                        </th>
                        <th
                          scope="col"
                          class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                        >
                          Venue
                        </th>
                        <th
                          scope="col"
                          class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                        >
                          Total Price
                        </th>
                        <th
                          scope="col"
                          class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                        >
                          My Share
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                      <tr
                        v-for="booking in yearData.bookings"
                        :key="booking.id"
                      >
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                          {{ formatDate(booking.date) }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                          <div class="font-medium">
                            {{ booking.booking_name }}
                          </div>
                          <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ booking.band_name }}
                          </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                          <div>
                            {{ booking.venue_name }}
                          </div>
                          <div
                            v-if="booking.venue_address"
                            class="text-xs"
                          >
                            {{ booking.venue_address }}
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                          ${{ formatNumber(booking.total_price) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-green-600 dark:text-green-400">
                          ${{ formatNumber(booking.user_share) }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Map Section -->
        <div
          v-if="stats.locations.length > 0"
          class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg"
        >
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
              Where I've Performed
            </h3>
            <location-map
              :locations="stats.locations"
              :api-key="googleMapsApiKey"
            />
          </div>
        </div>

        <!-- Locations Table -->
        <div
          v-if="stats.locations.length > 0"
          class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg"
        >
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
              My Recent Performance Locations
            </h3>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                  <tr>
                    <th
                      scope="col"
                      class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                    >
                      Event
                    </th>
                    <th
                      scope="col"
                      class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                    >
                      Venue
                    </th>
                    <th
                      scope="col"
                      class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                    >
                      Address
                    </th>
                    <th
                      scope="col"
                      class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                    >
                      Date
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                  <tr
                    v-for="(location, index) in stats.locations.slice(0, 20)"
                    :key="index"
                  >
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                      {{ location.title }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                      {{ location.venue_name }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                      {{ location.venue_address }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                      {{ formatDate(location.date) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-if="stats.payments.booking_count === 0 && stats.travel.event_count === 0"
          class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg"
        >
          <div class="p-12 text-center">
            <svg
              class="mx-auto h-12 w-12 text-gray-400"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
              />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
              No personal statistics available yet
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              You don't have any bookings yet. Once you join a band and they receive bookings, your calculated share will appear here based on the payment configuration.
            </p>
          </div>
        </div>
      </div>
    </div>
  </authenticated-layout>
</template>

<script>
import AuthenticatedLayout from '@/Layouts/Authenticated.vue'
import LocationMap from './LocationMap.vue'
import { Chart, registerables } from 'chart.js'

Chart.register(...registerables)

export default {
  components: {
    AuthenticatedLayout,
    LocationMap
  },

  props: {
    stats: {
      type: Object,
      required: true
    },
    user: {
      type: Object,
      required: true
    },
    googleMapsApiKey: {
      type: String,
      default: null
    }
  },

  data() {
    return {
      yearChart: null,
      bandChart: null,
      expandedYears: []
    }
  },

  mounted() {
    this.$nextTick(() => {
      this.createCharts()
    })
  },

  beforeUnmount() {
    // Clean up chart instances
    if (this.yearChart) {
      this.yearChart.destroy()
      this.yearChart = null
    }
    if (this.bandChart) {
      this.bandChart.destroy()
      this.bandChart = null
    }
  },

  methods: {
    toggleYear(year) {
      const index = this.expandedYears.indexOf(year)
      if (index > -1) {
        this.expandedYears.splice(index, 1)
      } else {
        this.expandedYears.push(year)
      }
    },

    createCharts() {
      // Earnings by Year Chart
      if (this.$refs.earningsByYearChart && this.stats.payments.by_year.length > 0) {
        const ctx = this.$refs.earningsByYearChart.getContext('2d')
        this.yearChart = new Chart(ctx, {
          type: 'bar',
          data: {
            labels: this.stats.payments.by_year.map(item => item.year),
            datasets: [{
              label: 'My Earnings ($)',
              data: this.stats.payments.by_year.map(item => parseFloat(item.total)),
              backgroundColor: 'rgba(34, 197, 94, 0.5)',
              borderColor: 'rgba(34, 197, 94, 1)',
              borderWidth: 1
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 2,
            plugins: {
              legend: {
                display: false
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  callback: function(value) {
                    return '$' + value.toLocaleString()
                  }
                }
              }
            }
          }
        })
      }

      // Earnings by Band Chart
      if (this.$refs.earningsByBandChart && this.stats.payments.by_band.length > 0) {
        const ctx = this.$refs.earningsByBandChart.getContext('2d')
        this.bandChart = new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: this.stats.payments.by_band.map(item => item.band_name),
            datasets: [{
              label: 'My Earnings ($)',
              data: this.stats.payments.by_band.map(item => parseFloat(item.total)),
              backgroundColor: [
                'rgba(34, 197, 94, 0.5)',
                'rgba(59, 130, 246, 0.5)',
                'rgba(168, 85, 247, 0.5)',
                'rgba(249, 115, 22, 0.5)',
                'rgba(236, 72, 153, 0.5)',
              ],
              borderColor: [
                'rgba(34, 197, 94, 1)',
                'rgba(59, 130, 246, 1)',
                'rgba(168, 85, 247, 1)',
                'rgba(249, 115, 22, 1)',
                'rgba(236, 72, 153, 1)',
              ],
              borderWidth: 1
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 1.5,
            plugins: {
              legend: {
                position: 'bottom'
              },
              tooltip: {
                callbacks: {
                  label: function(context) {
                    return context.label + ': $' + parseFloat(context.parsed).toLocaleString()
                  }
                }
              }
            }
          }
        })
      }
    },

    formatNumber(value) {
      if (!value) return '0'
      return parseFloat(value).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      })
    },

    formatDate(date) {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    }
  }
}
</script>
