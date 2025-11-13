<template>
  <div class="mb-6">
    <h2 class="text-center text-3xl font-extrabold text-gray-900 dark:text-white">
      Band Member Login
    </h2>
  </div>

  <breeze-validation-errors class="mb-4" />

  <div
    v-if="status"
    class="mb-4 font-medium text-sm text-green-600"
  >
    {{ status }}
  </div>

  <form @submit.prevent="submit">
    <div>
      <breeze-label
        for="email"
        value="Email"
      />
      <breeze-input
        id="email"
        v-model="form.email"
        type="email"
        class="mt-1 block w-full"
        required
        autofocus
        autocomplete="username"
      />
    </div>

    <div class="mt-4">
      <breeze-label
        for="password"
        value="Password"
      />
      <breeze-input
        id="password"
        v-model="form.password"
        type="password"
        class="mt-1 block w-full"
        required
        autocomplete="current-password"
      />
    </div>

    <div class="block mt-4">
      <label class="flex items-center">
        <breeze-checkbox
          v-model:checked="form.remember"
          name="remember"
        />
        <span class="ml-2 text-sm text-gray-600 dark:text-gray-100">Remember me</span>
      </label>
    </div>

    <div class="flex items-center justify-end mt-4">
      <Link
        v-if="canResetPassword"
        :href="route('password.request')"
        class="underline text-sm text-gray-600 dark:text-gray-100 hover:text-gray-900"
      >
        Forgot your password?
      </Link>

      <breeze-button
        class="ml-4"
        :class="{ 'opacity-25': form.processing }"
        :disabled="form.processing"
      >
        Log in
      </breeze-button>
    </div>
  </form>

  <div class="mt-6 text-center">
    <p class="text-sm text-gray-600 dark:text-gray-400">
      Are you a client?
      <Link
        :href="route('portal.login')"
        class="font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300"
      >
        Access the Client Portal →
      </Link>
    </p>
  </div>
</template>

<script>
    import BreezeButton from '@/Components/Button'
    import BreezeGuestLayout from "@/Layouts/Guest"
    import BreezeInput from '@/Components/Input'
    import BreezeCheckbox from '@/Components/Checkbox'
    import BreezeLabel from '@/Components/Label'
    import BreezeValidationErrors from '@/Components/ValidationErrors'

    export default {

        components: {
            BreezeButton,
            BreezeInput,
            BreezeCheckbox,
            BreezeLabel,
            BreezeValidationErrors
        },
        layout: BreezeGuestLayout,

        props: {
            auth: Object,
            canResetPassword: Boolean,
            errors: Object,
            status: String,
        },

        data() {
            return {
                form: this.$inertia.form({
                    email: '',
                    password: '',
                    remember: false
                })
            }
        },

        methods: {
            submit() {
                this.form
                    .transform(data => ({
                        ... data,
                        remember: this.form.remember ? 'on' : ''
                    }))
                    .post(this.route('login'), {
                        onFinish: () => this.form.reset('password'),
                    })
            }
        }
    }
</script>
