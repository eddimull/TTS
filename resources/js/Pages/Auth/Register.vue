<template>
  <breeze-validation-errors class="mb-4" />

  <form @submit.prevent="submit">
    <div>
      <breeze-label
        for="name"
        value="Name"
      />
      <breeze-input
        id="name"
        v-model="form.name"
        type="text"
        class="mt-1 block w-full"
        required
        autofocus
        autocomplete="name"
      />
    </div>

    <div class="mt-4">
      <breeze-label
        for="email"
        value="Email"
      />
      <breeze-input
        v-if="$page.props.invitationEmail"
        id="email"
        v-model="form.email"
        :value="$page.props.invitationEmail"
        type="email"
        class="mt-1 block w-full"
        required
        disabled
        autocomplete="username"
      />
      <breeze-input
        v-else
        id="email"
        v-model="form.email"
        type="email"
        class="mt-1 block w-full"
        required
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
        autocomplete="new-password"
      />
    </div>

    <div class="mt-4">
      <breeze-label
        for="password_confirmation"
        value="Confirm Password"
      />
      <breeze-input
        id="password_confirmation"
        v-model="form.password_confirmation"
        type="password"
        class="mt-1 block w-full"
        required
        autocomplete="new-password"
      />
    </div>

    <div class="flex items-center justify-end mt-4">
      <inertia-link
        :href="route('login')"
        class="underline text-sm text-gray-600 hover:text-gray-900"
      >
        Already registered?
      </inertia-link>

      <breeze-button
        class="ml-4"
        :class="{ 'opacity-25': form.processing }"
        :disabled="form.processing"
      >
        Register
      </breeze-button>
    </div>
  </form>
</template>

<script>
    import BreezeButton from '@/Components/Button'
    import BreezeGuestLayout from '@/Layouts/Guest'
    import BreezeInput from '@/Components/Input'
    import BreezeLabel from '@/Components/Label'
    import BreezeValidationErrors from '@/Components/ValidationErrors'

    export default {
        components: {
            BreezeButton,
            BreezeInput,
            BreezeLabel,
            BreezeValidationErrors,
        },
        layout: BreezeGuestLayout,
        data() {
            return {
                form: this.$inertia.form({
                    name: '',
                    email: '',
                    password: '',
                    password_confirmation: '',
                    terms: false,
                })
            }
        },
        created(){
            if(this.$page.props.invitationEmail)
            {
                this.form.email = this.$page.props.invitationEmail
            }
        },

        methods: {
            submit() {
                this.form.post(this.route('register'), {
                    onFinish: () => this.form.reset('password', 'password_confirmation'),
                })
            }
        }
    }
</script>
