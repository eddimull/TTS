<template>
  <div class="border-b-2 pt-1 pb-3 grid grid-cols-2 items-center">
    <div
      class="flex flex-col min-[320px]:flex-row items-start min-[320px]:items-center"
    >
      <div
        v-if="!isRehearsal"
        class="mb-2 sm:mb-0 sm:mr-3"
      >
        <card-icon :type="type" />
      </div>
      <div class="flex flex-col -mr-20">
        <div class="underline font-bold break-words">
          <Link :href="`/events/${eventkey}`">{{ name }}</Link>
        </div>
        <div class="text-gray-400 text-sm font-bold">
          {{ parsedDate.date }}
          <span class="sm:inline">- {{ parsedDate.day }}</span>
        </div>
      </div>
    </div>
    <div
      v-if="!isRehearsal"
      class="ml-4 text-right pr-4"
    >
      <span
        class="text-2xl font-bold leading-none cursor-pointer select-none"
        @click="openMenu"
      >&#8230;</span>
      <ContextMenu ref="menu" :model="menuItems" />
    </div>
  </div>
</template>
<script>
import CardIcon from "./CardIcon.vue";
import { DateTime } from "luxon";
export default {
    components: {
        CardIcon,
    },
    props: {
        name: {
            type: String,
            required: true,
        },
        type: {
            type: String,
            required: true,
        },
        date: {
            type: String,
            required: true,
        },
        eventkey: {
            type: String,
            required: false,
            default: 'no-key'
        },
        eventableType: {
            type: String,
            required: false,
            default: null
        },
        bookingId: {
            type: Number,
            required: false,
            default: null
        },
        bandId: {
            type: Number,
            required: false,
            default: null
        },
    },
    data() {
        return {
            parsedDate: DateTime.now().toFormat("MM-dd-yyyy"),
        };
    },
    computed: {
        isVirtual() {
            return this.eventkey && this.eventkey.startsWith('virtual-');
        },
        isRehearsal() {
            if (this.isVirtual) return true;
            if (this.eventableType === 'App\\Models\\Rehearsal') return true;
            return false;
        },
        isBooking() {
            return this.eventableType === 'App\\Models\\Bookings' && this.bookingId && this.bandId;
        },
        menuItems() {
            if (this.isVirtual) {
                return [
                    {
                        label: 'Generated Rehearsal',
                        icon: 'pi pi-info-circle',
                        disabled: true,
                    },
                ];
            }

            if (this.isBooking) {
                const b = this.bandId;
                const bk = this.bookingId;
                return [
                    {
                        label: 'Details',
                        icon: 'pi pi-file',
                        command: () => this.$inertia.get(route('Booking Details', { band: b, booking: bk })),
                    },
                    {
                        label: 'Media',
                        icon: 'pi pi-images',
                        command: () => this.$inertia.get(route('Booking Media', { band: b, booking: bk })),
                    },
                    {
                        label: 'Contacts',
                        icon: 'pi pi-users',
                        command: () => this.$inertia.get(route('Booking Contacts', { band: b, booking: bk })),
                    },
                    {
                        label: 'Finances',
                        icon: 'pi pi-dollar',
                        command: () => this.$inertia.get(route('Booking Finances', { band: b, booking: bk })),
                    },
                    {
                        label: 'Contract',
                        icon: 'pi pi-pen-to-square',
                        command: () => this.$inertia.get(route('Booking Contract', { band: b, booking: bk })),
                    },
                    {
                        label: 'Payout',
                        icon: 'pi pi-wallet',
                        command: () => this.$inertia.get(route('Booking Payout', { band: b, booking: bk })),
                    },
                ];
            }

            return [
                {
                    label: 'Edit Event',
                    icon: 'pi pi-pencil',
                    command: () => this.$inertia.get(`/events/${this.eventkey}/edit`),
                },
            ];
        },
    },
    created() {
        this.parsedDate = {
            date: DateTime.fromISO(this.date).toFormat("MM-dd-yyyy"),
            day: DateTime.fromISO(this.date).toFormat("(EEE)"),
        };
    },
    methods: {
        openMenu(event) {
            this.$refs.menu.show(event);
        },
    },
};
</script>
