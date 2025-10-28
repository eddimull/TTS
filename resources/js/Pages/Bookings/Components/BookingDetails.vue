<template>
  <Container class="p-4">
    <div class="space-y-4">
      <!-- Header Section with Title and Status -->
      <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4">
        <div class="flex justify-between items-start mb-3">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-50">
              {{ booking.name }}
            </h1>
            <div
              v-if="eventType"
              class="inline-block bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-1 rounded text-xs font-medium mt-2"
            >
              <i class="pi pi-tag mr-1" />
              {{ eventType.name }}
            </div>
          </div>
          <span
            :class="statusClass"
            class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide"
          >
            {{ booking.status }}
          </span>
        </div>
      </div>

      <!-- Quick Info Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Date & Time + Venue -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-50 mb-3 flex items-center">
            <i class="pi pi-calendar mr-2" />
            Schedule & Venue
          </h2>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-500 dark:text-gray-400">Date:</span>
              <span class="font-medium text-gray-900 dark:text-gray-50">{{ formatDate(booking.date) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500 dark:text-gray-400">Time:</span>
              <span class="font-medium text-gray-900 dark:text-gray-50">
                {{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }}
                <span
                  v-if="duration"
                  class="text-gray-500 dark:text-gray-400"
                >({{ duration }})</span>
              </span>
            </div>
            <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
              <span class="text-gray-500 dark:text-gray-400">Venue:</span>
              <span class="font-medium text-gray-900 dark:text-gray-50 text-right">{{ booking.venue_name || 'TBD' }}</span>
            </div>
            <div
              v-if="booking.venue_address"
              class="text-gray-600 dark:text-gray-400 text-xs text-right"
            >
              {{ booking.venue_address }}
            </div>
          </div>
        </div>

        <!-- Financial + Contract -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-50 mb-3 flex items-center">
            <i class="pi pi-dollar mr-2" />
            Financial & Contract
          </h2>
          <div class="space-y-3">
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-500 dark:text-gray-400">Price:</span>
              <span class="text-xl font-bold text-green-600 dark:text-green-400">${{ formatPrice(booking.price) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-sm text-gray-500 dark:text-gray-400">Paid:</span>
              <span class="text-xl font-bold text-blue-600 dark:text-blue-400">${{ formatPrice(booking.amountPaid) }}</span>
            </div>
            <div
              v-if="booking.price > 0"
              class="pt-2"
            >
              <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div
                  :style="{ width: paymentProgress + '%' }"
                  class="h-2 rounded-full transition-all duration-300"
                  :class="paymentProgress >= 100 ? 'bg-green-500' : 'bg-blue-500'"
                />
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400 text-center mt-1">
                {{ paymentProgress }}% paid
              </div>
            </div>
            <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
              <span class="text-sm text-gray-500 dark:text-gray-400">Contract: </span>
              <span
                v-if="booking.contract_option === 'default'"
                class="text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-1 rounded"
              >
                Default
              </span>
              <span
                v-else-if="booking.contract_option === 'external'"
                class="text-xs bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 px-2 py-1 rounded"
              >
                External
              </span>
              <span
                v-else
                class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-2 py-1 rounded"
              >
                None
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Three Column Layout for Contacts, Payments, Events -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Contacts Section -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-50 mb-3 flex items-center justify-between">
            <span>
              <i class="pi pi-users mr-2" />
              Contacts
            </span>
            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ booking.contacts?.length || 0 }}</span>
          </h2>
          <div
            v-if="booking.contacts && booking.contacts.length > 0"
            class="space-y-2 max-h-96 overflow-y-auto"
          >
            <div
              v-for="contact in booking.contacts"
              :key="contact.id"
              class="border border-gray-200 dark:border-slate-600 rounded p-2 text-sm hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors"
            >
              <div class="flex items-center justify-between mb-1">
                <div class="font-semibold text-gray-900 dark:text-gray-50 truncate">
                  {{ contact.name }}
                </div>
                <i
                  v-if="contact.pivot?.is_primary"
                  class="pi pi-star-fill text-amber-500 text-xs"
                  title="Primary Contact"
                />
              </div>
              <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                <div
                  v-if="contact.email"
                  class="flex items-center truncate"
                >
                  <i class="pi pi-envelope mr-1 text-xs" />
                  <a
                    :href="`mailto:${contact.email}`"
                    class="text-blue-600 dark:text-blue-400 hover:underline truncate"
                  >
                    {{ contact.email }}
                  </a>
                </div>
                <div
                  v-if="contact.phone"
                  class="flex items-center"
                >
                  <i class="pi pi-phone mr-1 text-xs" />
                  {{ contact.phone }}
                </div>
                <div
                  v-if="contact.pivot?.role"
                  class="flex items-center"
                >
                  <i class="pi pi-tag mr-1 text-xs" />
                  <span class="capitalize">{{ contact.pivot.role }}</span>
                </div>
              </div>
            </div>
          </div>
          <div
            v-else
            class="text-center text-sm text-gray-500 dark:text-gray-400 py-4"
          >
            No contacts
          </div>
        </div>

        <!-- Payments Section -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-50 mb-3 flex items-center justify-between">
            <span>
              <i class="pi pi-money-bill mr-2" />
              Payments
            </span>
            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ booking.payments?.length || 0 }}</span>
          </h2>
          <div
            v-if="booking.payments && booking.payments.length > 0"
            class="space-y-2 max-h-96 overflow-y-auto"
          >
            <div
              v-for="payment in booking.payments"
              :key="payment.id"
              class="border border-gray-200 dark:border-slate-600 rounded p-2 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors"
            >
              <div class="flex-1 min-w-0">
                <div class="font-medium text-gray-900 dark:text-gray-50 text-sm truncate">
                  {{ payment.name || 'Payment' }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  {{ formatDateShort(payment.date) }}
                </div>
              </div>
              <div class="text-right ml-2">
                <div class="text-sm font-semibold text-green-600 dark:text-green-400">
                  ${{ formatPrice(payment.amount) }}
                </div>
                <div
                  v-if="payment.status"
                  class="text-xs capitalize"
                  :class="payment.status === 'paid' ? 'text-green-600' : 'text-gray-500'"
                >
                  {{ payment.status }}
                </div>
              </div>
            </div>
          </div>
          <div
            v-else
            class="text-center text-sm text-gray-500 dark:text-gray-400 py-4"
          >
            No payments
          </div>
        </div>

        <!-- Events Section -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-50 mb-3 flex items-center justify-between">
            <span>
              <i class="pi pi-calendar-plus mr-2" />
              Events
            </span>
            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ booking.events?.length || 0 }}</span>
          </h2>
          <div
            v-if="booking.events && booking.events.length > 0"
            class="space-y-2 max-h-96 overflow-y-auto"
          >
            <div
              v-for="event in booking.events"
              :key="event.id"
              class="border border-gray-200 dark:border-slate-600 rounded p-2 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                  <div class="font-semibold text-gray-900 dark:text-gray-50 text-sm truncate mb-1">
                    {{ event.title }}
                  </div>
                  <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                    <div class="flex items-center">
                      <i class="pi pi-calendar mr-1 text-xs" />
                      {{ formatDateShort(event.date) }}
                    </div>
                    <div
                      v-if="event.time"
                      class="flex items-center"
                    >
                      <i class="pi pi-clock mr-1 text-xs" />
                      {{ formatTime(event.time) }}
                    </div>
                  </div>
                </div>
                <Link
                  :href="route('events.edit', event.key)"
                  class="text-blue-600 dark:text-blue-400 hover:underline text-xs ml-2 flex-shrink-0"
                >
                  View →
                </Link>
              </div>
            </div>
          </div>
          <div
            v-else
            class="text-center text-sm text-gray-500 dark:text-gray-400 py-4"
          >
            No events
          </div>
        </div>
      </div>

      <!-- Notes Section - Full Width -->
      <div
        v-if="booking.notes"
        class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4"
      >
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-50 mb-3 flex items-center">
          <i class="pi pi-file-edit mr-2" />
          Notes
        </h2>
        <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
          {{ booking.notes }}
        </div>
      </div>

      <!-- Recent History Section -->
      <div
        v-if="recentActivities && recentActivities.length > 0"
        class="bg-white dark:bg-slate-800 rounded-lg shadow-md p-4"
      >
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-50 flex items-center">
            <i class="pi pi-history mr-2" />
            Recent Activity
          </h2>
          <Link
            :href="route('bookings.history', [band.id, booking.id])"
            class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
          >
            View All →
          </Link>
        </div>
        <div class="space-y-2 max-h-64 overflow-y-auto">
          <div
            v-for="activity in recentActivities"
            :key="activity.id"
            class="flex items-start gap-3 pb-2 border-b border-gray-200 dark:border-gray-700 last:border-0"
          >
            <!-- Activity Icon -->
            <div
              class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs"
              :class="getActivityIconClass(activity.event_type)"
            >
              <i :class="getActivityIcon(activity.event_type)" />
            </div>
            
            <!-- Activity Content -->
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <div class="flex-1 min-w-0">
                  <p class="text-sm text-gray-900 dark:text-gray-50 font-medium">
                    {{ activity.description }}
                  </p>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    <span v-if="activity.causer">{{ activity.causer.name }}</span>
                    <span v-else>System</span>
                    · {{ activity.created_at_human }}
                  </p>
                </div>
                <span
                  v-if="activity.category"
                  class="flex-shrink-0 text-xs px-2 py-0.5 rounded"
                  :class="getCategoryClass(activity.category)"
                >
                  {{ formatCategory(activity.category) }}
                </span>
              </div>
              <!-- Show first change if available -->
              <div
                v-if="activity.changes && activity.changes.length > 0"
                class="mt-1 text-xs text-gray-600 dark:text-gray-400"
              >
                <span class="font-medium">{{ activity.changes[0].field }}:</span>
                <span v-if="activity.changes[0].old && activity.changes[0].old !== '(empty)'">
                  {{ truncate(activity.changes[0].old, 30) }} →
                </span>
                {{ truncate(activity.changes[0].new, 30) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end space-x-4">
        <Button
          label="View History"
          icon="pi pi-history"
          severity="secondary"
          outlined
          @click="viewHistory"
        />
        <Button
          label="Edit Booking"
          icon="pi pi-pencil"
          severity="secondary"
          @click="editBooking"
        />
        <Button
          v-if="props.booking.status !== 'confirmed'"
          label="Delete Booking"
          icon="pi pi-trash"
          severity="danger"
          outlined
          @click="deleteBooking"
        />
        <Button
          v-if="props.booking.status === 'confirmed'"
          label="Cancel Booking"
          icon="pi pi-times"
          severity="danger"
          outlined
          @click="cancelBooking"
        />
      </div>
    </div>
  </Container>
</template>

<script setup>
import { computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import Container from '@/Components/Container.vue'
import Button from 'primevue/button'
import { useStore } from 'vuex'
import { DateTime } from 'luxon'

const props = defineProps({
  booking: {
    type: Object,
    required: true
  },
  band: {
    type: Object,
    required: true
  },
  recentActivities: {
    type: Array,
    default: () => []
  }
})

const store = useStore()

const eventType = computed(() => {
  const types = store.getters['eventTypes/getAllEventTypes']
  return types.find(type => type.id === props.booking.event_type_id)
})

const statusClass = computed(() => {
  const statusClasses = {
    draft: 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
    pending: 'bg-yellow-200 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    confirmed: 'bg-green-200 text-green-800 dark:bg-green-900 dark:text-green-200',
    cancelled: 'bg-red-200 text-red-800 dark:bg-red-900 dark:text-red-200'
  }
  return statusClasses[props.booking.status] || statusClasses.draft
})

const duration = computed(() => {
  if (!props.booking.start_time || !props.booking.end_time) return null
  
  const start = DateTime.fromFormat(props.booking.start_time, 'HH:mm:ss')
  const end = DateTime.fromFormat(props.booking.end_time, 'HH:mm:ss')
  
  if (!start.isValid || !end.isValid) return null
  
  const diff = end.diff(start, ['hours', 'minutes'])
  const hours = Math.floor(diff.hours)
  const minutes = Math.round(diff.minutes)
  
  if (hours > 0 && minutes > 0) {
    return `${hours}h ${minutes}m`
  } else if (hours > 0) {
    return `${hours} hours`
  } else if (minutes > 0) {
    return `${minutes} minutes`
  }
  return null
})

const paymentProgress = computed(() => {
  if (!props.booking.price || props.booking.price === 0) return 0
  const paid = props.booking.amountPaid || 0
  const price = parseFloat(props.booking.price)
  return Math.min(Math.round((paid / price) * 100), 100)
})

const formatDate = (date) => {
  if (!date) return 'Not specified'
  return DateTime.fromISO(date).toFormat('EEEE, MMMM d, yyyy')
}

const formatDateShort = (date) => {
  if (!date) return 'Not specified'
  return DateTime.fromISO(date).toFormat('MMM d, yyyy')
}

const formatTime = (time) => {
  if (!time) return 'Not specified'
  const dt = DateTime.fromFormat(time, 'HH:mm:ss')
  return dt.isValid ? dt.toFormat('h:mm a') : time
}

const formatPrice = (price) => {
  if (price === null || price === undefined) return '0.00'
  return parseFloat(price).toFixed(2)
}

const editBooking = () => {
  // Navigate to Booking Details with edit mode query param or use an edit component
  router.visit(route('Booking Details', [props.band.id, props.booking.id]) + '?edit=true')
}

const deleteBooking = () => {
  if (confirm('Are you sure you want to delete this booking? This action cannot be undone.')) {
    router.delete(route('bands.booking.destroy', [props.band.id, props.booking.id]), {
      preserveScroll: false,
    })
  }
}

const cancelBooking = () => {
  if (confirm('Are you sure you want to cancel this booking?')) {
    router.post(route('Cancel Booking', [props.band.id, props.booking.id]), {}, {
      preserveScroll: true,
      preserveState: true,
    })
  }
}

const viewHistory = () => {
  router.visit(route('bookings.history', [props.band.id, props.booking.id]))
}

// Activity helper methods
const getActivityIconClass = (eventType) => {
  switch (eventType) {
    case 'created':
      return 'bg-green-500 text-white'
    case 'updated':
      return 'bg-blue-500 text-white'
    case 'deleted':
      return 'bg-red-500 text-white'
    default:
      return 'bg-gray-500 text-white'
  }
}

const getActivityIcon = (eventType) => {
  switch (eventType) {
    case 'created':
      return 'pi pi-plus'
    case 'updated':
      return 'pi pi-pencil'
    case 'deleted':
      return 'pi pi-trash'
    default:
      return 'pi pi-circle'
  }
}

const getCategoryClass = (category) => {
  const classes = {
    booking: 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200',
    contact: 'bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200',
    contact_info: 'bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200',
    payment: 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200',
    contract: 'bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200',
  }
  return classes[category] || 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200'
}

const formatCategory = (category) => {
  const labels = {
    booking: 'Booking',
    contact: 'Contact',
    contact_info: 'Contact Info',
    payment: 'Payment',
    contract: 'Contract',
  }
  return labels[category] || category
}

const truncate = (text, length) => {
  if (!text || text.length <= length) return text
  return text.substring(0, length) + '...'
}
</script>

<style scoped>
.prose {
  max-width: 100%;
}
</style>
