<template>
  <div>
    <div class="relative">
      <div
        ref="map"
        class="w-full h-96 rounded-lg bg-gray-100 dark:bg-gray-900"
      />
      <div
        v-if="loading"
        class="absolute inset-0 bg-white dark:bg-gray-800 bg-opacity-75 dark:bg-opacity-75 flex items-center justify-center rounded-lg"
      >
        <div class="text-center">
          <svg
            class="animate-spin h-8 w-8 text-blue-500 mx-auto mb-2"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
          >
            <circle
              class="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              stroke-width="4"
            />
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
          </svg>
          <p class="text-sm text-gray-600 dark:text-gray-400">
            Loading locations... ({{ markersLoaded }}/{{ totalToLoad }})
          </p>
        </div>
      </div>
    </div>
    <p
      v-if="!hasLocations"
      class="mt-2 text-sm text-gray-500 dark:text-gray-400 text-center"
    >
      {{ locations.length === 0 ? 'No performance locations to display.' : 'Map requires geocoding your performance locations.' }}
    </p>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  props: {
    locations: {
      type: Array,
      required: true
    },
    apiKey: {
      type: String,
      default: null
    }
  },

  data() {
    return {
      map: null,
      markers: [],
      loading: false,
      markersLoaded: 0,
      totalToLoad: 0,
      geocodedLocations: []
    }
  },

  computed: {
    hasLocations() {
      return this.locations && this.locations.length > 0
    }
  },

  mounted() {
    if (this.hasLocations) {
      this.loadMapWithLeaflet()
    }
  },

  methods: {
    async loadMapWithLeaflet() {
      // Load Leaflet CSS and JS dynamically
      if (!document.getElementById('leaflet-css')) {
        const link = document.createElement('link')
        link.id = 'leaflet-css'
        link.rel = 'stylesheet'
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
        link.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY='
        link.crossOrigin = ''
        document.head.appendChild(link)
      }

      if (!window.L) {
        const script = document.createElement('script')
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
        script.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo='
        script.crossOrigin = ''
        script.onload = () => {
          this.initMap()
        }
        script.onerror = () => {
          console.error('Failed to load Leaflet')
        }
        document.head.appendChild(script)
      } else {
        this.initMap()
      }
    },

    async initMap() {
      if (!this.$refs.map || !window.L) {
        return
      }

      // Create map centered on US (default)
      this.map = window.L.map(this.$refs.map).setView([39.8283, -98.5795], 4)

      // Add OpenStreetMap tiles (free, no API key needed)
      window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
      }).addTo(this.map)

      // Geocode and add markers
      await this.addMarkers()
    },

    async addMarkers() {
      if (!this.map || this.locations.length === 0) {
        return
      }

      this.loading = true
      const bounds = window.L.latLngBounds()
      let successfulMarkers = 0

      // Limit to first 50 locations to prevent excessive API calls
      const limitedLocations = this.locations.slice(0, 50)
      this.totalToLoad = limitedLocations.length
      this.markersLoaded = 0

      // Batch geocode requests (5 at a time to avoid overwhelming the server)
      const batchSize = 5
      for (let i = 0; i < limitedLocations.length; i += batchSize) {
        const batch = limitedLocations.slice(i, i + batchSize)

        const promises = batch.map(async (location) => {
          try {
            const result = await this.geocodeAddress(location.full_address)
            if (result) {
              const marker = window.L.marker([result.lat, result.lng]).addTo(this.map)

              marker.bindPopup(`
                <div style="padding: 4px;">
                  <h3 style="font-weight: bold; margin-bottom: 4px;">${location.title}</h3>
                  <p style="margin: 0; color: #666;">${location.venue_name}</p>
                  <p style="margin: 0; color: #666; font-size: 12px;">${location.venue_address}</p>
                  <p style="margin: 4px 0 0 0; color: #999; font-size: 12px;">${location.date}</p>
                  ${result.from_cache ? '<p style="margin: 2px 0 0 0; color: #10b981; font-size: 10px;">Cached</p>' : ''}
                </div>
              `)

              this.markers.push(marker)
              bounds.extend([result.lat, result.lng])
              successfulMarkers++
            }
            this.markersLoaded++
          } catch (error) {
            console.warn(`Failed to geocode location: ${location.full_address}`, error)
            this.markersLoaded++
          }
        })

        await Promise.all(promises)

        // Small delay between batches
        if (i + batchSize < limitedLocations.length) {
          await new Promise(resolve => setTimeout(resolve, 500))
        }
      }

      // Fit map to bounds if we have markers
      if (successfulMarkers > 0) {
        this.map.fitBounds(bounds, { padding: [50, 50] })

        // Don't zoom in too much if there's only one location
        if (successfulMarkers === 1 && this.map.getZoom() > 12) {
          this.map.setZoom(12)
        }
      }

      this.loading = false
    },

    async geocodeAddress(address) {
      try {
        const response = await axios.post('/api/geocodeAddress', {
          address: address
        })
        return response.data
      } catch (error) {
        console.error('Geocoding error:', error)
        return null
      }
    }
  },

  beforeUnmount() {
    // Clean up markers
    this.markers.forEach(marker => {
      marker.remove()
    })
    this.markers = []

    // Clean up map
    if (this.map) {
      this.map.remove()
      this.map = null
    }
  }
}
</script>

<style scoped>
/* Ensure map container has proper styling */
</style>
