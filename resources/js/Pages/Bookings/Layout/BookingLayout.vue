<template>
  <BreezeAuthenticatedLayout>
    <container>
      <NavSubmenu
        :routes="filteredRoutes"
        :booking="booking"
        :unread-comment-count="unreadCommentCount === null ? null : comments.unread.value"
        @open-comments="comments.openDrawer()"
      />
      <slot />
      <CommentsDrawer
        v-if="unreadCommentCount !== null"
        v-model:visible="comments.open.value"
        :title="`Comments · ${booking.name}`"
        :load-url="commentsUrl"
        :current-user-id="currentUserId"
        @read="comments.onRead()"
      />
    </container>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated'
import { computed, toRef } from "vue"
import { usePage } from '@inertiajs/vue3'
import { Ziggy } from '@/ziggy'
import NavSubmenu from '@/Components/NavSubmenu.vue';
import CommentsDrawer from '@/Components/Chat/CommentsDrawer.vue';
import { useCommentsDrawer } from '@/composables/useCommentsDrawer';
import { useBandRealtime } from '@/composables/useBandRealtime';

const props = defineProps({
  booking: Object,
  unreadCommentCount: { type: Number, default: null },
})

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);
const comments = useCommentsDrawer(toRef(props, 'unreadCommentCount'));
const commentsUrl = computed(() => route('chat.bookings.conversation', { band: props.booking.band_id, booking: props.booking.id }));

// Layout-level: refresh the pill when a comment lands on any booking tab.
useBandRealtime(props.booking.band_id, { message: ['unreadCommentCount'] });

const excludeRoutes = ['Create Booking', 'Booking Receipt', 'Download Booking Contract', 'bookings.history', 'bookings.historyJson', 'View Booking Contract', 'portal', 'chat.bookings.conversation']
const filteredRoutes = computed(() => {
  return Object.entries(Ziggy.routes).reduce((acc, [name, route]) => {
    if (route.uri.includes('booking/') &&
    !excludeRoutes.includes(name) &&
    !route.uri.includes('portal') &&
    route.methods.includes('GET')) {
      acc[name] = route
    }
    return acc
  }, {})
})
</script>
