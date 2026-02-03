<template>
  <form
    :action="'/bands/' + band.id"
    method="PATCH"
    @submit.prevent="updateBand"
  >
    <!-- Band Name -->
    <TextInput
      v-model="form.name"
      name="name"
      label="Band Name"
      placeholder="Enter band name"
    />

    <!-- Site Name -->
    <div>
      <TextInput
        v-model="form.site_name"
        name="site_name"
        label="Page Name (URL)"
        placeholder="band_name"
      />
      <p
        v-if="urlWarn"
        class="mt-1 text-sm text-red-600 dark:text-red-400"
      >
        Only letters, numbers, underscores, and hyphens are allowed
      </p>
    </div>

    <!-- Logo -->
    <div class="mb-6">
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Band Logo
      </label>
      <div class="space-y-3">
        <div
          v-if="band.logo"
          class="flex items-center gap-4"
        >
          <img
            :src="band.logo"
            alt="Current logo"
            class="w-16 h-16 object-cover rounded-lg border border-gray-200 dark:border-gray-600"
          >
          <span class="text-sm text-gray-600 dark:text-gray-400">Current logo</span>
        </div>
        <FileUpload
          ref="fileUpload"
          mode="basic"
          name="logo"
          accept="image/*"
          :auto="true"
          :custom-upload="true"
          choose-label="Upload New Logo"
          class="w-full"
          @uploader="uploadLogo"
        />
      </div>
    </div>

    <!-- Address Section -->
    <div class="border-t border-gray-200 dark:border-gray-600 pt-6 mb-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        Band Address
      </h3>
      <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 mb-4">
        <p class="text-sm text-blue-800 dark:text-blue-200">
          <strong>Required for contracts:</strong> This address will appear on all contracts and payment information sent to clients. All fields are required.
        </p>
      </div>

      <!-- Street Address with Autocomplete -->
      <div class="mb-4">
        <LocationAutocomplete
          v-model="form.address"
          name="band_address"
          label="Street Address *"
          placeholder="Start typing an address..."
          @location-selected="handleAddressSelected"
        />
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
          Start typing to search for your band's address
        </p>
      </div>

      <!-- City, State, Zip -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <!-- City -->
        <div class="md:col-span-1">
          <TextInput
            v-model="form.city"
            name="city"
            label="City *"
            placeholder="Auto-filled from address"
          />
        </div>

        <!-- State -->
        <div class="md:col-span-1">
          <TextInput
            v-model="form.state"
            name="state"
            label="State *"
            placeholder="LA"
          />
        </div>

        <!-- Zip Code -->
        <div class="md:col-span-1">
          <TextInput
            v-model="form.zip"
            name="zip"
            label="Zip Code *"
            placeholder="70506"
          />
        </div>
      </div>
    </div>

    <!-- Stripe Setup -->
    <div class="border-t border-gray-200 dark:border-gray-600 pt-6 mb-6">
      <div
        v-if="!band.stripe_accounts"
        class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4"
      >
        <div class="flex items-center gap-3">
          <svg
            class="w-6 h-6 text-yellow-600 dark:text-yellow-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"
            />
          </svg>
          <div>
            <h3 class="font-medium text-yellow-800 dark:text-yellow-200">
              Stripe Payment Setup Required
            </h3>
            <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
              Set up Stripe to accept payments for your band.
            </p>
            <a
              :href="'/bands/' + band.id + '/setupStripe'"
              class="inline-block mt-3"
            >
              <Button
                severity="warning"
                size="small"
              >Setup Stripe</Button>
            </a>
          </div>
        </div>
      </div>
      <div
        v-else
        class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4"
      >
        <div class="flex items-center gap-2">
          <svg
            class="w-5 h-5 text-green-600 dark:text-green-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M5 13l4 4L19 7"
            />
          </svg>
          <span class="text-green-800 dark:text-green-200 font-medium">Stripe account configured</span>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-600">
      <Button
        type="submit"
        label="Update Band"
        icon="pi pi-save"
        :loading="loading"
      />
    </div>
  </form>
</template>

<script>
import FileUpload from 'primevue/fileupload';
import LocationAutocomplete from '@/Components/LocationAutocomplete.vue';
import TextInput from '@/Components/TextInput.vue';

export default {
  name: 'EditDetails',
  components: {
    FileUpload,
    LocationAutocomplete,
    TextInput,
  },
  props: {
    band: {
      type: Object,
      required: true
    },
    form: {
      type: Object,
      required: true
    },
    loading: {
      type: Boolean,
      default: false
    }
  },
  emits: ['update-band', 'upload-logo'],
  data() {
    return {
      urlWarn: false
    }
  },
  watch: {
    'form.site_name': {
      handler(value) {
        if (value && value.length > 0) {
          let message = value;
          let urlsafeName = message.replace(/[^aA-zZ0-9\-_]/gm, "")
          this.urlWarn = urlsafeName !== value
          if (urlsafeName !== value) {
            this.form.site_name = urlsafeName;
          }
        }
      }
    }
  },
  methods: {
    updateBand() {
      this.$emit('update-band');
    },
    uploadLogo(event) {
      this.$emit('upload-logo', event);
    },
    handleAddressSelected(locationData) {
      // Parse Google Places API response to extract address components
      const result = locationData.result;

      if (!result || !result.address_components) {
        console.error('Invalid location data', locationData);
        return;
      }

      // Helper function to extract address component
      const getAddressComponent = (type) => {
        const component = result.address_components.find(comp =>
          comp.types.includes(type)
        );
        return component ? component.long_name : '';
      };

      const getAddressComponentShort = (type) => {
        const component = result.address_components.find(comp =>
          comp.types.includes(type)
        );
        return component ? component.short_name : '';
      };

      // Extract street address (street_number + route)
      const streetNumber = getAddressComponent('street_number');
      const route = getAddressComponent('route');
      const streetAddress = [streetNumber, route].filter(Boolean).join(' ');

      // Extract other components
      const city = getAddressComponent('locality') || getAddressComponent('sublocality');
      const state = getAddressComponentShort('administrative_area_level_1');
      const zip = getAddressComponent('postal_code');

      // Update all form fields
      if (streetAddress) {
        this.form.address = streetAddress;
      }
      if (city) {
        this.form.city = city;
      }
      if (state) {
        this.form.state = state;
      }
      if (zip) {
        this.form.zip = zip;
      }
    }
  }
}
</script>
