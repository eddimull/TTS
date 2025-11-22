<template>
  <div>
    <div class="relative">
      <div
        ref="map"
        class="w-full h-96 rounded-lg"
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
      v-if="!apiKeyAvailable"
      class="mt-2 text-sm text-gray-500 dark:text-gray-400"
    >
      Google Maps API key not configured. Set GOOGLE_MAPS_API_KEY in your .env file to see the map.
    </p>
  </div>
</template>

<script>
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
      apiKeyAvailable: false,
      geocoder: null,
      loading: false,
      markersLoaded: 0,
      totalToLoad: 0
    }
  },

  mounted() {
    this.loadGoogleMapsScript()
  },

  methods: {
    loadGoogleMapsScript() {
      // Check if script is already loaded
      if (window.google && window.google.maps) {
        this.apiKeyAvailable = true
        this.initMap()
        return
      }

      // Use API key from props
      if (!this.apiKey) {
        console.warn('Google Maps API key not provided')
        this.showLocationsList()
        return
      }

      this.apiKeyAvailable = true

      // Load Google Maps script
      const script = document.createElement('script')
      script.src = `https://maps.googleapis.com/maps/api/js?key=${this.apiKey}&libraries=places`
      script.async = true
      script.defer = true
      script.onload = () => {
        this.initMap()
      }
      script.onerror = () => {
        console.error('Failed to load Google Maps script')
        this.showLocationsList()
      }
      document.head.appendChild(script)
    },

    initMap() {
      if (!this.$refs.map || !window.google) {
        this.showLocationsList()
        return
      }

      // Create map centered on US (default)
      this.map = new window.google.maps.Map(this.$refs.map, {
        zoom: 4,
        center: { lat: 39.8283, lng: -98.5795 }, // Center of US
        styles: [
          {
            featureType: 'poi',
            elementType: 'labels',
            stylers: [{ visibility: 'off' }]
          }
        ]
      })

      this.geocoder = new window.google.maps.Geocoder()

      // Add markers for each location
      this.addMarkers()
    },

    async addMarkers() {
      if (!this.map || !this.geocoder || this.locations.length === 0) {
        return
      }

      this.loading = true
      const bounds = new window.google.maps.LatLngBounds()
      let successfulMarkers = 0

      // Limit to first 50 locations to prevent excessive API calls
      const limitedLocations = this.locations.slice(0, 50)
      this.totalToLoad = limitedLocations.length
      this.markersLoaded = 0

      // Add delay between geocoding requests to avoid rate limiting
      for (let i = 0; i < limitedLocations.length; i++) {
        const location = limitedLocations[i]

        try {
          // Add small delay between requests (100ms)
          if (i > 0) {
            await new Promise(resolve => setTimeout(resolve, 100))
          }

          const result = await this.geocodeAddress(location.full_address)
          if (result) {
            const marker = new window.google.maps.Marker({
              position: result,
              map: this.map,
              title: location.title
            })

            // Create info window
            const infoWindow = new window.google.maps.InfoWindow({
              content: `
                <div style="padding: 8px;">
                  <h3 style="font-weight: bold; margin-bottom: 4px;">${location.title}</h3>
                  <p style="margin: 0; color: #666;">${location.venue_name}</p>
                  <p style="margin: 0; color: #666; font-size: 12px;">${location.venue_address}</p>
                  <p style="margin: 4px 0 0 0; color: #999; font-size: 12px;">${location.date}</p>
                </div>
              `
            })

            marker.addListener('click', () => {
              infoWindow.open(this.map, marker)
            })

            this.markers.push(marker)
            bounds.extend(result)
            successfulMarkers++
          }

          this.markersLoaded = i + 1
        } catch (error) {
          console.warn(`Failed to geocode location: ${location.full_address}`, error)
          this.markersLoaded = i + 1
        }
      }

      // Fit map to bounds if we have markers
      if (successfulMarkers > 0) {
        this.map.fitBounds(bounds)

        // Don't zoom in too much if there's only one location
        const listener = window.google.maps.event.addListener(this.map, 'idle', () => {
          if (successfulMarkers === 1 && this.map.getZoom() > 12) {
            this.map.setZoom(12)
          }
          window.google.maps.event.removeListener(listener)
        })
      }

      this.loading = false
    },

    geocodeAddress(address) {
      return new Promise((resolve, reject) => {
        this.geocoder.geocode({ address: address }, (results, status) => {
          if (status === 'OK' && results[0]) {
            resolve(results[0].geometry.location)
          } else {
            reject(new Error(`Geocoding failed: ${status}`))
          }
        })
      })
    },

    showLocationsList() {
      // Fallback: just show the map div with a message
      if (this.$refs.map) {
        this.$refs.map.innerHTML = `
          <div class="flex items-center justify-center h-full bg-gray-100 dark:bg-gray-900 rounded-lg">
            <p class="text-gray-500 dark:text-gray-400">
              Map view requires Google Maps API configuration
            </p>
          </div>
        `
      }
    }
  },

  beforeUnmount() {
    // Clean up markers
    this.markers.forEach(marker => {
      marker.setMap(null)
    })
    this.markers = []
  }
}
</script>

<style scoped>
/* Ensure map container has proper styling */
</style>
