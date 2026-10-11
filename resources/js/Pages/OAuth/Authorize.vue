<template>
  <Head :title="`Authorize ${client.name}`" />

  <div class="mb-6 text-center">
    <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white">
      Allow {{ client.name }} to access TTS Bandmate?
    </h2>
    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
      It will act as
      <span class="font-semibold text-gray-900 dark:text-white">{{ user.name }}</span>
      <span class="block text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</span>
    </p>
  </div>

  <div
    v-if="scopes.length"
    class="mb-4"
  >
    <p class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
      This will let it:
    </p>
    <ul
      class="space-y-2"
      data-test="scopes"
    >
      <li
        v-for="scope in scopes"
        :key="scope.id"
        class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200"
      >
        <i
          class="pi pi-check-circle text-green-600 dark:text-green-400 mt-0.5"
          aria-hidden="true"
        />
        <span>{{ scope.description }}</span>
      </li>
    </ul>
  </div>

  <p class="mb-6 text-xs text-gray-500 dark:text-gray-400">
    Access is limited to the bands you belong to and the permissions you already have in each.
    You can revoke it at any time by removing the connector in {{ client.name }}.
  </p>

  <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
    <!--
      Plain HTML forms, not Inertia visits: approving or denying redirects the
      browser to the client's callback on another origin, which an XHR can't follow.
    -->
    <form
      method="POST"
      :action="denyUrl"
      data-test="deny-form"
    >
      <input
        type="hidden"
        name="_method"
        value="DELETE"
      >
      <input
        type="hidden"
        name="_token"
        :value="csrfToken"
      >
      <input
        type="hidden"
        name="client_id"
        :value="client.id"
      >
      <input
        type="hidden"
        name="auth_token"
        :value="authToken"
      >
      <breeze-button
        variant="secondary"
        outline
        class="w-full sm:w-auto justify-center"
        :disabled="submitting"
      >
        Deny
      </breeze-button>
    </form>

    <form
      method="POST"
      :action="approveUrl"
      data-test="approve-form"
      @submit="submitting = true"
    >
      <input
        type="hidden"
        name="_token"
        :value="csrfToken"
      >
      <input
        type="hidden"
        name="client_id"
        :value="client.id"
      >
      <input
        type="hidden"
        name="auth_token"
        :value="authToken"
      >
      <breeze-button
        class="w-full sm:w-auto justify-center"
        :disabled="submitting"
      >
        {{ submitting ? 'Authorizing…' : 'Approve' }}
      </breeze-button>
    </form>
  </div>
</template>

<script>
import { Head } from '@inertiajs/vue3';
import BreezeButton from '@/Components/Button';
import BreezeGuestLayout from '@/Layouts/Guest';

export default {
    layout: BreezeGuestLayout,

    components: {
        Head,
        BreezeButton,
    },

    props: {
        client: { type: Object, required: true },
        user: { type: Object, required: true },
        scopes: { type: Array, default: () => [] },
        authToken: { type: String, required: true },
        csrfToken: { type: String, required: true },
        approveUrl: { type: String, required: true },
        denyUrl: { type: String, required: true },
    },

    data() {
        return {
            submitting: false,
        };
    },
};
</script>
