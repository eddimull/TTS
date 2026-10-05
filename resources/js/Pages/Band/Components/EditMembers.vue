<template>
  <div class="space-y-6">
    <!-- Members Section -->
    <div>
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
        Members
      </h3>
      <div
        v-if="band.members && band.members.length > 0"
        class="space-y-2"
      >
        <div
          v-for="member in band.members"
          :key="member.id"
          class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
        >
          <div>
            <p class="font-medium text-gray-900 dark:text-white">
              {{ member.user.name }}
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              {{ member.user.email }}
            </p>
          </div>
          <div class="flex items-center gap-2">
            <Button
              v-if="member.user.id !== currentUserId"
              icon="pi pi-comment"
              label="Message"
              size="small"
              text
              data-test="message-user"
              :aria-label="`Message ${member.user.name}`"
              :disabled="messaging === member.user.id"
              @click="messageUser(member.user)"
            />
            <Link
              :href="'/permissions/' + band.id + '/' + member.user.id"
              class="text-blue-600 dark:text-blue-400 hover:underline text-sm"
            >
              Edit Permissions
            </Link>
          </div>
        </div>
      </div>
      <p
        v-else
        class="text-gray-600 dark:text-gray-400 italic"
      >
        No members added yet.
      </p>
    </div>

    <!-- Owners Section -->
    <div>
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
        Owners
      </h3>
      <div
        v-if="band.owners && band.owners.length > 0"
        class="space-y-2"
      >
        <div
          v-for="owner in band.owners"
          :key="owner.id"
          class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
        >
          <div>
            <p class="font-medium text-gray-900 dark:text-white">
              {{ owner.user.name }}
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              {{ owner.user.email }}
            </p>
          </div>
          <div class="flex items-center gap-2">
            <Button
              v-if="owner.user.id !== currentUserId"
              icon="pi pi-comment"
              label="Message"
              size="small"
              text
              data-test="message-user"
              :aria-label="`Message ${owner.user.name}`"
              :disabled="messaging === owner.user.id"
              @click="messageUser(owner.user)"
            />
            <button
              class="text-red-600 dark:text-red-400 hover:underline text-sm"
              @click="deleteOwner(owner)"
            >
              Remove
            </button>
          </div>
        </div>
      </div>
      <p
        v-else
        class="text-gray-600 dark:text-gray-400 italic"
      >
        No owners found.
      </p>
    </div>

    <!-- Pending Invites -->
    <div>
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
        Pending Invitations
      </h3>
      <div
        v-if="band.pending_invites && band.pending_invites.length > 0"
        class="space-y-2"
      >
        <div
          v-for="pendingInvite in band.pending_invites"
          :key="pendingInvite.id"
          class="flex items-center justify-between p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800"
        >
          <div>
            <p class="font-medium text-gray-900 dark:text-white">
              {{ pendingInvite.email }}
            </p>
            <p class="text-sm text-yellow-700 dark:text-yellow-400">
              Invitation pending
            </p>
          </div>
          <button
            class="text-red-600 dark:text-red-400 hover:underline text-sm"
            @click="deleteInvite(pendingInvite)"
          >
            Cancel
          </button>
        </div>
      </div>
      <p
        v-else
        class="text-gray-600 dark:text-gray-400 italic"
      >
        No pending invitations.
      </p>
    </div>

    <!-- Invite Form -->
    <div class="border-t border-gray-200 dark:border-gray-600 pt-6">
      <div v-if="!inviting">
        <Button
          label="Invite New Member"
          icon="pi pi-user-plus"
          @click="startInviting"
        />
      </div>
        
      <transition name="slide-down">
        <div
          v-if="inviting"
          class="space-y-4"
        >
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Email Address
            </label>
            <input
              :value="invite.email"
              type="email"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
              placeholder="user@example.com"
              required
              @input="updateInviteEmail"
            >
          </div>
          <div class="flex items-center gap-3">
            <Button
              label="Invite as Owner"
              icon="pi pi-star"
              severity="secondary"
              size="small"
              @click="inviteOwner"
            />
            <Button
              label="Invite as Member"
              icon="pi pi-user"
              severity="secondary"
              size="small"
              @click="inviteMember"
            />
            <Button
              label="Cancel"
              icon="pi pi-times"
              severity="secondary"
              text
              size="small"
              @click="cancelInviting"
            />
          </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { router } from '@inertiajs/vue3';

export default {
  name: 'EditMembers',
  props: {
    band: {
      type: Object,
      required: true
    },
    inviting: {
      type: Boolean,
      default: false
    },
    invite: {
      type: Object,
      required: true
    }
  },
  emits: ['delete-owner', 'delete-invite', 'invite-owner', 'invite-member', 'update-inviting', 'update-invite-email'],
  data() {
    return { messaging: null };
  },
  computed: {
    currentUserId() {
      return this.$page?.props?.auth?.user?.id ?? null;
    },
  },
  methods: {
    async messageUser(user) {
      this.messaging = user.id;
      try {
        const { data } = await axios.post(this.route('chat.conversations.dm'), { user_id: user.id });
        router.visit(this.route('messages.index', data.conversation.id));
      } catch (e) {
        this.$toast?.add({
          severity: 'error',
          summary: 'Could not start the conversation',
          detail: e?.response?.data?.message || 'Please try again.',
          life: 4000,
        });
      } finally {
        this.messaging = null;
      }
    },
    deleteOwner(owner) {
      this.$emit('delete-owner', owner);
    },
    deleteInvite(invite) {
      this.$emit('delete-invite', invite);
    },
    inviteOwner() {
      this.$emit('invite-owner');
    },
    inviteMember() {
      this.$emit('invite-member');
    },
    startInviting() {
      this.$emit('update-inviting', true);
    },
    cancelInviting() {
      this.$emit('update-inviting', false);
    },
    updateInviteEmail(event) {
      this.$emit('update-invite-email', event.target.value);
    }
  }
}
</script>

<style scoped>
.slide-down-enter-active {
  transition: all .2s ease;
}

.slide-down-leave-active {
  transition: all .1s cubic-bezier(1.0, 0.5, 0.8, 1.0);
  max-height: 230px;
}

.slide-down-enter-from,
.slide-down-leave-to {
  transform: translateY(-50px);
  max-height: 0px;
}
</style>
