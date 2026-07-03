<template>
  <div class="w-full max-w-lg">
    <!-- Invalid / consumed invitation -->
    <div
      v-if="!valid"
      class="text-center"
    >
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        This invite link isn't valid
      </h1>
      <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
        The invitation may have expired or already been used. Ask the band
        owner to send a fresh invite.
      </p>
      <div class="mt-8">
        <Link
          href="/"
          class="text-blue-600 dark:text-blue-400 hover:underline text-sm"
        >
          Go to Bandmate
        </Link>
      </div>
    </div>

    <!-- Valid invitation -->
    <div v-else>
      <div class="text-center">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
          You're invited to join {{ bandName }}
        </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Bandmate keeps your gigs, setlists, and payouts in one place.
        </p>
      </div>

      <breeze-validation-errors class="mt-4" />

      <!-- Get the app -->
      <div
        v-if="appStoreUrl || playStoreUrl"
        class="mt-8"
      >
        <p class="text-center text-sm font-medium text-gray-700 dark:text-gray-300">
          Get the app and scan the code again to join:
        </p>
        <div class="mt-3 flex justify-center gap-3">
          <a
            v-if="appStoreUrl"
            :href="appStoreUrl"
            class="store-button"
            dusk="invite-app-store"
          >
            App Store
          </a>
          <a
            v-if="playStoreUrl"
            :href="playStoreUrl"
            class="store-button"
            dusk="invite-play-store"
          >
            Google Play
          </a>
        </div>
        <div class="my-6 flex items-center gap-3">
          <span class="h-px flex-1 bg-gray-200 dark:bg-gray-700" />
          <span class="text-xs uppercase tracking-wide text-gray-400">or join in the browser</span>
          <span class="h-px flex-1 bg-gray-200 dark:bg-gray-700" />
        </div>
      </div>
      <div
        v-else
        class="mt-8"
      />

      <!-- Web join: signed-in users join right away; guests sign in first
           (login returns here; registering prefills the code on onboarding). -->
      <div
        v-if="$page.props.auth.user"
        class="text-center"
      >
        <breeze-button
          type="button"
          class="justify-center"
          :class="{ 'opacity-50 cursor-wait': joinForm.processing }"
          :disabled="joinForm.processing"
          dusk="invite-join"
          @click="join"
        >
          Join {{ bandName }}
        </breeze-button>
      </div>
      <div
        v-else
        class="flex flex-col items-center gap-3"
      >
        <Link
          :href="route('login')"
          class="w-full max-w-xs text-center px-4 py-2 rounded-md bg-gray-800 text-white text-sm font-semibold hover:bg-gray-700 dark:bg-gray-200 dark:text-gray-900 dark:hover:bg-white"
          dusk="invite-login"
        >
          Log in to join
        </Link>
        <Link
          :href="route('register')"
          class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
          dusk="invite-register"
        >
          New to Bandmate? Create an account
        </Link>
      </div>
    </div>
  </div>
</template>

<script>
import BreezeButton from '@/Components/Button'
import BreezeGuestWideLayout from '@/Layouts/GuestWide'
import BreezeValidationErrors from '@/Components/ValidationErrors'

export default {
  components: {
    BreezeButton,
    BreezeValidationErrors,
  },
  layout: BreezeGuestWideLayout,
  pageTitle: 'Band Invitation',

  props: {
    inviteKey: { type: String, required: true },
    valid: { type: Boolean, required: true },
    bandName: { type: String, default: null },
    appStoreUrl: { type: String, default: null },
    playStoreUrl: { type: String, default: null },
  },

  data() {
    return {
      joinForm: this.$inertia.form({
        key: this.inviteKey,
      }),
    }
  },

  methods: {
    join() {
      this.joinForm.post(this.route('onboarding.join'))
    },
  },
}
</script>

<style scoped>
.store-button {
  display: inline-flex;
  align-items: center;
  padding: 0.5rem 1.25rem;
  border-radius: 0.5rem;
  border: 1px solid rgb(209 213 219);
  font-size: 0.875rem;
  font-weight: 600;
  color: rgb(17 24 39);
  background-color: rgb(255 255 255);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.store-button:hover {
  border-color: rgb(59 130 246);
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.1);
}

:global(.dark) .store-button {
  border-color: rgb(71 85 105);
  background-color: rgb(51 65 85);
  color: rgb(255 255 255);
}
</style>
