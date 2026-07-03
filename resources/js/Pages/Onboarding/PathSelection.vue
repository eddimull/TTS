<template>
  <div class="w-full max-w-lg">
    <div class="text-center mb-2">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        How would you like to use Bandmate?
      </h1>
      <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
        You can always add or join a band later from your settings.
      </p>
    </div>

    <breeze-validation-errors class="mt-4" />

    <div class="mt-8 space-y-4">
      <!-- Create a Band -->
      <Link
        :href="route('bands.create')"
        class="onboarding-card group"
        dusk="onboarding-create"
      >
        <span class="onboarding-card__icon">
          <svg
            class="w-6 h-6"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.5"
              d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"
            />
          </svg>
        </span>
        <span class="onboarding-card__body">
          <span class="onboarding-card__title">Create a Band</span>
          <span class="onboarding-card__subtitle">Start a new band and invite your members.</span>
        </span>
        <span class="onboarding-card__chevron">→</span>
      </Link>

      <!-- Join a Band -->
      <div class="onboarding-card onboarding-card--static">
        <span class="onboarding-card__icon">
          <svg
            class="w-6 h-6"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.5"
              d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5m6.656-6.656l1.5-1.5a4 4 0 015.656 5.656l-3 3a4 4 0 01-5.656 0"
            />
          </svg>
        </span>
        <span class="onboarding-card__body w-full">
          <span class="onboarding-card__title">Join a Band</span>
          <span class="onboarding-card__subtitle">Enter an invite code from a band owner.</span>

          <form
            class="mt-3 flex gap-2"
            @submit.prevent="join"
          >
            <input
              v-model="joinForm.key"
              type="text"
              placeholder="Invite code"
              aria-label="Invite code"
              autocomplete="off"
              autocapitalize="none"
              spellcheck="false"
              dusk="onboarding-join-code"
              class="flex-1 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
            >
            <breeze-button
              type="submit"
              :class="{ 'opacity-25': joinForm.processing }"
              :disabled="joinForm.processing"
              dusk="onboarding-join-submit"
            >
              Join
            </breeze-button>
          </form>
          <p
            v-if="joinForm.errors.key"
            class="mt-2 text-sm text-red-600 dark:text-red-400"
            dusk="onboarding-join-error"
          >
            {{ joinForm.errors.key }}
          </p>
        </span>
      </div>

      <!-- Go Solo -->
      <button
        type="button"
        class="onboarding-card w-full text-left"
        :class="{ 'opacity-50 cursor-wait': soloForm.processing }"
        :disabled="soloForm.processing"
        dusk="onboarding-solo"
        @click="goSolo"
      >
        <span class="onboarding-card__icon">
          <svg
            class="w-6 h-6"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.5"
              d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"
            />
          </svg>
        </span>
        <span class="onboarding-card__body">
          <span class="onboarding-card__title">Go Solo</span>
          <span class="onboarding-card__subtitle">Use Bandmate for personal gig tracking and setlists.</span>
        </span>
        <span class="onboarding-card__chevron">→</span>
      </button>
    </div>

    <!-- Account access stays reachable even with no band yet. -->
    <div class="mt-8 flex items-center justify-center gap-6 text-sm">
      <Link
        :href="route('account')"
        class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
      >
        Account
      </Link>
      <Link
        :href="route('logout')"
        method="post"
        as="button"
        class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
        dusk="onboarding-signout"
      >
        Sign out
      </Link>
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
  pageTitle: 'Get Started',

  props: {
    // Stashed by the invite landing page (/invite/{key}) so a scanner who
    // had to register first doesn't retype the code.
    pendingInviteKey: { type: String, default: '' },
  },

  data() {
    return {
      joinForm: this.$inertia.form({
        key: this.pendingInviteKey,
      }),
      soloForm: this.$inertia.form({}),
    }
  },

  methods: {
    join() {
      this.joinForm.post(this.route('onboarding.join'))
    },
    goSolo() {
      this.soloForm.post(this.route('onboarding.solo'))
    },
  },
}
</script>

<style scoped>
.onboarding-card {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1.25rem;
  border-radius: 0.75rem;
  border: 1px solid rgb(229 231 235);
  background-color: rgb(255 255 255);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

:global(.dark) .onboarding-card {
  border-color: rgb(71 85 105);
  background-color: rgb(51 65 85);
}

.onboarding-card:not(.onboarding-card--static):hover {
  border-color: rgb(59 130 246);
  box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
}

.onboarding-card__icon {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3rem;
  height: 3rem;
  border-radius: 0.75rem;
  color: rgb(37 99 235);
  background-color: rgb(219 234 254);
}

:global(.dark) .onboarding-card__icon {
  color: rgb(96 165 250);
  background-color: rgb(30 58 138 / 0.4);
}

.onboarding-card__body {
  display: flex;
  flex-direction: column;
}

.onboarding-card__title {
  font-size: 1rem;
  font-weight: 600;
  color: rgb(17 24 39);
}

:global(.dark) .onboarding-card__title {
  color: rgb(255 255 255);
}

.onboarding-card__subtitle {
  margin-top: 0.125rem;
  font-size: 0.8125rem;
  color: rgb(75 85 99);
}

:global(.dark) .onboarding-card__subtitle {
  color: rgb(148 163 184);
}

.onboarding-card__chevron {
  margin-left: auto;
  align-self: center;
  color: rgb(156 163 175);
  font-size: 1.125rem;
}
</style>
